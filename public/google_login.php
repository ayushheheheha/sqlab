<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::getCurrentUser()) {
    redirect('dashboard.php');
}

if (!GoogleOAuthService::isConfigured()) {
    set_auth_flash('warning', 'Google sign-in is not configured yet.');
    redirect('login.php');
}

$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth_state'] = $state;

header('Location: ' . GoogleOAuthService::buildAuthUrl($state));
exit;
