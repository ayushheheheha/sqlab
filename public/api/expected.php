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
$runner = null;

try {
    if (!$problem) {
        throw new RuntimeException('Problem not found.');
    }

    $subjectSlug = strtolower((string) ($problem['subject_slug'] ?? 'sql'));

    if ($subjectSlug !== 'sql') {
        echo json_encode([
            'success' => true,
            'rows' => [[
                'expected_output' => trim((string) ($problem['expected_query'] ?? '')),
            ]],
            'columns' => ['expected_output'],
            'row_count' => 1,
            'execution_ms' => 0,
            'error' => null,
        ]);
        return;
    }

    $runner = new QueryRunner($problemId);
    $runner->setupSandbox();
    $result = $runner->run((string) $problem['expected_query']);
    echo json_encode($result);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
} finally {
    if ($runner instanceof QueryRunner) {
        $runner->teardown();
    }
}

