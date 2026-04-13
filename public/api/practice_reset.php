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

$runner = new PracticeRunner((int) $user['id']);

try {
    $runner->resetSandbox();
    echo json_encode(['success' => true, 'message' => 'Practice database reset.']);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $throwable->getMessage()]);
}
