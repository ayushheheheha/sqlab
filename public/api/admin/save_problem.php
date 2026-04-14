<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
verify_api_csrf_request();
$user = Auth::getCurrentUser();
$payload = json_decode(file_get_contents('php://input') ?: '{}', true);

$id = (int) ($payload['id'] ?? 0);
$title = trim((string) ($payload['title'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));
$difficulty = (string) ($payload['difficulty'] ?? 'easy');
$category = trim((string) ($payload['category'] ?? ''));
$subjectId = (int) ($payload['subject_id'] ?? 0);
$datasetId = (int) ($payload['dataset_id'] ?? 0);
$expectedQuery = trim((string) ($payload['expected_query'] ?? ''));

if ($title === '' || $description === '' || $category === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Title, description, and category are required.']);
    exit;
}

if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid difficulty.']);
    exit;
}

if ($subjectId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Subject is required.']);
    exit;
}

$subject = Subject::findById($subjectId);
$subjectSlug = strtolower((string) ($subject['slug'] ?? 'sql'));
$isSqlSubject = $subjectSlug === 'sql';

if ($expectedQuery === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $isSqlSubject ? 'Expected query is required.' : 'Expected output is required.']);
    exit;
}

if ($isSqlSubject && $datasetId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Dataset is required for SQL problems.']);
    exit;
}

if (!$isSqlSubject) {
    $testCases = parse_non_sql_test_cases($expectedQuery);

    if ($testCases === []) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'At least one valid test case is required. Use: input || expected_output']);
        exit;
    }

    $expectedQuery = json_encode($testCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $datasetId = 0;
}

try {
    $data = [
        'title' => $title,
        'description' => $description,
        'difficulty' => $difficulty,
        'category' => $category,
        'subject_id' => $subjectId,
        'dataset_id' => $datasetId,
        'expected_query' => $expectedQuery,
        'dataset_sql' => $isSqlSubject ? trim((string) ($payload['dataset_sql'] ?? '')) : '',
        'hint1' => trim((string) ($payload['hint1'] ?? '')),
        'hint2' => trim((string) ($payload['hint2'] ?? '')),
        'hint3' => trim((string) ($payload['hint3'] ?? '')),
        'is_active' => !empty($payload['is_active']) ? 1 : 0,
        'created_by' => (int) ($user['id'] ?? 0),
    ];

    if ($id > 0) {
        Problem::update($id, $data);
        echo json_encode(['success' => true, 'problem_id' => $id, 'message' => 'Problem updated.']);
        exit;
    }

    $problemId = Problem::create($data);
    echo json_encode(['success' => true, 'problem_id' => $problemId, 'message' => 'Problem created.']);
} catch (Throwable $throwable) {
    json_internal_error($throwable);
}

function parse_non_sql_test_cases(string $raw): array
{
    $raw = trim($raw);

    if ($raw === '') {
        return [];
    }

    // Accept JSON array for edits, otherwise parse line format: input || expected_output
    if (str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $cases = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $expected = trim((string) ($row['expected_output'] ?? ''));
                if ($expected === '') {
                    continue;
                }
                $cases[] = [
                    'input' => (string) ($row['input'] ?? ''),
                    'expected_output' => $expected,
                ];
            }
            return $cases;
        }
    }

    $cases = [];
    $lines = preg_split('/\r?\n/', $raw) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('||', $line, 2));
        $input = '';
        $expected = '';

        if (count($parts) === 2) {
            [$input, $expected] = $parts;
        } else {
            $expected = $parts[0] ?? '';
        }

        if ($expected === '') {
            continue;
        }

        $cases[] = [
            'input' => $input,
            'expected_output' => $expected,
        ];
    }

    return $cases;
}
