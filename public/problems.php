<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$problems = Problem::allActive();
$totalProblems = count($problems);
$progressStmt = DB::getConnection()->prepare('SELECT problem_id, status FROM user_progress WHERE user_id = :user_id');
$progressStmt->execute(['user_id' => (int) $user['id']]);
$progress = [];

foreach ($progressStmt->fetchAll() as $row) {
    $progress[(int) $row['problem_id']] = $row['status'];
}
$solvedCount = count(array_filter($progress, static fn (string $status): bool => $status === 'solved'));
$categories = array_values(array_unique(array_map(static fn (array $problem): string => (string) $problem['category'], $problems)));
sort($categories);

render_app_layout('Problems', $user, static function () use ($problems, $progress, $solvedCount, $totalProblems, $categories): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Problems</h1>
            <p class="page-subtitle"><?= $solvedCount ?>/<?= $totalProblems ?> solved. Pick a challenge and validate your SQL against real sample data.</p>
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
