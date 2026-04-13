<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_auth.php';

if (Auth::getCurrentUser()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        redirect('dashboard.php');
    }

    $error = 'Incorrect email or password.';
}

render_auth_layout('Login', static function () use ($error): void {
    ?>
    <h1>Welcome back</h1>
    <p class="auth-copy">Log in to continue your SQL practice streak.</p>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
        </div>
        <button class="btn-primary btn-block" type="submit">Log In</button>
    </form>
    <p class="auth-copy" style="margin-top:16px;">No account yet? <a href="<?= e(app_url('register.php')) ?>">Register here</a>.</p>
    <?php
});
