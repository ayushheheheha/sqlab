<?php

declare(strict_types=1);

final class QueryRunner
{
    private const MAX_SELECT_ROWS = 500;

    private int $problemId;
    private int $userId;
    private ?string $tempDbName = null;
    private bool $sharedHostingMode = false;
    private string $tablePrefix = '';
    private array $tableMap = [];
    private ?array $problem = null;
    private string $expectedQuery = '';
    private static bool $sandboxPrivilegesVerified = false;

    public function __construct(int $problemId)
    {
        $this->problemId = $problemId;
        $this->userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->sharedHostingMode = $this->isSharedHostingMode();
    }

    public function setupSandbox(): void
    {
        $this->problem = Problem::findWithDatasets($this->problemId);

        if (!$this->problem || (int) $this->problem['is_active'] !== 1) {
            throw new RuntimeException('Problem not found.');
        }

        $this->expectedQuery = (string) $this->problem['expected_query'];
        $rootPdo = DB::getConnection();

        if ($this->sharedHostingMode) {
            $this->tablePrefix = sprintf('sqlab_q_%d_%d_%s_', max(0, $this->userId), $this->problemId, $this->randomToken());
            $this->tableMap = $this->buildTableMap($this->problem, $this->tablePrefix);
            $rootSandbox = $rootPdo;
        } else {
            $this->assertSandboxUserPrivileges();
            $this->tempDbName = sprintf('sqlab_sandbox_%d_%s', max(0, $this->userId), $this->randomToken());
            $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
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
        }

        foreach ($this->problem['dataset_records'] as $dataset) {
            self::runSqlBatch($rootSandbox, (string) $dataset['schema_sql'], $this->tableMap);
            self::runSqlBatch($rootSandbox, (string) $dataset['seed_sql'], $this->tableMap);
        }

        if (!empty($this->problem['dataset_sql'])) {
            self::runSqlBatch($rootSandbox, (string) $this->problem['dataset_sql'], $this->tableMap);
        }
    }

    public function run(string $userQuery): array
    {
        $userQuery = trim($userQuery);
        $userQuery = preg_replace('/;\s*$/', '', $userQuery) ?? $userQuery;
        $truncated = false;

        if (!$this->sharedHostingMode && $this->tempDbName === null) {
            return $this->errorResult('Sandbox is not ready.');
        }

        if ($userQuery === '' || str_contains($userQuery, ';') || !preg_match('/^\s*(SELECT|WITH)\b/i', $userQuery) || preg_match('/\b(DROP|CREATE|INSERT|UPDATE|DELETE|ALTER|TRUNCATE)\b/i', $userQuery)) {
            return $this->errorResult('Only SELECT queries are allowed.');
        }

        try {
            $pdo = $this->sharedHostingMode ? DB::getConnection() : DB::sandboxConnection((string) $this->tempDbName);
            $this->applyExecutionLimit($pdo);
            $queryToRun = sqlab_translate_oracle_sql($userQuery);
            $queryToRun = $this->rewriteSqlWithMap($queryToRun, $this->tableMap);
            $queryToRun = $this->enforceSelectLimit($queryToRun, $truncated);

            $startedAt = microtime(true);
            $stmt = $pdo->query($queryToRun);
            $rows = $stmt->fetchAll();
            $executionMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'success' => true,
                'rows' => $rows,
                'columns' => $this->columnNames($stmt, $rows),
                'row_count' => count($rows),
                'execution_ms' => $executionMs,
                'truncated' => $truncated,
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            return $this->errorResult($throwable->getMessage(), $truncated);
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
        if ($this->sharedHostingMode) {
            $this->dropMappedTables(DB::getConnection(), $this->tableMap);
            $this->tableMap = [];
            return;
        }

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

    private function errorResult(string $message, bool $truncated = false): array
    {
        return [
            'success' => false,
            'rows' => [],
            'columns' => [],
            'row_count' => 0,
            'execution_ms' => 0,
            'truncated' => $truncated,
            'error' => $message,
        ];
    }

    private function enforceSelectLimit(string $query, bool &$truncated): string
    {
        if (preg_match('/\bLIMIT\s+\d+/i', $query)) {
            $truncated = false;

            return $query;
        }

        $truncated = true;

        return rtrim($query) . ' LIMIT ' . self::MAX_SELECT_ROWS;
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

    private static function runSqlBatch(PDO $pdo, string $sql, array $tableMap = []): void
    {
        foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $statement) {
            if ($statement !== '') {
                $pdo->exec(self::rewriteSqlStatic($statement, $tableMap));
            }
        }
    }

    private function rewriteSqlWithMap(string $sql, array $tableMap): string
    {
        return self::rewriteSqlStatic($sql, $tableMap);
    }

    private static function rewriteSqlStatic(string $sql, array $tableMap): string
    {
        return sqlab_rewrite_sql_with_map($sql, $tableMap);
    }

    private function buildTableMap(array $problem, string $prefix): array
    {
        $names = [];

        foreach (($problem['dataset_records'] ?? []) as $dataset) {
            $names = [...$names, ...$this->extractCreatedTableNames((string) ($dataset['schema_sql'] ?? ''))];
        }

        $names = [...$names, ...$this->extractCreatedTableNames((string) ($problem['dataset_sql'] ?? ''))];
        $names = array_values(array_unique(array_filter($names)));
        $map = [];

        foreach ($names as $name) {
            $map[$name] = $prefix . $name;
        }

        return $map;
    }

    private function extractCreatedTableNames(string $sql): array
    {
        if ($sql === '') {
            return [];
        }

        preg_match_all('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $sql, $matches);

        return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    }

    private function dropMappedTables(PDO $pdo, array $tableMap): void
    {
        foreach (array_reverse(array_values($tableMap)) as $tableName) {
            try {
                $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
            } catch (Throwable) {
            }
        }
    }

    private function isSharedHostingMode(): bool
    {
        return strtolower((string) ($_ENV['SHARED_HOSTING_MODE'] ?? 'false')) === 'true';
    }

    private function sandboxUser(): string
    {
        return str_replace("'", "''", $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox');
    }

    private function randomToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function assertSandboxUserPrivileges(): void
    {
        if (self::$sandboxPrivilegesVerified) {
            return;
        }

        $charset = (string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4');
        $pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3306',
                $charset
            ),
            $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox',
            $_ENV['DB_SANDBOX_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $grants = $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);

        if (!$grants) {
            throw new RuntimeException('Sandbox user grants could not be verified.');
        }

        $dangerousPattern = '/\b(ALL PRIVILEGES|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE|INDEX|REFERENCES|TRIGGER|EVENT|EXECUTE|FILE|SUPER|GRANT OPTION|CREATE USER|RELOAD|PROCESS|SHUTDOWN|REPLICATION)\b/i';

        foreach ($grants as $grant) {
            if (preg_match($dangerousPattern, (string) $grant)) {
                throw new RuntimeException('Sandbox user has elevated database privileges.');
            }
        }

        self::$sandboxPrivilegesVerified = true;
    }
}
