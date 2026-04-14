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

verify_api_csrf_request();

$runner = new PracticeRunner((int) $user['id']);

try {
    $runner->resetSandbox();
    echo json_encode(['success' => true, 'message' => 'Practice database reset.']);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
}
