<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$subjectFromQuery = trim((string) ($_GET['subject'] ?? ''));

if ($subjectFromQuery !== '') {
    set_active_subject_slug($subjectFromQuery);
}

$activeSubject = get_active_subject();
$subjectId = (int) ($activeSubject['id'] ?? 0);
$problems = Problem::allActive($subjectId > 0 ? $subjectId : null);
$totalProblems = count($problems);
$progressSql = 'SELECT up.problem_id, up.status
                FROM user_progress up
                INNER JOIN problems p ON p.id = up.problem_id
                WHERE up.user_id = :user_id';
$progressParams = ['user_id' => (int) $user['id']];

if (Subject::isReady() && $subjectId > 0) {
    $progressSql .= ' AND p.subject_id = :subject_id';
    $progressParams['subject_id'] = $subjectId;
}

$progressStmt = DB::getConnection()->prepare($progressSql);
$progressStmt->execute($progressParams);
$progress = [];

foreach ($progressStmt->fetchAll() as $row) {
    $progress[(int) $row['problem_id']] = $row['status'];
}
$solvedCount = count(array_filter($progress, static fn (string $status): bool => $status === 'solved'));
$categories = array_values(array_unique(array_map(static fn (array $problem): string => (string) $problem['category'], $problems)));
sort($categories);

render_app_layout($activeSubject['name'] . ' Problems', $user, static function () use ($problems, $progress, $solvedCount, $totalProblems, $categories, $activeSubject): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Problems</h1>
            <p class="page-subtitle"><?= $solvedCount ?>/<?= $totalProblems ?> solved in <?= e($activeSubject['name']) ?>. Pick a challenge and keep upskilling.</p>
        </div>
    </section>

    <section class="problem-filter-bar">
        <input id="problemSearch" type="search" placeholder="Search by title...">
        <div class="audience-toggle" data-problem-filter="difficulty">
            <?php foreach (['all' => 'All', 'easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $value => $label): ?>
                <button class="btn-ghost <?= $value === 'all' ? 'active' : '' ?>" type="button" data-value="<?= e($value) ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>
        <select id="problemCategory">
            <option value="all">All categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e($category) ?>"><?= e($category) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="audience-toggle" data-problem-filter="status">
            <?php foreach (['all' => 'All', 'solved' => 'Solved', 'unsolved' => 'Unsolved'] as $value => $label): ?>
                <button class="btn-ghost <?= $value === 'all' ? 'active' : '' ?>" type="button" data-value="<?= e($value) ?>"><?= e($label) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="grid grid-2 problem-grid" id="problemGrid">
        <?php foreach ($problems as $problem): ?>
            <?php
            $status = ($progress[(int) $problem['id']] ?? '') === 'solved' ? 'solved' : 'unsolved';
            $xp = ['easy' => 10, 'medium' => 20, 'hard' => 40][$problem['difficulty']] ?? 10;
            $badge = $problem['difficulty'] === 'easy' ? 'success' : ($problem['difficulty'] === 'medium' ? 'warning' : 'danger');
            $description = mb_strlen($problem['description']) > 100 ? mb_substr($problem['description'], 0, 100) . '...' : $problem['description'];
            ?>
            <a class="card problem-card"
               href="<?= e(app_url('solve.php?id=' . (int) $problem['id'])) ?>"
               data-title="<?= e(strtolower($problem['title'])) ?>"
               data-difficulty="<?= e($problem['difficulty']) ?>"
               data-category="<?= e($problem['category']) ?>"
               data-status="<?= e($status) ?>">
                <div class="problem-card-head">
                    <strong><?= e($problem['title']) ?></strong>
                    <span class="problem-status-icon"><?= $status === 'solved' ? '✓' : '–' ?></span>
                </div>
                <div class="problem-card-badges">
                    <span class="badge badge-muted"><?= e($problem['category']) ?></span>
                    <span class="badge badge-<?= e($badge) ?>"><?= e(ucfirst($problem['difficulty'])) ?></span>
                </div>
                <p class="muted"><?= e($description) ?></p>
                <div class="problem-card-foot">
                    <span><?= $xp ?> XP</span>
                    <span><?= $status === 'solved' ? 'Solved' : 'Unsolved' ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
    <div class="card empty-state" id="problemEmptyState" hidden>No problems match your filters.</div>
    <?php
});
