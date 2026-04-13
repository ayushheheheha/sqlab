<?php

declare(strict_types=1);

if (!function_exists('loadEnv')) {
    function loadEnv(?string $envPath = null): void
    {
        static $loaded = false;

        if ($loaded) {
            return;
        }

        $envPath ??= dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($envPath)) {
            $loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            $loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $trimmed, 2);
            $name = trim($name);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }

        $loaded = true;
    }
}

loadEnv();

