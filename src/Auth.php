<?php

declare(strict_types=1);

final class Auth
{
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

        return ['success' => true, 'message' => 'Registration successful. You can log in now.'];
    }

    public static function login(string $email, string $password): bool
    {
        $stmt = DB::getConnection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];

        return true;
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
}
