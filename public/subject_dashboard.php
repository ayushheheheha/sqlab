<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

if (isset($_GET['subject'])) {
    set_active_subject_slug((string) $_GET['subject']);
}

$subject = get_active_subject();
$subjectId = (int) ($subject['id'] ?? 0);

$stats = User::stats((int) $user['id']);
$recent = Submission::recentForUser((int) $user['id'], 5, $subjectId > 0 ? $subjectId : null);
$badges = User::allBadgesForUser((int) $user['id']);
$pdo = DB::getConnection();

if (Subject::isReady() && $subjectId > 0) {
    $totalStmt = $pdo->prepare('SELECT COUNT(*) FROM problems WHERE is_active = 1 AND subject_id = :subject_id');
    $totalStmt->execute(['subject_id' => $subjectId]);
    $totalProblems = (int) $totalStmt->fetchColumn();

    $solvedStmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT up.problem_id)
         FROM user_progress up
         INNER JOIN problems p ON p.id = up.problem_id
         WHERE up.user_id = :user_id AND up.status = "solved" AND p.subject_id = :subject_id'
    );
    $solvedStmt->execute(['user_id' => (int) $user['id'], 'subject_id' => $subjectId]);
    $stats['solved_count'] = (int) $solvedStmt->fetchColumn();
} else {
    $totalProblems = (int) $pdo->query('SELECT COUNT(*) FROM problems WHERE is_active = 1')->fetchColumn();
}

$level = intdiv((int) $user['xp'], 100);
$xpProgress = (int) $user['xp'] % 100;

$categorySql = 'SELECT p.category, COUNT(*) AS solved_count
                FROM user_progress up
                INNER JOIN problems p ON p.id = up.problem_id
                WHERE up.user_id = :user_id AND up.status = "solved"';
$categoryParams = ['user_id' => (int) $user['id']];

if (Subject::isReady() && $subjectId > 0) {
    $categorySql .= ' AND p.subject_id = :subject_id';
    $categoryParams['subject_id'] = $subjectId;
}

$categorySql .= ' GROUP BY p.category ORDER BY solved_count DESC LIMIT 1';
$categoryStmt = $pdo->prepare($categorySql);
$categoryStmt->execute($categoryParams);
$commonCategory = $categoryStmt->fetchColumn();

$recommendedSql = 'SELECT p.*
                   FROM problems p
                   LEFT JOIN user_progress up ON up.problem_id = p.id AND up.user_id = :user_id
                   WHERE p.is_active = 1
                     AND (up.status IS NULL OR up.status != "solved")
                     AND (:category_filter = "" OR p.category = :category_match)';
$recommendedParams = [
    'user_id' => (int) $user['id'],
    'category_filter' => (string) ($commonCategory ?: ''),
    'category_match' => (string) ($commonCategory ?: ''),
];

if (Subject::isReady() && $subjectId > 0) {
    $recommendedSql .= ' AND p.subject_id = :subject_id';
    $recommendedParams['subject_id'] = $subjectId;
}

$recommendedSql .= ' ORDER BY FIELD(p.difficulty, "easy", "medium", "hard"), p.id LIMIT 3';
$recommendedStmt = $pdo->prepare($recommendedSql);
$recommendedStmt->execute($recommendedParams);
$recommended = $recommendedStmt->fetchAll();

if (!$recommended) {
    $fallbackSql = 'SELECT p.*
                    FROM problems p
                    LEFT JOIN user_progress up ON up.problem_id = p.id AND up.user_id = :user_id
                    WHERE p.is_active = 1
                      AND (up.status IS NULL OR up.status != "solved")';
    $fallbackParams = ['user_id' => (int) $user['id']];

    if (Subject::isReady() && $subjectId > 0) {
        $fallbackSql .= ' AND p.subject_id = :subject_id';
        $fallbackParams['subject_id'] = $subjectId;
    }

    $fallbackSql .= ' ORDER BY FIELD(p.difficulty, "easy", "medium", "hard"), p.id LIMIT 3';
    $fallbackStmt = $pdo->prepare($fallbackSql);
    $fallbackStmt->execute($fallbackParams);
    $recommended = $fallbackStmt->fetchAll();
}

