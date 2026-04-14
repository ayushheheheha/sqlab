<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
$user = Auth::getCurrentUser();
verify_api_csrf_request();

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$datasetId = (int) ($payload['dataset_id'] ?? 0);
$query = trim((string) ($payload['query'] ?? ''));
$query = preg_replace('/;\s*$/', '', $query) ?? $query;

if ($datasetId <= 0 || $query === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Dataset and query are required.']);
    exit;
}

if (str_contains($query, ';') || !preg_match('/^\s*(SELECT|WITH)\b/i', $query) || preg_match('/\b(DROP|CREATE|INSERT|UPDATE|DELETE|ALTER|TRUNCATE)\b/i', $query)) {
    echo json_encode([
        'success' => false,
        'rows' => [],
        'columns' => [],
        'row_count' => 0,
        'execution_ms' => 0,
        'error' => 'Only SELECT queries are allowed.',
    ]);
    exit;
}

$dataset = Dataset::find($datasetId);

if (!$dataset) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Dataset not found.']);
    exit;
}

$sharedHostingMode = strtolower((string) ($_ENV['SHARED_HOSTING_MODE'] ?? 'false')) === 'true';
$tempDbName = sprintf('sqlab_admin_%d_%s', (int) ($user['id'] ?? 0), str_replace('.', '', uniqid('', true)));
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
$tableMap = [];

try {
    $rootPdo = DB::getConnection();
    if ($sharedHostingMode) {
        $prefix = sprintf('sqlab_admin_%d_%s_', (int) ($user['id'] ?? 0), str_replace('.', '', uniqid('', true)));
        $tableMap = buildTableMap((string) $dataset['schema_sql'], $prefix);
        runSqlBatch($rootPdo, (string) $dataset['schema_sql'], $tableMap);
        runSqlBatch($rootPdo, (string) $dataset['seed_sql'], $tableMap);
        $sandbox = $rootPdo;
    } else {
        $rootPdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET %s COLLATE %s_unicode_ci', $tempDbName, $charset, $charset));
        $rootPdo->exec(sprintf(
            "GRANT SELECT ON `%s`.* TO '%s'@'localhost'",
            $tempDbName,
            str_replace("'", "''", $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox')
        ));

        $sandboxRoot = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_PORT'] ?? '3306', $tempDbName, $charset),
            $_ENV['DB_USER'] ?? '',
            $_ENV['DB_PASS'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        runSqlBatch($sandboxRoot, (string) $dataset['schema_sql']);
        runSqlBatch($sandboxRoot, (string) $dataset['seed_sql']);

        $sandbox = DB::sandboxConnection($tempDbName);
    }

    try {
        $sandbox->exec('SET SESSION MAX_EXECUTION_TIME=3000');
    } catch (Throwable) {
        try {
            $sandbox->exec('SET SESSION max_statement_time=3');
        } catch (Throwable) {
        }
    }

    $started = microtime(true);
    $stmt = $sandbox->query(rewriteSqlWithMap($query, $tableMap));
    $rows = $stmt->fetchAll();
    $elapsed = (int) round((microtime(true) - $started) * 1000);
    $columns = [];

    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $columns[] = (string) ($meta['name'] ?? $i);
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'columns' => $columns ?: array_keys($rows[0] ?? []),
        'row_count' => count($rows),
        'execution_ms' => $elapsed,
        'error' => null,
    ]);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
} finally {
    if ($sharedHostingMode) {
        dropMappedTables(DB::getConnection(), $tableMap);
        return;
    }

    try {
        DB::getConnection()->exec(sprintf(
            "REVOKE SELECT ON `%s`.* FROM '%s'@'localhost'",
            $tempDbName,
            str_replace("'", "''", $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox')
        ));
    } catch (Throwable) {
    }

    try {
        DB::getConnection()->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $tempDbName));
    } catch (Throwable) {
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
