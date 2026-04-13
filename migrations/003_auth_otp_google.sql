ALTER TABLE users
    ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER password_hash,
    ADD COLUMN google_id VARCHAR(191) NULL UNIQUE AFTER email_verified_at,
    ADD COLUMN auth_provider ENUM('password', 'google', 'password+google') NOT NULL DEFAULT 'password' AFTER google_id;

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    resend_available_at DATETIME NOT NULL,
    last_sent_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_verifications_user (user_id),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

UPDATE users SET email_verified_at = COALESCE(email_verified_at, created_at) WHERE email_verified_at IS NULL;
