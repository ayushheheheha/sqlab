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
$problemId = (int) ($payload['problem_id'] ?? 0);
$query = trim((string) ($payload['query'] ?? ''));

if ($problemId <= 0 || $query === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Problem and query are required.']);
    exit;
}

$runner = new QueryRunner($problemId);

try {
    $runner->setupSandbox();
    $result = $runner->run($query);
    $isCorrect = false;
    $progress = ['first_correct_solve' => false, 'xp_awarded' => 0, 'badges' => []];

    if ($result['success']) {
        $isCorrect = $runner->checkCorrectness($result['rows']);
    }

    $progress = Submission::saveExecution(
        (int) $user['id'],
        $problemId,
        $query,
        $isCorrect,
        (int) $result['execution_ms'],
        (int) $result['row_count']
    );

    echo json_encode([
        'success' => (bool) $result['success'],
        'rows' => $result['rows'],
        'columns' => $result['columns'],
        'row_count' => $result['row_count'],
        'execution_ms' => $result['execution_ms'],
        'error' => $result['error'],
        'is_correct' => $isCorrect,
        'xp_awarded' => $progress['xp_awarded'],
        'badges' => $progress['badges'],
        'first_correct_solve' => $progress['first_correct_solve'],
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $throwable->getMessage()]);
} finally {
    $runner->teardown();
}

