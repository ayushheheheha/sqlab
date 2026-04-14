<?php

declare(strict_types=1);

function render_app_layout(string $title, array $user, callable $content): void
{
    $activeSubject = get_active_subject();
    $subjectSlug = (string) ($activeSubject['slug'] ?? 'sql');
    $navItems = [
        ['label' => 'Subjects', 'href' => 'dashboard.php'],
        ['label' => 'Dashboard', 'href' => 'subject_dashboard.php?subject=' . urlencode($subjectSlug)],
        ['label' => 'Problems', 'href' => 'problems.php'],
        ['label' => 'Practice Lab', 'href' => 'practice.php'],
        ['label' => 'Leaderboard', 'href' => 'leaderboard.php'],
        ['label' => 'Profile', 'href' => 'profile.php'],
    ];
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | GenzLAB</title>
        <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    </head>
    <body>
        <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
            </svg>
        </button>
            <aside class="sidebar">
                <a class="sidebar-logo" href="<?= e(app_url('dashboard.php')) ?>">GenzLAB</a>
                <nav>
                    <?php foreach ($navItems as $item): ?>
                        <?php
                        $isActive = is_active_path($item['href']);

                        if (str_starts_with($item['href'], 'subject_dashboard.php') && is_active_path('subject_dashboard.php')) {
                            $isActive = true;
                        }
                        ?>
                        <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= e(app_url($item['href'])) ?>">
                            <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="sidebar-section-label">Track: <?= e($activeSubject['name'] ?? 'SQL') ?></div>
                <?php if (($user['role'] ?? 'student') === 'admin'): ?>
                    <div>
                        <p class="sidebar-section-label">Admin Panel</p>
                        <nav>
                            <a class="sidebar-link <?= is_active_path('admin/index.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/index.php')) ?>">Overview</a>
                            <a class="sidebar-link <?= is_active_path('admin/problems.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/problems.php')) ?>">Problems</a>
                            <a class="sidebar-link <?= is_active_path('admin/users.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/users.php')) ?>">Users</a>
                            <a class="sidebar-link <?= is_active_path('admin/datasets.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/datasets.php')) ?>">Datasets</a>
                        </nav>
                    </div>
                <?php endif; ?>
                <div class="sidebar-section-label">
                    <?= e($user['username']) ?> · <a href="<?= e(app_url('logout.php')) ?>">Logout</a>
                </div>
            </aside>
            <main class="app-main">
                <?php $content(); ?>
            </main>
        <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
    </body>
    </html>
    <?php
}
