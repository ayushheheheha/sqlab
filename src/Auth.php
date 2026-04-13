<?php

declare(strict_types=1);

final class Auth
{
    private const PENDING_VERIFICATION_KEY = 'pending_verification_user_id';

    public static function register(string $username, string $email, string $password): array
    {
        $username = trim($username);
        $email = strtolower(trim($email));

        if (mb_strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please provide a valid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        $pdo = DB::getConnection();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1');
        $check->execute(['email' => $email, 'username' => $username]);

        if ($check->fetch()) {
            return ['success' => false, 'message' => 'That email or username is already in use.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, last_active)
             VALUES (:username, :email, :password_hash, :role, CURDATE())'
        );
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash,
            'role' => 'student',
        ]);

        $userId = (int) $pdo->lastInsertId();
        self::setPendingVerificationUserId($userId);

        $otpResult = VerificationService::issueOtp($userId, $email, $username);

        if (!$otpResult['success']) {
            return ['success' => false, 'message' => $otpResult['message']];
        }

        return ['success' => true, 'message' => $otpResult['message']];
    }

    public static function login(string $email, string $password): array
    {
        $stmt = DB::getConnection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !is_string($user['password_hash'] ?? null) || !password_verify($password, (string) $user['password_hash'])) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }

        if (empty($user['email_verified_at'])) {
            self::setPendingVerificationUserId((int) $user['id']);
            VerificationService::issueOtp((int) $user['id'], (string) $user['email'], (string) $user['username']);

            return [
                'success' => false,
                'requires_verification' => true,
                'message' => 'Email not verified. Enter the OTP sent to your email.',
            ];
        }

        self::loginUser($user);

        return ['success' => true];
    }

    public static function loginWithGoogleProfile(array $profile): array
    {
        $googleId = trim((string) ($profile['sub'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? 'Google User'));
        $emailVerified = (bool) ($profile['email_verified'] ?? false);

        if ($googleId === '' || $email === '' || !$emailVerified) {
            return ['success' => false, 'message' => 'Google account did not provide a verified email.'];
        }

        $pdo = DB::getConnection();

        $googleStmt = $pdo->prepare('SELECT * FROM users WHERE google_id = :google_id LIMIT 1');
        $googleStmt->execute(['google_id' => $googleId]);
        $googleUser = $googleStmt->fetch();

        if ($googleUser) {
            self::loginUser($googleUser);
            return ['success' => true];
        }

        $emailStmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $emailStmt->execute(['email' => $email]);
        $existing = $emailStmt->fetch();

        if ($existing) {
            $update = $pdo->prepare(
                'UPDATE users
                 SET google_id = :google_id,
                     email_verified_at = COALESCE(email_verified_at, NOW()),
                     auth_provider = CASE
                        WHEN auth_provider = "password" THEN "password+google"
                        ELSE auth_provider
                     END
                 WHERE id = :id'
            );
            $update->execute(['google_id' => $googleId, 'id' => $existing['id']]);

            $existing['google_id'] = $googleId;
            $existing['email_verified_at'] = $existing['email_verified_at'] ?? date('Y-m-d H:i:s');
            self::loginUser($existing);

            return ['success' => true];
        }

        $username = self::generateUniqueUsername($name !== '' ? $name : ((string) strstr($email, '@', true)));
        $randomPasswordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => 12]);

        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, last_active, email_verified_at, google_id, auth_provider)
             VALUES (:username, :email, :password_hash, :role, CURDATE(), NOW(), :google_id, :auth_provider)'
        );
        $insert->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $randomPasswordHash,
            'role' => 'student',
            'google_id' => $googleId,
            'auth_provider' => 'google',
        ]);

        $newUserId = (int) $pdo->lastInsertId();
        $freshStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $freshStmt->execute(['id' => $newUserId]);
        $newUser = $freshStmt->fetch();

        if (!$newUser) {
            return ['success' => false, 'message' => 'Unable to finish Google sign-in.'];
        }

        self::loginUser($newUser);

        return ['success' => true];
    }

    public static function resendOtpForPendingUser(): array
    {
        $user = self::getPendingVerificationUser();

        if (!$user) {
            return ['success' => false, 'message' => 'No pending verification session found.'];
        }

        return VerificationService::issueOtp((int) $user['id'], (string) $user['email'], (string) $user['username']);
    }

    public static function verifyOtpForPendingUser(string $otp): array
    {
        $user = self::getPendingVerificationUser();

        if (!$user) {
            return ['success' => false, 'message' => 'No pending verification session found.'];
        }

        $result = VerificationService::verifyOtp((int) $user['id'], $otp);

        if (!$result['success']) {
            return $result;
        }

        $freshStmt = DB::getConnection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $freshStmt->execute(['id' => (int) $user['id']]);
        $freshUser = $freshStmt->fetch();

        if (!$freshUser) {
            return ['success' => false, 'message' => 'User not found after verification.'];
        }

        self::clearPendingVerificationUserId();
        self::loginUser($freshUser);

        return ['success' => true, 'message' => 'Email verified and logged in.'];
    }

    public static function getPendingVerificationUser(): ?array
    {
        $userId = self::getPendingVerificationUserId();

        if ($userId <= 0) {
            return null;
        }

        $stmt = DB::getConnection()->prepare('SELECT id, username, email, email_verified_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function getPendingVerificationUserId(): int
    {
        return (int) ($_SESSION[self::PENDING_VERIFICATION_KEY] ?? 0);
    }

    public static function setPendingVerificationUserId(int $userId): void
    {
        $_SESSION[self::PENDING_VERIFICATION_KEY] = $userId;
    }

    public static function clearPendingVerificationUserId(): void
    {
        unset($_SESSION[self::PENDING_VERIFICATION_KEY]);
    }

    public static function isGoogleConfigured(): bool
    {
        return GoogleOAuthService::isConfigured();
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            redirect('login.php');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (($_SESSION['role'] ?? 'student') !== 'admin') {
            redirect('dashboard.php');
        }
    }

    public static function getCurrentUser(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return User::findById((int) $_SESSION['user_id']);
    }

    private static function loginUser(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = (string) ($user['role'] ?? 'student');
        self::clearPendingVerificationUserId();
    }

    private static function generateUniqueUsername(string $seed): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(str_replace(' ', '_', $seed))) ?: 'user';
        $candidate = substr($base, 0, 24);

        $pdo = DB::getConnection();

        for ($i = 0; $i < 20; $i++) {
            $username = $i === 0 ? $candidate : substr($candidate, 0, 20) . '_' . random_int(100, 9999);
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);

            if (!$stmt->fetch()) {
                return $username;
            }
        }

        return 'user_' . random_int(100000, 999999);
    }
}
