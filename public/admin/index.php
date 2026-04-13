<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$problemCount = (int) DB::getConnection()->query('SELECT COUNT(*) FROM problems')->fetchColumn();
$userCount = (int) DB::getConnection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$submissionCount = (int) DB::getConnection()->query('SELECT COUNT(*) FROM submissions')->fetchColumn();

render_app_layout('Admin', $user, static function () use ($problemCount, $userCount, $submissionCount): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Admin Dashboard</h1>
            <p class="page-subtitle">Manage content, users, and platform activity.</p>
        </div>
    </section>
    <section class="grid grid-3">
        <article class="card"><p class="stat-label">Problems</p><div class="stat-value"><?= $problemCount ?></div></article>
        <article class="card"><p class="stat-label">Users</p><div class="stat-value"><?= $userCount ?></div></article>
        <article class="card"><p class="stat-label">Submissions</p><div class="stat-value"><?= $submissionCount ?></div></article>
    </section>
    <?php
});
