<?php

declare(strict_types=1);

final class GoogleOAuthService
{
    public static function isConfigured(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '' && self::redirectUri() !== '';
    }

    public static function buildAuthUrl(string $state): string
    {
        $params = [
            'client_id' => self::clientId(),
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
            'state' => $state,
            'prompt' => 'select_account',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public static function exchangeCodeForToken(string $code): ?array
    {
        $payload = [
            'code' => $code,
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ];

        return self::httpPostJson('https://oauth2.googleapis.com/token', $payload);
    }

    public static function fetchUserProfile(string $accessToken): ?array
    {
        return self::httpGetJson('https://www.googleapis.com/oauth2/v3/userinfo', [
            'Authorization: Bearer ' . $accessToken,
        ]);
    }

    private static function clientId(): string
    {
        return (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? '');
    }

    private static function clientSecret(): string
    {
        return (string) ($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
    }

    private static function redirectUri(): string
    {
        return (string) ($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
    }

    private static function httpPostJson(string $url, array $payload): ?array
    {
        if (!function_exists('curl_init')) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($payload),
                    'timeout' => 15,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);

            if (!is_string($response)) {
                return null;
            }

            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : null;
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function httpGetJson(string $url, array $headers = []): ?array
    {
        if (!function_exists('curl_init')) {
            $headerText = '';
            if ($headers) {
                $headerText = implode("\r\n", $headers) . "\r\n";
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => $headerText,
                    'timeout' => 15,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);

            if (!is_string($response)) {
                return null;
            }

            $decoded = json_decode($response, true);

            return is_array($decoded) ? $decoded : null;
        }

        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
