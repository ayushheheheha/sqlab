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

verify_api_csrf_request();

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$submission = trim((string) ($payload['query'] ?? ''));
$stdin = (string) ($payload['stdin'] ?? '');

if ($submission === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Submission is required.']);
    exit;
}

try {
    $activeSubject = get_active_subject();
    $subjectSlug = strtolower((string) ($activeSubject['slug'] ?? 'sql'));

    if ($subjectSlug === 'sql') {
        $runner = new PracticeRunner((int) $user['id']);
        $result = $runner->execute($submission);

        echo json_encode([
            'success' => (bool) $result['success'],
            'rows' => $result['rows'],
            'columns' => $result['columns'],
            'row_count' => $result['row_count'],
            'execution_ms' => $result['execution_ms'],
            'error' => $result['error'],
            'message' => $result['message'] ?? null,
            'statement_type' => $result['statement_type'] ?? null,
            'affected_rows' => $result['affected_rows'] ?? 0,
            'tables' => $result['tables'] ?? [],
            'target_table' => $result['target_table'] ?? null,
            'table_columns' => $result['table_columns'] ?? [],
            'table_preview' => $result['table_preview'] ?? null,
            'subject_slug' => $subjectSlug,
        ]);

        return;
    }

    $runner = new CodeRunner();
    $result = $runner->run($subjectSlug, $submission, $stdin);

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
        'execution_ms' => (int) ($result['execution_ms'] ?? 0),
        'error' => $result['error'] ?? null,
        'message' => $result['success'] ? 'Execution completed.' : ($result['error'] ?? 'Execution failed.'),
        'statement_type' => strtoupper($subjectSlug),
        'affected_rows' => 0,
        'tables' => [],
        'target_table' => null,
        'table_columns' => [],
        'table_preview' => null,
        'subject_slug' => $subjectSlug,
        'status' => (string) ($result['status'] ?? 'Unknown'),
        'stdout' => (string) ($result['stdout'] ?? ''),
        'stderr' => (string) ($result['stderr'] ?? ''),
        'compile_output' => (string) ($result['compile_output'] ?? ''),
        'display_output' => $displayOutput,
    ]);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
}
