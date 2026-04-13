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
        <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">Theme</button>
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
