<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!Auth::getCurrentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$datasetId = (int) ($_GET['dataset_id'] ?? 0);

if ($datasetId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Valid dataset_id is required.']);
    exit;
}

$pdo = DB::getConnection();
$datasetStmt = $pdo->prepare('SELECT schema_sql FROM datasets WHERE id = :id LIMIT 1');
$datasetStmt->execute(['id' => $datasetId]);
$schemaSql = (string) $datasetStmt->fetchColumn();

if ($schemaSql === '') {
    http_response_code(404);
    echo json_encode(['error' => 'Dataset not found.']);
    exit;
}

$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
$tempDbName = 'sqlab_schema_' . $datasetId . '_' . str_replace('.', '', uniqid('', true));
$sharedHostingMode = strtolower((string) ($_ENV['SHARED_HOSTING_MODE'] ?? 'false')) === 'true';
$tableMap = [];

if (!$sharedHostingMode) {
    // TODO(security): Dynamic SQL is required for ephemeral schema database names.
    $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s_unicode_ci', $tempDbName, $charset, $charset));
}

try {
    if ($sharedHostingMode) {
        $prefix = 'sqlab_schema_' . $datasetId . '_' . str_replace('.', '', uniqid('', true)) . '_';
        $tableMap = buildTableMap($schemaSql, $prefix);
        runSqlBatch($pdo, $schemaSql, $tableMap);
    } else {
        $schemaDb = new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $_ENV['DB_HOST'] ?? '127.0.0.1',
                $_ENV['DB_PORT'] ?? '3306',
                $tempDbName,
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

        runSqlBatch($schemaDb, $schemaSql);
    }

    if ($sharedHostingMode) {
        $tableNames = array_values($tableMap);
        $quoted = array_map(static fn (string $name): string => DB::getConnection()->quote($name), $tableNames);

        $query = 'SELECT
            c.TABLE_NAME AS table_name,
            c.COLUMN_NAME AS column_name,
            c.COLUMN_TYPE AS column_type,
            CASE WHEN k.CONSTRAINT_NAME = "PRIMARY" THEN 1 ELSE 0 END AS is_pk,
            k.REFERENCED_TABLE_NAME AS fk_table,
            k.REFERENCED_COLUMN_NAME AS fk_column
         FROM INFORMATION_SCHEMA.COLUMNS c
         LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
            ON k.TABLE_SCHEMA = c.TABLE_SCHEMA
           AND k.TABLE_NAME = c.TABLE_NAME
           AND k.COLUMN_NAME = c.COLUMN_NAME
         WHERE c.TABLE_SCHEMA = DATABASE()';

        if ($quoted !== []) {
            $query .= ' AND c.TABLE_NAME IN (' . implode(', ', $quoted) . ')';
        } else {
            $query .= ' AND 1 = 0';
        }

        $query .= ' ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION';
        $columnStmt = $pdo->query($query);
    } else {
        $columnStmt = $pdo->prepare(
            'SELECT
                c.TABLE_NAME AS table_name,
                c.COLUMN_NAME AS column_name,
                c.COLUMN_TYPE AS column_type,
                CASE WHEN k.CONSTRAINT_NAME = "PRIMARY" THEN 1 ELSE 0 END AS is_pk,
                k.REFERENCED_TABLE_NAME AS fk_table,
                k.REFERENCED_COLUMN_NAME AS fk_column
             FROM INFORMATION_SCHEMA.COLUMNS c
             LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
                ON k.TABLE_SCHEMA = c.TABLE_SCHEMA
               AND k.TABLE_NAME = c.TABLE_NAME
               AND k.COLUMN_NAME = c.COLUMN_NAME
             WHERE c.TABLE_SCHEMA = :schema_name
             ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION'
        );
        $columnStmt->execute(['schema_name' => $tempDbName]);
    }

    $rows = $columnStmt->fetchAll();
    $reverseMap = array_flip($tableMap);

    $tables = [];

    foreach ($rows as $row) {
        $rawName = (string) $row['table_name'];
        $name = $sharedHostingMode ? ((string) ($reverseMap[$rawName] ?? $rawName)) : $rawName;

        if (!isset($tables[$name])) {
            $tables[$name] = ['name' => $name, 'columns' => []];
        }

        $tables[$name]['columns'][] = [
            'name' => (string) $row['column_name'],
            'type' => (string) $row['column_type'],
            'is_pk' => (int) $row['is_pk'] === 1,
            'fk_table' => $row['fk_table'] ? (string) ($sharedHostingMode ? ($reverseMap[(string) $row['fk_table']] ?? (string) $row['fk_table']) : $row['fk_table']) : null,
            'fk_column' => $row['fk_column'] ? (string) $row['fk_column'] : null,
        ];
    }

    echo json_encode(['tables' => array_values($tables)], JSON_THROW_ON_ERROR);
} finally {
    if ($sharedHostingMode) {
        dropMappedTables($pdo, $tableMap);
    } else {
        // TODO(security): Dynamic SQL is required for ephemeral schema database names.
        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $tempDbName));
    }
}

function runSqlBatch(PDO $pdo, string $sql, array $tableMap = []): void
{
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])) as $statement) {
        if ($statement !== '') {
            $pdo->exec(rewriteSqlWithMap($statement, $tableMap));
        }
    }
}

function buildTableMap(string $schemaSql, string $prefix): array
{
    preg_match_all('/\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $schemaSql, $matches);
    $map = [];

    foreach (array_values(array_unique(array_map('strval', $matches[1] ?? []))) as $name) {
        $map[$name] = $prefix . $name;
    }

    return $map;
}

function rewriteSqlWithMap(string $sql, array $tableMap): string
{
    return sqlab_rewrite_sql_with_map($sql, $tableMap);
}

function dropMappedTables(PDO $pdo, array $tableMap): void
{
    foreach (array_reverse(array_values($tableMap)) as $tableName) {
        try {
            $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $tableName));
        } catch (Throwable) {
        }
    }
}

