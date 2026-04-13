<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_auth.php';

if (Auth::getCurrentUser()) {
    redirect('dashboard.php');
}

$message = null;
$error = null;
$flash = pull_auth_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_request();

    $result = Auth::register(
        (string) ($_POST['username'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? '')
    );

    if ($result['success']) {
        set_auth_flash('success', (string) $result['message']);
        redirect('verify-email.php');
    } else {
        $error = $result['message'];
    }
}

render_auth_layout('Register', static function () use ($message, $error, $flash): void {
    ?>
    <h1>Create your GenzLAB account</h1>
    <p class="auth-copy">Track solves, earn badges, and climb the leaderboard.</p>
    <?php if ($flash): ?>
        <div class="flash flash-<?= e((string) ($flash['type'] ?? 'success')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="flash flash-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="auth-form" novalidate>
        <?= csrf_input() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" autocomplete="username" required minlength="3">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" autocomplete="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="auth-password-wrap">
                <input id="password" type="password" name="password" autocomplete="new-password" required minlength="8">
                <button type="button" class="auth-password-toggle" data-password-toggle="password">Show</button>
            </div>
        </div>
        <button class="btn-primary btn-block" type="submit">Register</button>
    </form>
    <?php if (Auth::isGoogleConfigured()): ?>
        <div class="auth-divider"><span>or continue with</span></div>
        <a class="btn-ghost btn-block auth-google" href="<?= e(app_url('google_login.php')) ?>">Sign up with Google</a>
    <?php endif; ?>
    <p class="auth-note">Already have an account? <a href="<?= e(app_url('login.php')) ?>">Log in</a>.</p>
    <?php
});
