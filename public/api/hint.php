<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

$user = Auth::getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$problemId = (int) ($payload['problem_id'] ?? 0);
$hintLevel = (int) ($payload['hint_level'] ?? 0);

if ($problemId <= 0 || !in_array($hintLevel, [1, 2, 3], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Valid problem_id and hint_level are required.']);
    exit;
}

$problem = Problem::find($problemId);
$hint = trim((string) ($problem['hint' . $hintLevel] ?? ''));

if (!$problem || $hint === '') {
    http_response_code(404);
    echo json_encode(['error' => 'Hint not found.']);
    exit;
}

Submission::incrementHint((int) $user['id'], $problemId);

echo json_encode(['hint' => $hint]);

