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
        <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
        <title><?= e($title) ?> | GenzLAB</title>
        <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
    </head>
    <body class="auth-shell">
        <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
            </svg>
        </button>
        <main class="auth-layout">
            <section class="card auth-brand-panel" aria-label="GenzLAB highlights">
                <a class="auth-wordmark" href="<?= e(app_url()) ?>">GenzLAB</a>
                <p class="auth-kicker">Practice Platform</p>
                <h1>Level up SQL with focused daily drills.</h1>
                <p class="auth-brand-copy">Solve realistic SQL challenges, keep your streak alive, and track progress with badges and leaderboard milestones.</p>
                <ul class="auth-brand-list">
                    <li>Real datasets with instant feedback</li>
                    <li>Practice Lab for free-form SQL</li>
                    <li>XP, streaks, and ranking</li>
                </ul>
            </section>
            <section class="auth-form-panel" aria-label="Authentication form">
                <div class="card auth-card">
                    <?php $content(); ?>
                </div>
            </section>
        </main>
        <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-password-toggle]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const targetId = button.getAttribute('data-password-toggle');
                        const input = targetId ? document.getElementById(targetId) : null;

                        if (!input) {
                            return;
                        }

                        const isPassword = input.type === 'password';
                        input.type = isPassword ? 'text' : 'password';
                        button.textContent = isPassword ? 'Hide' : 'Show';
                        input.focus();
                    });
                });
            });
        </script>
    </body>
    </html>
    <?php
}
