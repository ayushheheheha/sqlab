<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::getCurrentUser();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SQLab</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
    <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">Theme</button>
    <main class="public-main">
        <section class="landing-hero">
            <div class="card landing-copy">
                <span class="landing-kicker">PHP 8.2 + MySQL 8</span>
                <h1>Master SQL with realistic labs.</h1>
                <p class="page-subtitle">SQLab turns ecommerce, university, and hospital datasets into guided problems with live result comparison, XP, badges, and a lightweight admin workflow.</p>
                <div style="display:flex; gap:12px; margin-top:20px;">
                    <a class="btn-primary" href="<?= e($user ? app_url('dashboard.php') : app_url('register.php')) ?>"><?= $user ? 'Go to Dashboard' : 'Create Account' ?></a>
                    <a class="btn-ghost" href="<?= e($user ? app_url('problems.php') : app_url('login.php')) ?>"><?= $user ? 'Browse Problems' : 'Log In' ?></a>
                </div>
            </div>
            <div class="landing-grid">
                <article class="card landing-card">
                    <div class="landing-card-head">
                        <div class="landing-card-icon">SQL</div>
                        <div>
                            <h2>Run Queries</h2>
                            <p class="muted">Every problem executes against seeded sandbox data so your app schema stays untouched.</p>
                        </div>
                    </div>
                    <div class="landing-card-footer"><span>Safe practice</span><span class="landing-arrow">→</span></div>
                </article>
                <article class="card landing-card">
                    <div class="landing-card-head">
                        <div class="landing-card-icon">XP</div>
                        <div>
                            <h2>Track Progress</h2>
                            <p class="muted">Earn XP, maintain streaks, and unlock badges as you solve tougher exercises.</p>
                        </div>
                    </div>
                    <div class="landing-card-footer"><span>Gamified growth</span><span class="landing-arrow">→</span></div>
                </article>
            </div>
        </section>
        <section class="card landing-note">
            <div class="grid grid-3">
                <div><div class="stat-value">3</div><div class="stat-label">Seeded datasets</div></div>
                <div><div class="stat-value">10</div><div class="stat-label">Practice problems</div></div>
                <div><div class="stat-value">5</div><div class="stat-label">Unlockable badges</div></div>
            </div>
        </section>
    </main>
    <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
