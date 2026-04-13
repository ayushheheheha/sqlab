<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: application/json');
Auth::requireAdmin();
$user = Auth::getCurrentUser();
$payload = json_decode(file_get_contents('php://input') ?: '{}', true);

$id = (int) ($payload['id'] ?? 0);
$title = trim((string) ($payload['title'] ?? ''));
$description = trim((string) ($payload['description'] ?? ''));
$difficulty = (string) ($payload['difficulty'] ?? 'easy');
$category = trim((string) ($payload['category'] ?? ''));
$datasetId = (int) ($payload['dataset_id'] ?? 0);
$expectedQuery = trim((string) ($payload['expected_query'] ?? ''));

if ($title === '' || $description === '' || $category === '' || $expectedQuery === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Title, description, category, and expected query are required.']);
    exit;
}

if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid difficulty.']);
    exit;
}

if ($datasetId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Dataset is required.']);
    exit;
}

try {
    $data = [
        'title' => $title,
        'description' => $description,
        'difficulty' => $difficulty,
        'category' => $category,
        'dataset_id' => $datasetId,
        'expected_query' => $expectedQuery,
        'dataset_sql' => trim((string) ($payload['dataset_sql'] ?? '')),
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}
