<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
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
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account from this screen.']);
    exit;
}

try {
    User::deleteById($userId);
    echo json_encode(['success' => true, 'message' => 'User deleted.']);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}
