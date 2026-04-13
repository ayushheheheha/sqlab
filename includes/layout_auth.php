<?php

declare(strict_types=1);

function render_auth_layout(string $title, callable $content): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | SQLab</title>
        <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    </head>
    <body class="auth-shell">
        <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
            </svg>
        </button>
        <main>
            <a class="auth-wordmark" href="<?= e(app_url()) ?>">SQLab</a>
            <div class="card auth-card">
                <?php $content(); ?>
            </div>
        </main>
        <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
    </body>
    </html>
    <?php
}
