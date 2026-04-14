<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
verify_api_csrf_request();

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$datasetId = (int) ($payload['dataset_id'] ?? 0);

if ($datasetId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid dataset id.']);
    exit;
}

try {
    Dataset::delete($datasetId);
    echo json_encode(['success' => true, 'message' => 'Dataset deleted.']);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
}
