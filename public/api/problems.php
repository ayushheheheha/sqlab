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

$difficulty = strtolower((string) ($_GET['difficulty'] ?? 'all'));
$category = (string) ($_GET['category'] ?? 'all');
$status = strtolower((string) ($_GET['status'] ?? 'all'));
$problems = Problem::allActive();
$progressStmt = DB::getConnection()->prepare('SELECT problem_id, status FROM user_progress WHERE user_id = :user_id');
$progressStmt->execute(['user_id' => (int) $user['id']]);
$progress = [];

foreach ($progressStmt->fetchAll() as $row) {
    $progress[(int) $row['problem_id']] = $row['status'];
}

$filtered = array_values(array_filter($problems, static function (array $problem) use ($difficulty, $category, $status, $progress): bool {
    $problemStatus = ($progress[(int) $problem['id']] ?? '') === 'solved' ? 'solved' : 'unsolved';

    return ($difficulty === 'all' || $problem['difficulty'] === $difficulty)
        && ($category === 'all' || $problem['category'] === $category)
        && ($status === 'all' || $problemStatus === $status);
}));

echo json_encode([
    'success' => true,
    'count' => count($filtered),
    'problems' => $filtered,
]);
