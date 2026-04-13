<?php

declare(strict_types=1);

final class EmailService
{
    public static function sendVerificationOtp(string $toEmail, string $username, string $otp): bool
    {
        $appName = (string) ($_ENV['APP_NAME'] ?? 'GenzLAB');
        $subject = $appName . ' verification code';
        $body = sprintf(
            "Hi %s,\n\nYour verification code is: %s\n\nThis code expires in 10 minutes.\nIf you did not request this, ignore this email.\n\n- %s",
            $username,
            $otp,
            $appName
        );

        $driver = strtolower((string) ($_ENV['MAIL_DRIVER'] ?? 'log'));

        if ($driver === 'smtp') {
            return self::sendViaSmtp($toEmail, $subject, $body);
        }

        if ($driver === 'mail') {
            $from = (string) ($_ENV['MAIL_FROM'] ?? 'no-reply@localhost');
            $headers = "From: " . $from . "\r\n" . "Content-Type: text/plain; charset=UTF-8\r\n";

            return @mail($toEmail, $subject, $body, $headers);
        }

        self::logMail($toEmail, $subject, $body);

        return true;
    }

    private static function sendViaSmtp(string $toEmail, string $subject, string $body): bool
    {
        $host = trim((string) ($_ENV['SMTP_HOST'] ?? ''));
        $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $user = (string) ($_ENV['SMTP_USER'] ?? '');
        $pass = (string) ($_ENV['SMTP_PASS'] ?? '');
        $encryption = strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls')));
        $timeout = (int) ($_ENV['SMTP_TIMEOUT'] ?? 15);
        $from = trim((string) ($_ENV['MAIL_FROM'] ?? 'no-reply@localhost'));
        $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? ($_ENV['APP_NAME'] ?? 'GenzLAB')));

        if ($host === '' || $port <= 0 || $user === '' || $pass === '' || $from === '') {
            self::logMail($toEmail, $subject, $body . "\n\n[SMTP ERROR] Missing SMTP env configuration.");
            return false;
        }

        if (!function_exists('curl_init')) {
            self::logMail($toEmail, $subject, $body . "\n\n[SMTP ERROR] cURL extension is not enabled.");
            return false;
        }

        $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
        $url = sprintf('%s://%s:%d', $scheme, $host, $port);
        $fromHeader = self::formatFromHeader($fromName, $from);
        $payload = implode("\r\n", [
            'Date: ' . date(DATE_RFC2822),
            'To: <' . $toEmail . '>',
            'From: ' . $fromHeader,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $body,
            '',
        ]);

        $cursor = 0;
        $length = strlen($payload);

        $ch = curl_init($url);

        if ($ch === false) {
            self::logMail($toEmail, $subject, $body . "\n\n[SMTP ERROR] Failed to initialize cURL.");
            return false;
        }

        $useSsl = $encryption === 'none' ? CURLUSESSL_NONE : CURLUSESSL_ALL;

        curl_setopt_array($ch, [
            CURLOPT_USERNAME => $user,
            CURLOPT_PASSWORD => $pass,
            CURLOPT_USE_SSL => $useSsl,
            CURLOPT_MAIL_FROM => '<' . $from . '>',
            CURLOPT_MAIL_RCPT => ['<' . $toEmail . '>'],
            CURLOPT_UPLOAD => true,
            CURLOPT_READFUNCTION => static function ($curl, $fileHandle, $maxLength) use (&$payload, &$cursor, $length): string {
                if ($cursor >= $length) {
                    return '';
                }

                $chunk = substr($payload, $cursor, $maxLength);
                $cursor += strlen($chunk);

                return $chunk;
            },
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $error !== '' || ($code !== 0 && $code >= 400)) {
            $detail = $error !== '' ? $error : ('SMTP response code: ' . $code);
            self::logMail($toEmail, $subject, $body . "\n\n[SMTP ERROR] " . $detail);
            return false;
        }

        return true;
    }

    private static function formatFromHeader(string $fromName, string $fromEmail): string
    {
        if ($fromName === '') {
            return '<' . $fromEmail . '>';
        }

        return sprintf('%s <%s>', str_replace(['\r', '\n'], '', $fromName), $fromEmail);
    }

    private static function logMail(string $toEmail, string $subject, string $body): void
    {
        $dir = dirname(__DIR__) . '/storage/logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $toEmail,
            $subject,
            $body
        );

        file_put_contents($dir . '/mail.log', $entry, FILE_APPEND);
    }
}
