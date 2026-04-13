<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/db.php';

spl_autoload_register(static function (string $class): void {
    $path = dirname(__DIR__) . '/src/' . $class . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

function app_base_path(): string
{
    $basePath = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/');

    return $basePath === '' ? '' : '/' . $basePath;
}

function app_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return app_base_path() . '/';
    }

    return app_base_path() . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_uri_path(): string
{
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $basePath = app_base_path();

    if ($basePath !== '' && str_starts_with($requestUri, $basePath)) {
        $requestUri = substr($requestUri, strlen($basePath)) ?: '/';
    }

    return $requestUri;
}

function is_active_path(string $path): bool
{
    $current = rtrim(current_uri_path(), '/') ?: '/';
    $target = '/' . trim($path, '/');
    $target = rtrim($target, '/') ?: '/';

    return $current === $target;
}

