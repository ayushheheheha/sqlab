<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

header('Content-Type: application/json');

if (!Auth::getCurrentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required.']);
    exit;
}

$period = strtolower((string) ($_GET['period'] ?? 'alltime'));
$pdo = DB::getConnection();

if (!in_array($period, ['alltime', 'week', 'month'], true)) {
    $period = 'alltime';
}

if ($period === 'alltime') {
    $stmt = $pdo->query(
        'SELECT
            u.id,
            u.username,
            u.xp,
            u.streak,
            COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.problem_id END) AS solved,
            COUNT(DISTINCT ub.badge_id) AS badge_count
         FROM users u
         LEFT JOIN user_progress up ON up.user_id = u.id
         LEFT JOIN user_badges ub ON ub.user_id = u.id
         WHERE u.role = "student"
         GROUP BY u.id
         ORDER BY u.xp DESC, solved DESC, u.username ASC
         LIMIT 50'
    );
    $rows = $stmt->fetchAll();
} else {
    $days = $period === 'week' ? 7 : 30;
    $stmt = $pdo->query(
        'SELECT
            u.id,
            u.username,
            u.xp,
            u.streak,
            SUM(CASE WHEN s.is_correct = 1 THEN 1 ELSE 0 END) AS solved,
            COUNT(DISTINCT ub.badge_id) AS badge_count
         FROM users u
         LEFT JOIN submissions s
            ON s.user_id = u.id
           AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
         LEFT JOIN user_badges ub ON ub.user_id = u.id
         WHERE u.role = "student"
         GROUP BY u.id
         ORDER BY solved DESC, u.xp DESC, u.username ASC
         LIMIT 50'
    );
    $rows = $stmt->fetchAll();
}

$ranked = [];

foreach ($rows as $index => $row) {
    $ranked[] = [
        'rank' => $index + 1,
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'solved' => (int) $row['solved'],
        'xp' => (int) $row['xp'],
        'streak' => (int) $row['streak'],
        'badge_count' => (int) $row['badge_count'],
    ];
}

echo json_encode($ranked, JSON_THROW_ON_ERROR);
