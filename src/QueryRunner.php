<?php

declare(strict_types=1);

final class QueryRunner
{
    private int $problemId;
    private int $userId;
    private ?string $tempDbName = null;
    private ?array $problem = null;
    private string $expectedQuery = '';

    public function __construct(int $problemId)
    {
        $this->problemId = $problemId;
        $this->userId = (int) ($_SESSION['user_id'] ?? 0);
    }

    public function setupSandbox(): void
    {
        $this->problem = Problem::findWithDatasets($this->problemId);

        if (!$this->problem || (int) $this->problem['is_active'] !== 1) {
            throw new RuntimeException('Problem not found.');
        }

        $this->expectedQuery = (string) $this->problem['expected_query'];
        $this->tempDbName = sprintf('sqlab_sandbox_%d_%s', max(0, $this->userId), str_replace('.', '', uniqid('', true)));
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $rootPdo = DB::getConnection();

        $rootPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s_unicode_ci', $this->tempDbName, $charset, $charset));
        $rootPdo->exec(sprintf("GRANT SELECT ON `%s`.* TO '%s'@'localhost'", $this->tempDbName, $this->sandboxUser()));

        $rootSandbox = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3306',
                $this->tempDbName,
                $charset
            ),
            $_ENV['DB_USER'] ?? '',
            $_ENV['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        foreach ($this->problem['dataset_records'] as $dataset) {
            self::runSqlBatch($rootSandbox, (string) $dataset['schema_sql']);
            self::runSqlBatch($rootSandbox, (string) $dataset['seed_sql']);
        }

        if (!empty($this->problem['dataset_sql'])) {
            self::runSqlBatch($rootSandbox, (string) $this->problem['dataset_sql']);
        }
    }

    public function run(string $userQuery): array
    {
        $userQuery = trim($userQuery);
        $userQuery = preg_replace('/;\s*$/', '', $userQuery) ?? $userQuery;

        if ($this->tempDbName === null) {
            return $this->errorResult('Sandbox is not ready.');
        }

        if ($userQuery === '' || str_contains($userQuery, ';') || !preg_match('/^\s*(SELECT|WITH)\b/i', $userQuery) || preg_match('/\b(DROP|CREATE|INSERT|UPDATE|DELETE|ALTER|TRUNCATE)\b/i', $userQuery)) {
            return $this->errorResult('Only SELECT queries are allowed.');
        }

        try {
            $pdo = DB::sandboxConnection($this->tempDbName);
            $this->applyExecutionLimit($pdo);

            $startedAt = microtime(true);
            $stmt = $pdo->query($userQuery);
            $rows = $stmt->fetchAll();
            $executionMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'success' => true,
                'rows' => $rows,
                'columns' => $this->columnNames($stmt, $rows),
                'row_count' => count($rows),
                'execution_ms' => $executionMs,
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            return $this->errorResult($throwable->getMessage());
        }
    }

    public function checkCorrectness(array $userRows): bool
    {
        $expected = $this->run($this->expectedQuery);

        if (!$expected['success']) {
            return false;
        }

        return $this->normalizeRows($userRows) === $this->normalizeRows($expected['rows']);
    }

    public function teardown(): void
    {
        if ($this->tempDbName === null) {
            return;
        }

        try {
            DB::getConnection()->exec(sprintf("REVOKE SELECT ON `%s`.* FROM '%s'@'localhost'", $this->tempDbName, $this->sandboxUser()));
        } catch (Throwable) {
        }

        try {
            DB::getConnection()->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->tempDbName));
        } finally {
            $this->tempDbName = null;
        }
    }

    public function problem(): array
    {
        if ($this->problem === null) {
            $this->problem = Problem::findWithDatasets($this->problemId) ?? [];
        }

        return $this->problem;
    }

    public function schemaSql(): string
    {
        $schema = [];

        foreach (($this->problem()['dataset_records'] ?? []) as $dataset) {
            $schema[] = trim((string) $dataset['schema_sql']);
        }

        if (!empty($this->problem['dataset_sql'])) {
            $schema[] = trim((string) $this->problem['dataset_sql']);
        }

        return implode("\n\n", array_filter($schema));
    }

    private function columnNames(PDOStatement $stmt, array $rows): array
    {
        $columns = [];

        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta = $stmt->getColumnMeta($i);
            $columns[] = (string) ($meta['name'] ?? $i);
        }

        return $columns ?: array_keys($rows[0] ?? []);
    }

    private function errorResult(string $message): array
    {
        return [
            'success' => false,
            'rows' => [],
            'columns' => [],
            'row_count' => 0,
            'execution_ms' => 0,
            'error' => $message,
        ];
    }

    private function applyExecutionLimit(PDO $pdo): void
    {
        try {
            $pdo->exec('SET SESSION MAX_EXECUTION_TIME=3000');
            return;
        } catch (Throwable) {
        }

        try {
            $pdo->exec('SET SESSION max_statement_time=3');
        } catch (Throwable) {
            // Some local MySQL/MariaDB builds do not expose a per-session query timeout variable.
        }
    }

    private function normalizeRows(array $rows): array
    {
        $normalized = array_map(static function (array $row): string {
            ksort($row);

            return json_encode(array_map(static fn ($value) => is_numeric($value) ? (string) $value : $value, $row), JSON_THROW_ON_ERROR);
        }, $rows);

        sort($normalized, SORT_STRING);

        return $normalized;
    }

    private static function runSqlBatch(PDO $pdo, string $sql): void
    {
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    private function sandboxUser(): string
    {
        return str_replace("'", "''", $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox');
    }
}
