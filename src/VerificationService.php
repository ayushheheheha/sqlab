<?php

declare(strict_types=1);

final class VerificationService
{
    public static function issueOtp(int $userId, string $email, string $username): array
    {
        $pdo = DB::getConnection();
        $now = new DateTimeImmutable();

        $existingStmt = $pdo->prepare('SELECT resend_available_at FROM email_verifications WHERE user_id = :user_id LIMIT 1');
        $existingStmt->execute(['user_id' => $userId]);
        $existing = $existingStmt->fetch();

        if ($existing && !empty($existing['resend_available_at']) && strtotime((string) $existing['resend_available_at']) > $now->getTimestamp()) {
            $secondsLeft = max(1, strtotime((string) $existing['resend_available_at']) - $now->getTimestamp());

            return ['success' => false, 'message' => 'Please wait ' . $secondsLeft . 's before requesting another OTP.'];
        }

        $otp = (string) random_int(100000, 999999);
        $hash = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
        $expiresAt = $now->modify('+10 minutes')->format('Y-m-d H:i:s');
        $resendAvailableAt = $now->modify('+60 seconds')->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO email_verifications (user_id, otp_hash, expires_at, attempts, resend_available_at, last_sent_at)
             VALUES (:user_id, :otp_hash, :expires_at, 0, :resend_available_at, NOW())
             ON DUPLICATE KEY UPDATE
                otp_hash = VALUES(otp_hash),
                expires_at = VALUES(expires_at),
                attempts = 0,
                resend_available_at = VALUES(resend_available_at),
                last_sent_at = NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'otp_hash' => $hash,
            'expires_at' => $expiresAt,
            'resend_available_at' => $resendAvailableAt,
        ]);

        $sent = EmailService::sendVerificationOtp($email, $username, $otp);

        if (!$sent) {
            return ['success' => false, 'message' => 'Could not send OTP email. Try again later.'];
        }

        $message = 'Verification code sent to your email.';

        if (($_ENV['APP_ENV'] ?? 'production') !== 'production' && strtolower((string) ($_ENV['MAIL_DRIVER'] ?? 'log')) === 'log') {
            $message .= ' (Dev mode: check storage/logs/mail.log)';
        }

        return ['success' => true, 'message' => $message];
    }

    public static function verifyOtp(int $userId, string $otp): array
    {
        $otp = trim($otp);

        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['success' => false, 'message' => 'OTP must be 6 digits.'];
        }

        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM email_verifications WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $record = $stmt->fetch();

        if (!$record) {
            return ['success' => false, 'message' => 'No OTP request found. Please resend OTP.'];
        }

        if ((int) ($record['attempts'] ?? 0) >= 5) {
            return ['success' => false, 'message' => 'Too many invalid attempts. Please resend OTP.'];
        }

        if (empty($record['expires_at']) || strtotime((string) $record['expires_at']) < time()) {
            return ['success' => false, 'message' => 'OTP expired. Please resend OTP.'];
        }

        if (!password_verify($otp, (string) $record['otp_hash'])) {
            $pdo->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            return ['success' => false, 'message' => 'Invalid OTP.'];
        }

        $pdo->beginTransaction();

        try {
            $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id')->execute(['id' => $userId]);
            $pdo->prepare('DELETE FROM email_verifications WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }

        return ['success' => true, 'message' => 'Email verified successfully.'];
    }
}
