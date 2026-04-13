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

$state = (string) ($_GET['state'] ?? '');
$storedState = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);

if ($state === '' || $storedState === '' || !hash_equals($storedState, $state)) {
    set_auth_flash('error', 'Invalid Google OAuth state. Please try again.');
    redirect('login.php');
}

if (!empty($_GET['error'])) {
    set_auth_flash('error', 'Google sign-in was cancelled or denied.');
    redirect('login.php');
}

$code = (string) ($_GET['code'] ?? '');

if ($code === '') {
    set_auth_flash('error', 'Google sign-in did not return a code.');
    redirect('login.php');
}

$token = GoogleOAuthService::exchangeCodeForToken($code);

if (!$token || empty($token['access_token'])) {
    set_auth_flash('error', 'Failed to exchange Google code for token.');
    redirect('login.php');
}

$profile = GoogleOAuthService::fetchUserProfile((string) $token['access_token']);

if (!$profile) {
    set_auth_flash('error', 'Failed to fetch Google profile.');
    redirect('login.php');
}

$result = Auth::loginWithGoogleProfile($profile);

if (!($result['success'] ?? false)) {
    set_auth_flash('error', (string) ($result['message'] ?? 'Google sign-in failed.'));
    redirect('login.php');
}

redirect('dashboard.php');
