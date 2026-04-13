<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_auth.php';

if (Auth::getCurrentUser()) {
    redirect('dashboard.php');
}

$pending = Auth::getPendingVerificationUser();

if (!$pending) {
    set_auth_flash('warning', 'Please login or register first.');
    redirect('login.php');
}

$error = null;
$message = null;
$flash = pull_auth_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_request();
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        $result = Auth::resendOtpForPendingUser();
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }

    if ($action === 'verify') {
        $result = Auth::verifyOtpForPendingUser((string) ($_POST['otp'] ?? ''));

        if ($result['success']) {
            redirect('dashboard.php');
        }

        $error = $result['message'] ?? 'Could not verify OTP.';
    }
}

render_auth_layout('Verify Email', static function () use ($pending, $error, $message, $flash): void {
    ?>
    <h1>Verify your email</h1>
    <p class="auth-copy">Enter the 6-digit OTP sent to <strong><?= e((string) $pending['email']) ?></strong>.</p>
    <?php if ($flash): ?>
        <div class="flash flash-<?= e((string) ($flash['type'] ?? 'success')) ?>"><?= e((string) ($flash['message'] ?? '')) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="flash flash-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="verify">
        <div class="form-group">
            <label for="otp">OTP Code</label>
            <input id="otp" type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" required>
        </div>
        <button class="btn-primary btn-block" type="submit">Verify Email</button>
    </form>

    <form method="post" style="margin-top:12px;">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="resend">
        <button class="btn-ghost btn-block" type="submit">Resend OTP</button>
    </form>

    <p class="auth-copy" style="margin-top:16px;">Want to use another account? <a href="<?= e(app_url('login.php')) ?>">Back to login</a>.</p>
    <?php
});
