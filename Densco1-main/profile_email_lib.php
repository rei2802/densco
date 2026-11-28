<?php

require_once __DIR__ . '/email_otp_lib.php';
require_once __DIR__ . '/password_reset_lib.php';

function pending_profile_email(mysqli $db, int $userId): ?string {
    try {
        $row = $db->execute_query(
            'SELECT new_email FROM profile_email_changes WHERE user_id = ? AND expires_at > UTC_TIMESTAMP()',
            [$userId]
        )->fetch_assoc();
        return $row['new_email'] ?? null;
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1146) throw $error;
        return null;
    }
}

function ensure_profile_email_changes(mysqli $db): void {
    $db->query('CREATE TABLE IF NOT EXISTS profile_email_changes (
        user_id INT NOT NULL PRIMARY KEY,
        original_email VARCHAR(100) NOT NULL,
        new_email VARCHAR(100) NOT NULL,
        password_state CHAR(64) NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        expires_at DATETIME NOT NULL,
        last_sent_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function request_profile_email_change(mysqli $db, int $userId, string $email, string $password): string {
    ensure_profile_email_changes($db);
    $db->begin_transaction();
    try {
        $user = $db->execute_query(
            'SELECT email, full_name, password FROM users WHERE user_id = ? FOR UPDATE', [$userId]
        )->fetch_assoc();
        if (!$user || !password_verify($password, $user['password'])) {
            throw new DomainException('Enter your current password to change your email.');
        }
        if (strcasecmp($user['email'], $email) === 0) {
            throw new DomainException('Enter an email address different from your current one.');
        }
        if ($db->execute_query('SELECT 1 FROM users WHERE email = ? AND user_id <> ?', [$email, $userId])->fetch_assoc()) {
            throw new DomainException('That email is already used by another account.');
        }
        $pending = $db->execute_query(
            'SELECT TIMESTAMPDIFF(SECOND, last_sent_at, UTC_TIMESTAMP()) AS age FROM profile_email_changes WHERE user_id = ?',
            [$userId]
        )->fetch_assoc();
        if ($pending && (int) $pending['age'] < OTP_COOLDOWN_SECS) {
            throw new DomainException('Wait one minute before requesting another code.');
        }
        $code = otp_generate();
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $version = hash('sha256', $user['password']);
        $minutes = OTP_MINUTES;
        $db->execute_query(
            "INSERT INTO profile_email_changes
             (user_id, original_email, new_email, password_state, code_hash, expires_at, last_sent_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL $minutes MINUTE, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE original_email = VALUES(original_email), new_email = VALUES(new_email),
             password_state = VALUES(password_state), code_hash = VALUES(code_hash), attempts = 0,
             expires_at = VALUES(expires_at), last_sent_at = VALUES(last_sent_at)",
            [$userId, $user['email'], $email, $version, $hash]
        );
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }

    if (!otp_send_email($email, $user['full_name'], $code, emailChange: true)) {
        // Keep the cooldown row, but never expose an undelivered code or replace a newer request.
        $db->execute_query('UPDATE profile_email_changes SET expires_at = UTC_TIMESTAMP() WHERE user_id = ? AND code_hash = ?', [$userId, $hash]);
        throw new DomainException('Could not send a verification code. Try again in one minute.');
    }
    return $email;
}

function verify_profile_email_change(mysqli $db, int $userId, string $email, string $code): bool {
    ensure_profile_email_changes($db);
    ensure_verified_column($db);
    ensure_login_otp_table($db);
    ensure_reset_table($db);
    $db->begin_transaction();
    try {
        $user = $db->execute_query('SELECT email, password FROM users WHERE user_id = ? FOR UPDATE', [$userId])->fetch_assoc();
        $pending = $db->execute_query(
            'SELECT * FROM profile_email_changes WHERE user_id = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE',
            [$userId]
        )->fetch_assoc();
        if (!$pending || !$user || $pending['new_email'] !== $email || $pending['original_email'] !== $user['email']
            || !hash_equals($pending['password_state'], hash('sha256', $user['password']))) {
            throw new DomainException('This email change expired. Request a new code.');
        }
        if ((int) $pending['attempts'] >= OTP_MAX_ATTEMPTS) {
            throw new DomainException('Too many incorrect codes. Request a new code.');
        }
        if (!password_verify($code, $pending['code_hash'])) {
            $db->execute_query('UPDATE profile_email_changes SET attempts = attempts + 1 WHERE user_id = ?', [$userId]);
            $db->commit();
            return false;
        }
        try {
            $db->execute_query(
                'UPDATE users SET email = ?, email_verified_at = UTC_TIMESTAMP() WHERE user_id = ?',
                [$pending['new_email'], $userId]
            );
        } catch (mysqli_sql_exception $error) {
            if ($error->getCode() === 1062) throw new DomainException('That email is already used by another account.');
            throw $error;
        }
        $db->execute_query('DELETE FROM profile_email_changes WHERE user_id = ?', [$userId]);
        $db->execute_query('DELETE FROM login_otps WHERE user_id = ?', [$userId]);
        $db->execute_query('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        $db->commit();
        return true;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
