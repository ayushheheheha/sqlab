<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

final class DB
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        try {
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }

        return self::$instance;
    }

    public static function sandboxConnection(string $dbName): PDO
    {
        $host = $_ENV['DB_SANDBOX_HOST'] ?? (($_ENV['DB_HOST'] ?? '127.0.0.1') === '127.0.0.1' ? 'localhost' : ($_ENV['DB_HOST'] ?? 'localhost'));
        $port = $_ENV['DB_PORT'] ?? '3306';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        $user = $_ENV['DB_SANDBOX_USER'] ?? 'sqlab_sandbox';
        $pass = $_ENV['DB_SANDBOX_PASS'] ?? 'SandboxPass!99';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbName, $charset);

        try {
            return new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $message = 'Sandbox database connection failed.';

            if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                $message .= ' ' . $exception->getMessage();
            }

            throw new RuntimeException($message, 0, $exception);
        }
    }
}
