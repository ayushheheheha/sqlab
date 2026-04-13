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
// TODO(security): Dynamic SQL is required for ephemeral schema database names.
$pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s_unicode_ci', $tempDbName, $charset, $charset));

try {
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

    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schemaSql) ?: [])) as $statement) {
        if ($statement !== '') {
            $schemaDb->exec($statement);
        }
    }

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
    $rows = $columnStmt->fetchAll();

    $tables = [];

    foreach ($rows as $row) {
        $name = (string) $row['table_name'];

        if (!isset($tables[$name])) {
            $tables[$name] = ['name' => $name, 'columns' => []];
        }

        $tables[$name]['columns'][] = [
            'name' => (string) $row['column_name'],
            'type' => (string) $row['column_type'],
            'is_pk' => (int) $row['is_pk'] === 1,
            'fk_table' => $row['fk_table'] ? (string) $row['fk_table'] : null,
            'fk_column' => $row['fk_column'] ? (string) $row['fk_column'] : null,
        ];
    }

    echo json_encode(['tables' => array_values($tables)], JSON_THROW_ON_ERROR);
} finally {
    // TODO(security): Dynamic SQL is required for ephemeral schema database names.
    $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $tempDbName));
}

