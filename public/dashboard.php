<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$subjectFromQuery = trim((string) ($_GET['subject'] ?? ''));

if ($subjectFromQuery !== '') {
    set_active_subject_slug($subjectFromQuery);
    redirect('subject_dashboard.php?subject=' . urlencode(get_active_subject_slug()));
}

$subjects = Subject::statsForUser((int) $user['id']);
$activeSlug = get_active_subject_slug();

render_app_layout('Subjects', $user, static function () use ($subjects, $activeSlug): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Choose Your Subject</h1>
            <p class="page-subtitle">Pick a track to open its dashboard. SQL is live now, and more subjects are being added.</p>
        </div>
    </section>

    <section class="grid grid-3">
        <?php foreach ($subjects as $subject): ?>
            <?php
            $isActive = (string) $subject['slug'] === $activeSlug;
            $total = (int) ($subject['total_problems'] ?? 0);
            $solved = (int) ($subject['solved_count'] ?? 0);
            ?>
            <article class="card">
                <div class="page-header" style="margin-bottom:12px; padding-bottom:10px;">
                    <div>
                        <h2><?= e($subject['name']) ?></h2>
                        <p class="page-subtitle"><?= e((string) $subject['description']) ?></p>
                    </div>
                    <?php if ($isActive): ?>
                        <span class="badge badge-success">Current</span>
                    <?php endif; ?>
                </div>
                <p class="muted">Solved: <?= $solved ?> / <?= $total ?></p>
                <div style="margin-top:14px;">
                    <a class="btn-primary" href="<?= e(app_url('subject_dashboard.php?subject=' . urlencode((string) $subject['slug']))) ?>">Open <?= e($subject['name']) ?> Dashboard</a>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
});
