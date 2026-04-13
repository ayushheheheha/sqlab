<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

$user = Auth::getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Login required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$query = trim((string) ($payload['query'] ?? ''));

if ($query === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Query is required.']);
    exit;
}

$runner = new PracticeRunner((int) $user['id']);

try {
    $result = $runner->execute($query);

    echo json_encode([
        'success' => (bool) $result['success'],
        'rows' => $result['rows'],
        'columns' => $result['columns'],
        'row_count' => $result['row_count'],
        'execution_ms' => $result['execution_ms'],
        'error' => $result['error'],
        'message' => $result['message'] ?? null,
        'statement_type' => $result['statement_type'] ?? null,
        'affected_rows' => $result['affected_rows'] ?? 0,
        'tables' => $result['tables'] ?? [],
        'target_table' => $result['target_table'] ?? null,
        'table_columns' => $result['table_columns'] ?? [],
        'table_preview' => $result['table_preview'] ?? null,
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $throwable->getMessage()]);
}
