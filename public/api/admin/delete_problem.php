<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
verify_api_csrf_request();

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$problemId = (int) ($payload['problem_id'] ?? 0);
$hardDelete = isset($_GET['hard']) && $_GET['hard'] === '1';

if ($problemId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid problem id.']);
    exit;
}

try {
    if ($hardDelete) {
        Problem::hardDelete($problemId);
        echo json_encode(['success' => true, 'message' => 'Problem deleted permanently.']);
        exit;
    }

    Problem::softDelete($problemId);
    echo json_encode(['success' => true, 'message' => 'Problem deactivated.']);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
}
