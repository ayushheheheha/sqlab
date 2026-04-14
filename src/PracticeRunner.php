<?php

declare(strict_types=1);

final class PracticeRunner
{
    private int $userId;
    private ?string $tempDbName = null;
    private bool $sharedHostingMode = false;
    private string $tablePrefix = '';
    private array $tableMap = [];
    private const SESSION_KEY = 'practice_sandbox_db';
    private const SESSION_TABLE_MAP_KEY = 'practice_sandbox_tables';
    private const SESSION_PREFIX_KEY = 'practice_sandbox_prefix';
    private static bool $staleCleanupChecked = false;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->sharedHostingMode = $this->isSharedHostingMode();
    }

    public function execute(string $query): array
    {
        $this->ensureSandbox();

        $query = trim($query);
        $query = preg_replace('/;\s*$/', '', $query) ?? $query;

        if (!$this->sharedHostingMode && $this->tempDbName === null) {
            return $this->errorResult('Sandbox is not ready.');
        }

        if ($query === '' || str_contains($query, ';')) {
            return $this->errorResult('Run one SQL statement at a time.');
        }

        if ($this->containsForbiddenSql($query)) {
            return $this->errorResult('This statement is blocked in practice mode.');
        }

        try {
            $pdo = $this->sharedHostingMode ? DB::getConnection() : DB::sandboxConnection((string) $this->tempDbName);
            $this->applyExecutionLimit($pdo);
            $statementType = strtoupper((string) strtok(ltrim($query), " \t\r\n"));
            $queryToRun = sqlab_translate_oracle_sql($query);
            $queryToRun = $this->sharedHostingMode ? $this->rewritePracticeQuery($queryToRun, $statementType) : $queryToRun;

            $startedAt = microtime(true);
            $stmt = $pdo->prepare($queryToRun);
            $stmt->execute();
            $executionMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($this->sharedHostingMode) {
                $this->persistSandboxState();
            }

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
        if ($this->sharedHostingMode) {
            $this->tableMap = $this->loadSessionTableMap();
            $this->dropMappedTables(DB::getConnection(), $this->tableMap);
            unset($_SESSION[self::SESSION_TABLE_MAP_KEY], $_SESSION[self::SESSION_PREFIX_KEY], $_SESSION[self::SESSION_KEY]);
            $this->tableMap = [];
            $this->tablePrefix = '';
            $this->tempDbName = null;
            return;
        }

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

    public function destroyAllForUser(): int
    {
        if ($this->sharedHostingMode) {
            $root = DB::getConnection();
            $pattern = sprintf('sqlab_practice_%d_', max(0, $this->userId)) . '%';
            $stmt = $root->prepare(
                'SELECT TABLE_NAME
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME LIKE :name_like'
            );
            $stmt->execute(['name_like' => $pattern]);

            $dropped = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
                if (!preg_match('/^sqlab_practice_\d+_[a-z0-9]+_[a-zA-Z0-9_]+$/', (string) $tableName)) {
                    continue;
                }

                try {
                    $root->exec(sprintf('DROP TABLE IF EXISTS `%s`', (string) $tableName));
                    $dropped++;
                } catch (Throwable) {
                }
            }

            unset($_SESSION[self::SESSION_TABLE_MAP_KEY], $_SESSION[self::SESSION_PREFIX_KEY], $_SESSION[self::SESSION_KEY]);
            $this->tableMap = [];
            $this->tablePrefix = '';
            $this->tempDbName = null;

            return $dropped;
        }

        $root = DB::getConnection();
        $pattern = sprintf('sqlab_practice_%d_', max(0, $this->userId));
        $stmt = $root->prepare(
            'SELECT SCHEMA_NAME
             FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME LIKE :name_like'
        );
        $stmt->execute(['name_like' => $pattern . '%']);

        $dropped = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $dbName) {
            $name = (string) $dbName;

            if (!preg_match('/^sqlab_practice_\d+_[a-z0-9]+$/', $name)) {
                continue;
            }

            try {
                $root->exec(sprintf("REVOKE ALL PRIVILEGES ON `%s`.* FROM '%s'@'localhost'", $name, $this->sandboxUser()));
            } catch (Throwable) {
            }

            try {
                $root->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $name));
                $dropped++;
            } catch (Throwable) {
            }
        }

        unset($_SESSION[self::SESSION_KEY]);
        $this->tempDbName = null;

        return $dropped;
    }

    public static function cleanupStaleSandboxes(int $maxAgeHours = 24): int
    {
        $maxAgeHours = max(1, $maxAgeHours);
        $sharedHostingMode = strtolower((string) ($_ENV['SHARED_HOSTING_MODE'] ?? 'false')) === 'true';

        if ($sharedHostingMode) {
            return self::cleanupStalePracticeTables($maxAgeHours);
        }

        $root = DB::getConnection();
        $stmt = $root->query(
            'SELECT SCHEMA_NAME, CREATE_TIME
             FROM INFORMATION_SCHEMA.SCHEMATA
             WHERE SCHEMA_NAME LIKE "sqlab_practice_%"'
        );

        $dropped = 0;
        $cutoff = time() - ($maxAgeHours * 3600);
        $sandboxUser = $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox';

        foreach ($stmt->fetchAll() as $row) {
            $dbName = (string) ($row['SCHEMA_NAME'] ?? '');
            $createTimeRaw = (string) ($row['CREATE_TIME'] ?? '');

            if (!preg_match('/^sqlab_practice_\d+_[a-z0-9]+$/', $dbName)) {
                continue;
            }

            $createTs = strtotime($createTimeRaw ?: '');
            if ($createTs === false || $createTs > $cutoff) {
                continue;
            }

            try {
                $root->exec(sprintf("REVOKE ALL PRIVILEGES ON `%s`.* FROM '%s'@'localhost'", $dbName, $sandboxUser));
            } catch (Throwable) {
            }

            try {
                $root->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $dbName));
                $dropped++;
            } catch (Throwable) {
            }
        }

        return $dropped;
    }

    private function ensureSandbox(): void
    {
        if (!self::$staleCleanupChecked) {
            self::$staleCleanupChecked = true;

            try {
                self::cleanupStaleSandboxes((int) ($_ENV['PRACTICE_SANDBOX_MAX_AGE_HOURS'] ?? 24));
            } catch (Throwable) {
            }
        }

        if ($this->sharedHostingMode) {
            $this->tableMap = $this->loadSessionTableMap();
            $this->tablePrefix = (string) ($_SESSION[self::SESSION_PREFIX_KEY] ?? '');

            if ($this->tablePrefix === '') {
                $this->tablePrefix = sprintf('sqlab_practice_%d_%s_', max(0, $this->userId), str_replace('.', '', uniqid('', true)));
                $_SESSION[self::SESSION_PREFIX_KEY] = $this->tablePrefix;
            }

            $this->tempDbName = (string) ($_ENV['DB_NAME'] ?? '');
            return;
        }

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
        if ($this->sharedHostingMode) {
            return array_values(array_keys($this->tableMap));
        }

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
            $physicalTable = $this->sharedHostingMode ? ($this->tableMap[$table] ?? $table) : $table;
            $stmt = $pdo->query(sprintf('DESCRIBE `%s`', $physicalTable));
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
            $physicalTable = $this->sharedHostingMode ? ($this->tableMap[$table] ?? $table) : $table;
            $stmt = $pdo->query(sprintf('SELECT * FROM `%s` LIMIT 25', $physicalTable));
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

    private function rewritePracticeQuery(string $query, string $statementType): string
    {
        if ($query === '') {
            return $query;
        }

        if ($statementType === 'CREATE') {
            $logical = $this->extractTargetTable($query, $statementType);

            if ($logical !== null && !isset($this->tableMap[$logical])) {
                $this->tableMap[$logical] = $this->tablePrefix . $logical;
            }
        }

        $rewritten = $this->rewriteSqlWithMap($query, $this->tableMap);

        if ($statementType === 'DROP') {
            $logical = $this->extractTargetTable($query, $statementType);
            if ($logical !== null) {
                unset($this->tableMap[$logical]);
            }
        }

        return $rewritten;
    }

    private function rewriteSqlWithMap(string $sql, array $tableMap): string
    {
        return sqlab_rewrite_sql_with_map($sql, $tableMap);
    }

    private function loadSessionTableMap(): array
    {
        $raw = $_SESSION[self::SESSION_TABLE_MAP_KEY] ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $map = [];

        foreach ($raw as $logical => $physical) {
            $logicalName = (string) $logical;
            $physicalName = (string) $physical;

            if (!preg_match('/^[a-zA-Z0-9_]+$/', $logicalName)) {
                continue;
            }

            if (!preg_match('/^sqlab_practice_\d+_[a-z0-9]+_[a-zA-Z0-9_]+$/', $physicalName)) {
                continue;
            }

            $map[$logicalName] = $physicalName;
        }

        return $map;
    }

    private function persistSandboxState(): void
    {
        $_SESSION[self::SESSION_TABLE_MAP_KEY] = $this->tableMap;
        $_SESSION[self::SESSION_PREFIX_KEY] = $this->tablePrefix;
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

    private static function cleanupStalePracticeTables(int $maxAgeHours): int
    {
        $root = DB::getConnection();
        $stmt = $root->query(
            'SELECT TABLE_NAME, CREATE_TIME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE "sqlab_practice_%"'
        );

        $dropped = 0;
        $cutoff = time() - ($maxAgeHours * 3600);

        foreach ($stmt->fetchAll() as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? '');
            $createTimeRaw = (string) ($row['CREATE_TIME'] ?? '');

            if (!preg_match('/^sqlab_practice_\d+_[a-z0-9]+_[a-zA-Z0-9_]+$/', $tableName)) {
                continue;
            }

            $createTs = strtotime($createTimeRaw ?: '');
            if ($createTs === false || $createTs > $cutoff) {
                continue;
            }

            try {
                $root->exec(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
                $dropped++;
            } catch (Throwable) {
            }
        }

        return $dropped;
    }

    private function isSharedHostingMode(): bool
    {
        return strtolower((string) ($_ENV['SHARED_HOSTING_MODE'] ?? 'false')) === 'true';
    }
}
