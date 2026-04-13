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
    <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
    </button>
    <main class="public-main">
        <section class="card simple-home">
            <h1>Master SQL with realistic labs.</h1>
            <p class="page-subtitle">Practice SQL with guided challenges and instant feedback in a focused, distraction-free workspace.</p>
            <div class="simple-home-actions">
                <a class="btn-primary" href="<?= e($user ? app_url('dashboard.php') : app_url('register.php')) ?>"><?= $user ? 'Go to Dashboard' : 'Create Account' ?></a>
                <a class="btn-ghost" href="<?= e($user ? app_url('problems.php') : app_url('login.php')) ?>"><?= $user ? 'Browse Problems' : 'Log In' ?></a>
            </div>
        </section>
    </main>
    <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
