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
$submission = trim((string) ($payload['query'] ?? ''));
$stdin = (string) ($payload['stdin'] ?? '');
$testCases = is_array($payload['test_cases'] ?? null) ? $payload['test_cases'] : [];

if ($problemId <= 0 || $submission === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Problem and submission are required.']);
    exit;
}

$sqlRunner = null;

try {
    $problem = Problem::find($problemId);

    if (!$problem || (int) ($problem['is_active'] ?? 0) !== 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Problem not found.']);
        exit;
    }

    $subjectSlug = strtolower((string) ($problem['subject_slug'] ?? 'sql'));
    $isCorrect = false;
    $progress = ['first_correct_solve' => false, 'xp_awarded' => 0, 'badges' => []];

    if ($subjectSlug === 'sql') {
        $sqlRunner = new QueryRunner($problemId);
        $sqlRunner->setupSandbox();
        $result = $sqlRunner->run($submission);

        if ($result['success']) {
            $isCorrect = $sqlRunner->checkCorrectness($result['rows']);
        }

        $progress = Submission::saveExecution(
            (int) $user['id'],
            $problemId,
            $submission,
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
            'status' => 'Accepted',
            'subject_slug' => $subjectSlug,
        ]);

        return;
    }

    $runner = new CodeRunner();
    $hasSampleCases = false;
    $caseResults = [];
    $result = null;

    if ($testCases) {
        foreach ($testCases as $case) {
            if (!is_array($case) || !array_key_exists('input', $case) || !array_key_exists('expected_output', $case)) {
                continue;
            }

            $hasSampleCases = true;
            $caseInput = (string) $case['input'];
            $expectedOutput = trim((string) $case['expected_output']);
            $caseRun = $runner->run($subjectSlug, $submission, $caseInput);
            $actualOutput = trim((string) ($caseRun['stdout'] ?? ''));
            $passed = (bool) $caseRun['success'] && $actualOutput === $expectedOutput;

            $caseResults[] = [
                'input' => $caseInput,
                'expected_output' => $expectedOutput,
                'actual_output' => $actualOutput,
                'passed' => $passed,
                'status' => (string) ($caseRun['status'] ?? 'Unknown'),
                'error' => $caseRun['error'] ?? null,
                'execution_ms' => (int) ($caseRun['execution_ms'] ?? 0),
            ];

            if (!$passed && $result === null) {
                $result = $caseRun;
            }
        }

        if ($hasSampleCases) {
            $allPassed = $caseResults !== [] && !array_filter($caseResults, static fn (array $row): bool => empty($row['passed']));
            $isCorrect = $allPassed;

            if ($result === null) {
                $result = [
                    'success' => true,
                    'status' => 'Accepted',
                    'stdout' => (string) ($caseResults[count($caseResults) - 1]['actual_output'] ?? ''),
                    'stderr' => '',
                    'compile_output' => '',
                    'message' => '',
                    'execution_ms' => array_sum(array_map(static fn (array $row): int => (int) ($row['execution_ms'] ?? 0), $caseResults)),
                    'memory_kb' => 0,
                    'error' => null,
                ];
            }
        }
    }

    if (!$hasSampleCases) {
        $result = $runner->run($subjectSlug, $submission, $stdin);

        if ($result['success']) {
            $expected = trim((string) ($problem['expected_query'] ?? ''));
            $actual = trim((string) ($result['stdout'] ?? ''));
            $isCorrect = ($expected === '') ? true : ($actual === $expected);
        }
    }

    $rowCount = $hasSampleCases
        ? count($caseResults)
        : (trim((string) ($result['stdout'] ?? '')) === '' ? 0 : count(preg_split('/\r?\n/', trim((string) $result['stdout'])) ?: []));

    $progress = Submission::saveExecution(
        (int) $user['id'],
        $problemId,
        $submission,
        $isCorrect,
        (int) $result['execution_ms'],
        $rowCount
    );

    $displayOutput = trim((string) ($result['stdout'] ?? ''));
    if ($displayOutput === '') {
        $displayOutput = trim((string) ($result['compile_output'] ?? ''));
    }
    if ($displayOutput === '') {
        $displayOutput = trim((string) ($result['stderr'] ?? ''));
    }

    echo json_encode([
        'success' => (bool) $result['success'],
        'rows' => [],
        'columns' => [],
        'row_count' => 0,
        'execution_ms' => $result['execution_ms'],
        'error' => $result['error'],
        'status' => $result['status'] ?? 'Unknown',
        'stdout' => $result['stdout'] ?? '',
        'stderr' => $result['stderr'] ?? '',
        'compile_output' => $result['compile_output'] ?? '',
        'display_output' => $displayOutput,
        'case_results' => $caseResults,
        'memory_kb' => (int) ($result['memory_kb'] ?? 0),
        'is_correct' => $isCorrect,
        'xp_awarded' => $progress['xp_awarded'],
        'badges' => $progress['badges'],
        'first_correct_solve' => $progress['first_correct_solve'],
        'subject_slug' => $subjectSlug,
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $throwable->getMessage()]);
} finally {
    if ($sqlRunner instanceof QueryRunner) {
        $sqlRunner->teardown();
    }
}

