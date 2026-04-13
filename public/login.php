<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_auth.php';

if (Auth::getCurrentUser()) {
    redirect('dashboard.php');
}

$error = null;
$flash = pull_auth_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_request();

    $result = Auth::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));

    if (($result['success'] ?? false) === true) {
        redirect('dashboard.php');
    }

    if (!empty($result['requires_verification'])) {
        set_auth_flash('warning', (string) ($result['message'] ?? 'Please verify your email with OTP.'));
        redirect('verify-email.php');
    }

    $error = (string) ($result['message'] ?? 'Incorrect email or password.');
}

render_auth_layout('Login', static function () use ($error, $flash): void {
    ?>
    <h1>Welcome back</h1>
    <p class="auth-copy">Log in to continue your SQL practice streak.</p>
    <?php if ($flash): ?>
        <div class="flash flash-<?= e((string) ($flash['type'] ?? 'warning')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="auth-form" novalidate>
        <?= csrf_input() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" autocomplete="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <div class="auth-password-wrap">
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                <button type="button" class="auth-password-toggle" data-password-toggle="password">Show</button>
            </div>
        </div>
        <button class="btn-primary btn-block" type="submit">Log In</button>
    </form>
    <?php if (Auth::isGoogleConfigured()): ?>
        <div class="auth-divider"><span>or continue with</span></div>
        <a class="btn-ghost btn-block auth-google" href="<?= e(app_url('google_login.php')) ?>">Continue with Google</a>
    <?php endif; ?>
    <p class="auth-note">No account yet? <a href="<?= e(app_url('register.php')) ?>">Register here</a>.</p>
    <?php
});
