<?php

declare(strict_types=1);

function render_app_layout(string $title, array $user, callable $content): void
{
    $navItems = [
        ['label' => 'Dashboard', 'href' => 'dashboard.php'],
        ['label' => 'Problems', 'href' => 'problems.php'],
        ['label' => 'Leaderboard', 'href' => 'leaderboard.php'],
        ['label' => 'Profile', 'href' => 'profile.php'],
    ];
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | SQLab</title>
        <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    </head>
    <body>
        <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">Theme</button>
            <aside class="sidebar">
                <a class="sidebar-logo" href="<?= e(app_url('dashboard.php')) ?>">SQLab</a>
                <nav>
                    <?php foreach ($navItems as $item): ?>
                        <a class="sidebar-link <?= is_active_path($item['href']) ? 'active' : '' ?>" href="<?= e(app_url($item['href'])) ?>">
                            <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <?php if (($user['role'] ?? 'student') === 'admin'): ?>
                    <div>
                        <p class="sidebar-section-label">Admin Panel</p>
                        <nav>
                            <a class="sidebar-link <?= is_active_path('admin/index.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/index.php')) ?>">Overview</a>
                            <a class="sidebar-link <?= is_active_path('admin/problems.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/problems.php')) ?>">Problems</a>
                            <a class="sidebar-link <?= is_active_path('admin/users.php') ? 'active' : '' ?>" href="<?= e(app_url('admin/users.php')) ?>">Users</a>
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
