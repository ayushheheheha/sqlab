<?php

declare(strict_types=1);

final class PracticeRunner
{
    private int $userId;
    private ?string $tempDbName = null;
    private const SESSION_KEY = 'practice_sandbox_db';

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function execute(string $query): array
    {
        $this->ensureSandbox();

        $query = trim($query);
        $query = preg_replace('/;\s*$/', '', $query) ?? $query;

        if ($this->tempDbName === null) {
            return $this->errorResult('Sandbox is not ready.');
        }

        if ($query === '' || str_contains($query, ';')) {
            return $this->errorResult('Run one SQL statement at a time.');
        }

        if ($this->containsForbiddenSql($query)) {
            return $this->errorResult('This statement is blocked in practice mode.');
        }

        try {
            $pdo = DB::sandboxConnection($this->tempDbName);
            $this->applyExecutionLimit($pdo);
            $statementType = strtoupper((string) strtok(ltrim($query), " \t\r\n"));

            $startedAt = microtime(true);
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $executionMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($stmt->columnCount() === 0) {
                return [
                    'success' => true,
                    'rows' => [],
                    'columns' => [],
                    'row_count' => (int) $stmt->rowCount(),
                    'execution_ms' => $executionMs,
                    'error' => null,
                    'message' => $this->buildMutationMessage($statementType, (int) $stmt->rowCount()),
                    'statement_type' => $statementType,
                    'affected_rows' => (int) $stmt->rowCount(),
                    'tables' => $this->listTables($pdo),
                    'target_table' => $this->extractTargetTable($query, $statementType),
                    'table_columns' => $this->describeTargetTable($pdo, $query, $statementType),
                    'table_preview' => $this->previewTargetTable($pdo, $query, $statementType),
                ];
            }

            $rows = $stmt->fetchAll();

            return [
                'success' => true,
                'rows' => $rows,
                'columns' => $this->columnNames($stmt, $rows),
                'row_count' => count($rows),
                'execution_ms' => $executionMs,
                'error' => null,
                'message' => null,
                'statement_type' => $statementType,
                'affected_rows' => 0,
                'tables' => $this->listTables($pdo),
                'target_table' => null,
                'table_columns' => [],
                'table_preview' => null,
            ];
        } catch (Throwable $throwable) {
            return $this->errorResult($throwable->getMessage());
        }
    }

    public function resetSandbox(): void
    {
        $this->destroySandbox();
        $this->ensureSandbox();
    }

