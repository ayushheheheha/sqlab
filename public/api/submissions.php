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

$problemId = (int) ($_GET['problem_id'] ?? 0);

if ($problemId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Valid problem_id is required.']);
    exit;
}

echo json_encode([
    'success' => true,
    'submissions' => Submission::recentForUserProblem((int) $user['id'], $problemId, 5),
]);

