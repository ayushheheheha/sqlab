<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$profileId = (int) ($_GET['id'] ?? $user['id']);
$profileUser = User::findById($profileId) ?? $user;
$stats = User::stats((int) $profileUser['id']);
$badges = User::allBadgesForUser((int) $profileUser['id']);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$totalSubmissions = Submission::countForUser((int) $profileUser['id']);
$totalPages = max(1, (int) ceil($totalSubmissions / $perPage));
$page = min($page, $totalPages);
$recent = Submission::paginatedForUser((int) $profileUser['id'], $page, $perPage);
$level = intdiv((int) $profileUser['xp'], 100);
$xpProgress = (int) $profileUser['xp'] % 100;
$initials = strtoupper(substr((string) $profileUser['username'], 0, 2));

render_app_layout('Profile', $user, static function () use ($profileUser, $stats, $badges, $recent, $page, $totalPages, $xpProgress, $level, $initials): void {
    ?>
    <section class="page-header profile-hero">
        <div class="avatar-initials"><?= e($initials) ?></div>
        <div>
            <h1><?= e($profileUser['username']) ?></h1>
            <p class="page-subtitle">Joined <?= e(date('M j, Y', strtotime($profileUser['created_at']))) ?> · <span class="badge badge-muted"><?= e(ucfirst($profileUser['role'])) ?></span></p>
        </div>
    </section>
    <section class="grid grid-3">
        <article class="card"><p class="stat-label">XP</p><div class="stat-value"><?= (int) $profileUser['xp'] ?></div></article>
        <article class="card"><p class="stat-label">Solved</p><div class="stat-value"><?= (int) $stats['solved_count'] ?></div></article>
        <article class="card"><p class="stat-label">Streak</p><div class="stat-value"><?= (int) $profileUser['streak'] ?></div></article>
    </section>

    <section class="card xp-progress-card">
        <strong>Level <?= $level ?> — <?= (int) $profileUser['xp'] ?> XP</strong>
        <p class="muted"><?= $xpProgress ?>/100 XP to next level</p>
        <div class="xp-bar-track"><div class="xp-bar-fill" style="width: <?= $xpProgress ?>%"></div></div>
    </section>

    <section class="card">
        <h2 style="margin-bottom:16px;">Badges</h2>
        <div class="badge-list badge-row">
            <?php foreach ($badges as $badge): ?>
                <div class="badge-tile <?= empty($badge['earned_at']) ? 'locked' : '' ?>">
                    <div style="width:38px; margin:0 auto 8px;"><?= $badge['icon_svg'] ?></div>
                    <strong><?= e($badge['name']) ?></strong>
                    <p class="muted"><?= empty($badge['earned_at']) ? 'Locked' : 'Earned' ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2 style="margin-bottom:16px;">Submission History</h2>
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>Problem</th>
                    <th>Difficulty</th>
                    <th>Result</th>
                    <th>Execution Time</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $item): ?>
                    <tr>
                        <td><?= e($item['title']) ?></td>
                        <td><span class="badge badge-<?= $item['difficulty'] === 'easy' ? 'success' : ($item['difficulty'] === 'medium' ? 'warning' : 'danger') ?>"><?= e(ucfirst($item['difficulty'])) ?></span></td>
                        <td><span class="badge badge-<?= (int) $item['is_correct'] === 1 ? 'success' : 'danger' ?>"><?= (int) $item['is_correct'] === 1 ? 'Correct' : 'Wrong' ?></span></td>
                        <td><?= (int) $item['execution_time_ms'] ?> ms</td>
                        <td><?= e(date('M j, Y H:i', strtotime($item['submitted_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                    <tr><td colspan="5">Nothing here yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn-ghost" href="<?= e(app_url('profile.php?id=' . (int) $profileUser['id'] . '&page=' . ($page - 1))) ?>">Previous</a>
            <?php endif; ?>
            <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a class="btn-ghost" href="<?= e(app_url('profile.php?id=' . (int) $profileUser['id'] . '&page=' . ($page + 1))) ?>">Next</a>
            <?php endif; ?>
        </nav>
    </section>
    <?php
});
exit;
$stats = User::stats((int) $user['id']);
$badges = User::badges((int) $user['id']);
$recent = Submission::recentForUser((int) $user['id'], 20);

render_app_layout('Profile', $user, static function () use ($user, $stats, $badges, $recent): void {
    ?>
    <section class="page-header">
        <div>
            <h1><?= e($user['username']) ?></h1>
            <p class="page-subtitle"><?= e($user['email']) ?> • Joined <?= e(date('M j, Y', strtotime($user['created_at']))) ?></p>
        </div>
    </section>
    <section class="grid grid-2">
        <article class="card"><p class="stat-label">Role</p><div class="stat-value"><?= e(ucfirst($user['role'])) ?></div></article>
        <article class="card"><p class="stat-label">Solved</p><div class="stat-value"><?= (int) $stats['solved_count'] ?></div></article>
        <article class="card"><p class="stat-label">Attempted</p><div class="stat-value"><?= (int) $stats['attempted_count'] ?></div></article>
        <article class="card"><p class="stat-label">Badges</p><div class="stat-value"><?= (int) $stats['badge_count'] ?></div></article>
    </section>
    <section class="grid grid-2" style="margin-top:16px;">
        <article class="card">
            <h2 style="margin-bottom:16px;">Badges</h2>
            <div class="grid">
                <?php foreach ($badges as $badge): ?>
                    <div>
                        <div style="width:32px; margin-bottom:8px;"><?= $badge['icon_svg'] ?></div>
                        <strong><?= e($badge['name']) ?></strong>
                        <p class="muted"><?= e($badge['description']) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$badges): ?>
                    <p class="muted">No badges earned yet.</p>
                <?php endif; ?>
            </div>
        </article>
        <article class="card">
            <h2 style="margin-bottom:16px;">Submission History</h2>
            <div class="table-shell">
                <table>
                    <thead>
                    <tr>
                        <th>Problem</th>
                        <th>Result</th>
                        <th>Time</th>
                        <th>Submitted</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $item): ?>
                        <tr>
                            <td><?= e($item['title']) ?></td>
                            <td><?= (int) $item['is_correct'] === 1 ? 'Correct' : 'Incorrect' ?></td>
                            <td><?= (int) $item['execution_time_ms'] ?> ms</td>
                            <td><?= e(date('M j, Y H:i', strtotime($item['submitted_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="4">Nothing here yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
    <?php
});
