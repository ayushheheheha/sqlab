<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_auth.php';

if (Auth::getCurrentUser()) {
    redirect('dashboard.php');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = Auth::register(
        (string) ($_POST['username'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? '')
    );

    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

render_auth_layout('Register', static function () use ($message, $error): void {
    ?>
    <h1>Create your SQLab account</h1>
    <p class="auth-copy">Track solves, earn badges, and climb the leaderboard.</p>
    <?php if ($message): ?>
        <div class="flash flash-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" required minlength="3">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required minlength="8">
        </div>
        <button class="btn-primary btn-block" type="submit">Register</button>
    </form>
    <p class="auth-copy" style="margin-top:16px;">Already have an account? <a href="<?= e(app_url('login.php')) ?>">Log in</a>.</p>
    <?php
});
