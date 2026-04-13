<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);

$id = (int) ($payload['id'] ?? 0);
$name = trim((string) ($payload['name'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));
$schemaSql = trim((string) ($payload['schema_sql'] ?? ''));
$seedSql = trim((string) ($payload['seed_sql'] ?? ''));

if ($name === '' || $description === '' || $schemaSql === '' || $seedSql === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'All dataset fields are required.']);
    exit;
}

if (!preg_match('/\bCREATE\s+TABLE\b/i', $schemaSql)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Schema SQL must include at least one CREATE TABLE statement.']);
    exit;
}

try {
    $datasetId = Dataset::save([
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'schema_sql' => $schemaSql,
        'seed_sql' => $seedSql,
    ]);

    echo json_encode([
        'success' => true,
        'dataset_id' => $datasetId,
        'message' => $id > 0 ? 'Dataset updated.' : 'Dataset created.',
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}