    public function destroySandbox(): void
    {
        $this->tempDbName = $this->loadSessionDbName();

        if ($this->tempDbName === null) {
            return;
        }

        try {
            DB::getConnection()->exec(sprintf("REVOKE ALL PRIVILEGES ON `%s`.* FROM '%s'@'localhost'", $this->tempDbName, $this->sandboxUser()));
        } catch (Throwable) {
        }

        try {
            DB::getConnection()->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->tempDbName));
        } finally {
            unset($_SESSION[self::SESSION_KEY]);
            $this->tempDbName = null;
        }
    }

    private function ensureSandbox(): void
    {
        $existing = $this->loadSessionDbName();
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $rootPdo = DB::getConnection();

        if ($existing !== null && $this->databaseExists($rootPdo, $existing)) {
            $this->tempDbName = $existing;
            return;
        }

        $this->tempDbName = sprintf('sqlab_practice_%d_%s', max(0, $this->userId), str_replace('.', '', uniqid('', true)));
        $rootPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s_unicode_ci', $this->tempDbName, $charset, $charset));
        $rootPdo->exec(sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'localhost'", $this->tempDbName, $this->sandboxUser()));
        $_SESSION[self::SESSION_KEY] = $this->tempDbName;
    }

    private function loadSessionDbName(): ?string
    {
        $value = (string) ($_SESSION[self::SESSION_KEY] ?? '');

        if ($value === '' || !preg_match('/^sqlab_practice_\d+_[a-z0-9]+$/', $value)) {
            return null;
        }

        return $value;
    }

    private function databaseExists(PDO $pdo, string $dbName): bool
    {
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1');
        $stmt->execute(['name' => $dbName]);

        return (bool) $stmt->fetchColumn();
    }

    private function containsForbiddenSql(string $query): bool
    {
        $forbidden = [
            'CREATE USER',
            'DROP USER',
            'ALTER USER',
            'RENAME USER',
            'GRANT',
            'REVOKE',
            'SET GLOBAL',
            'SET @@GLOBAL',
            'DROP DATABASE',
            'CREATE DATABASE',
            'USE ',
            'FLUSH',
            'SHUTDOWN',
            'INSTALL PLUGIN',
            'UNINSTALL PLUGIN',
            'INTO OUTFILE',
            'INTO DUMPFILE',
            'LOAD DATA',
        ];

        $upper = strtoupper($query);

        foreach ($forbidden as $pattern) {
            if (str_contains($upper, $pattern)) {
                return true;
            }
        }

        return false;
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
        }
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
            'message' => null,
            'statement_type' => null,
            'affected_rows' => 0,
            'tables' => [],
            'target_table' => null,
            'table_columns' => [],
            'table_preview' => null,
        ];
    }

    private function buildMutationMessage(string $statementType, int $affectedRows): string
    {
        return match ($statementType) {
            'CREATE' => 'Object created successfully.',
            'ALTER' => 'Object altered successfully.',
            'DROP' => 'Object dropped successfully.',
            'TRUNCATE' => 'Table truncated successfully.',
            'INSERT' => sprintf('%d row(s) inserted.', $affectedRows),
            'UPDATE' => sprintf('%d row(s) updated.', $affectedRows),
            'DELETE' => sprintf('%d row(s) deleted.', $affectedRows),
            default => 'Statement executed successfully.',
        };
    }

    private function listTables(PDO $pdo): array
    {
        $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

        return array_map(static fn (array $row): string => (string) ($row[0] ?? ''), $rows);
    }

    private function extractTargetTable(string $query, string $statementType): ?string
    {
        $patterns = [
            'CREATE' => '/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
            'ALTER' => '/^\s*ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i',
            'DROP' => '/^\s*DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
            'TRUNCATE' => '/^\s*TRUNCATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i',
            'INSERT' => '/^\s*INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            'UPDATE' => '/^\s*UPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
            'DELETE' => '/^\s*DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i',
        ];

        $pattern = $patterns[$statementType] ?? null;

        if ($pattern === null || !preg_match($pattern, $query, $matches)) {
            return null;
        }

        $table = (string) ($matches[1] ?? '');

        return preg_match('/^[a-zA-Z0-9_]+$/', $table) ? $table : null;
    }

    private function describeTargetTable(PDO $pdo, string $query, string $statementType): array
    {
        $table = $this->extractTargetTable($query, $statementType);

        if ($table === null) {
            return [];
        }

        try {
            $stmt = $pdo->query(sprintf('DESCRIBE `%s`', $table));
            $rows = $stmt->fetchAll();

            return array_map(static function (array $row): array {
                return [
                    'field' => (string) ($row['Field'] ?? ''),
                    'type' => (string) ($row['Type'] ?? ''),
                    'null' => (string) ($row['Null'] ?? ''),
                    'key' => (string) ($row['Key'] ?? ''),
                ];
            }, $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function previewTargetTable(PDO $pdo, string $query, string $statementType): ?array
    {
        if (!in_array($statementType, ['INSERT', 'UPDATE', 'DELETE'], true)) {
            return null;
        }

        $table = $this->extractTargetTable($query, $statementType);

        if ($table === null) {
            return null;
        }

        try {
            $stmt = $pdo->query(sprintf('SELECT * FROM `%s` LIMIT 25', $table));
            $rows = $stmt->fetchAll();

            return [
                'table' => $table,
                'columns' => $this->columnNames($stmt, $rows),
                'rows' => $rows,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function sandboxUser(): string
    {
        return str_replace("'", "''", $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox');
    }
}
