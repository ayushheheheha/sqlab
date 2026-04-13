<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!Auth::getCurrentUser()) {
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

$problem = Problem::find($problemId);
$runner = new QueryRunner($problemId);

try {
    if (!$problem) {
        throw new RuntimeException('Problem not found.');
    }

    $runner->setupSandbox();
    $result = $runner->run((string) $problem['expected_query']);
    echo json_encode($result);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $throwable->getMessage()]);
} finally {
    $runner->teardown();
}

