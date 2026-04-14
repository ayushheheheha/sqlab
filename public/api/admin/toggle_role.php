<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
verify_api_csrf_request();
$currentUser = Auth::getCurrentUser();

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$userId = (int) ($payload['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
    exit;
}

if ((int) ($currentUser['id'] ?? 0) === $userId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'You cannot change your own role from this screen.']);
    exit;
}

try {
    User::toggleRole($userId);
    echo json_encode(['success' => true, 'message' => 'Role updated.']);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
}