render_app_layout($subject['name'] . ' Dashboard', $user, static function () use ($user, $stats, $recent, $badges, $xpProgress, $level, $totalProblems, $recommended, $subject): void {
    ?>
    <section class="page-header dashboard-welcome">
        <div>
            <h1><?= e($subject['name']) ?> Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?= e($user['username']) ?> · <?= e(date('l, F j, Y')) ?></p>
        </div>
        <?php if ((int) $user['streak'] > 0): ?>
            <span class="badge badge-warning"><?= (int) $user['streak'] ?> day streak</span>
        <?php endif; ?>
    </section>

    <section class="grid grid-3">
        <article class="card">
            <p class="stat-label">Total XP</p>
            <div class="stat-value"><?= (int) $user['xp'] ?></div>
        </article>
        <article class="card">
            <p class="stat-label">Problems Solved</p>
            <div class="stat-value"><?= (int) $stats['solved_count'] ?>/<?= $totalProblems ?></div>
        </article>
        <article class="card">
            <p class="stat-label">Current Streak</p>
            <div class="stat-value"><?= (int) $user['streak'] ?> days</div>
        </article>
    </section>

    <section class="card xp-progress-card">
        <strong>Level <?= $level ?> — <?= (int) $user['xp'] ?> XP</strong>
        <p class="muted"><?= $xpProgress ?>/100 XP to next level</p>
        <div class="xp-bar-track"><div class="xp-bar-fill" style="width: <?= $xpProgress ?>%"></div></div>
    </section>

    <section class="grid grid-2" style="margin-top:16px;">
        <article class="card">
            <h2 style="margin-bottom:16px;">Last 5 Submissions</h2>
            <div class="table-shell">
                <table>
                    <thead>
                    <tr>
                        <th>Problem</th>
                        <th>Result</th>
                        <th>Timestamp</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $item): ?>
                        <tr>
                            <td><?= e($item['title']) ?></td>
                            <td><span class="badge badge-<?= (int) $item['is_correct'] === 1 ? 'success' : 'danger' ?>"><?= (int) $item['is_correct'] === 1 ? 'Correct' : 'Wrong' ?></span></td>
                            <td><?= e(date('M j, H:i', strtotime($item['submitted_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="3">No submissions yet for <?= e($subject['name']) ?>.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="card">
            <h2 style="margin-bottom:16px;">Badges Earned</h2>
            <div class="badge-list">
                <?php foreach ($badges as $badge): ?>
                    <div class="badge-tile <?= empty($badge['earned_at']) ? 'locked' : '' ?>">
                        <div style="width:32px; margin-bottom:8px;"><?= $badge['icon_svg'] ?></div>
                        <strong><?= e($badge['name']) ?></strong>
                        <p class="muted"><?= empty($badge['earned_at']) ? 'Locked' : e(date('M j', strtotime($badge['earned_at']))) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section class="card recommended-card">
        <h2 style="margin-bottom:16px;">Recommended Problems</h2>
        <div class="grid grid-3">
            <?php foreach ($recommended as $problem): ?>
                <article class="mini-problem-card">
                    <strong><?= e($problem['title']) ?></strong>
                    <p class="muted"><?= e($problem['category']) ?> · <?= e(ucfirst($problem['difficulty'])) ?></p>
                    <a class="btn-ghost" href="<?= e(app_url('solve.php?id=' . (int) $problem['id'])) ?>">Solve</a>
                </article>
            <?php endforeach; ?>
            <?php if (!$recommended): ?>
                <p class="muted">No active <?= e($subject['name']) ?> problems yet.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php
});
