<?php
// Helpers for verifying an email address with a 6-digit one-time code (OTP)
// during registration. The account is NOT created until the code is entered,
// so nobody can register with an email address they don't own.
// Only a hash of the code is stored, never the code itself.

require_once __DIR__ . '/mail_lib.php';

const OTP_MINUTES        = 10; // how long a code stays valid
const OTP_COOLDOWN_SECS  = 60; // minimum time between two codes for one email
const OTP_MAX_ATTEMPTS   = 5;  // wrong guesses allowed per code

function ensure_otp_table(mysqli $conn) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS email_otps (
            email VARCHAR(100) NOT NULL PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            otp_hash CHAR(64) NOT NULL,
            attempts TINYINT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            last_sent_at DATETIME NOT NULL
        )"
    );
}

function otp_hash_for($email, $code) {
    return hash('sha256', strtolower($email) . '|' . $code);
}

function otp_generate() {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Seconds the person still has to wait before another code may be sent (0 = ok).
function otp_wait_seconds(mysqli $conn, $email) {
    $stmt = $conn->prepare("SELECT GREATEST(0, ? - TIMESTAMPDIFF(SECOND, last_sent_at, NOW())) AS wait_s FROM email_otps WHERE email = ? LIMIT 1");
    $cool = OTP_COOLDOWN_SECS;
    $stmt->bind_param("is", $cool, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['wait_s'] : 0;
}

// Emails the code. Returns true if the email provider accepted it.
function otp_send_email($email, $name, $code) {
    $mins = OTP_MINUTES;
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $subject = "Your Densco verification code: $code";
    $text = "Hello $name,\r\n\r\nYour Densco Health Products verification code is: $code\r\n\r\n"
          . "It is valid for $mins minutes. If you did not try to create an account, you can ignore this email.\r\n";
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;color:#222">'
          . "<p>Hello $safeName,</p>"
          . '<p>Use this code to verify your email address and finish creating your Densco account:</p>'
          . '<p style="font-size:32px;letter-spacing:8px;font-weight:700;margin:24px 0">' . $code . '</p>'
          . "<p>The code is valid for $mins minutes. If you did not try to create an account, you can ignore this email.</p>"
          . '<p style="color:#888;font-size:12px">Densco Health Products</p></div>';
    return send_email($email, $name, $subject, $html, $text);
}

// Stores a fresh code for this pending registration (replacing any earlier one)
// and returns the plain code so it can be emailed.
function otp_store(mysqli $conn, $email, $name, $pwHash) {
    $code = otp_generate();
    $hash = otp_hash_for($email, $code);
    $mins = OTP_MINUTES;
    $stmt = $conn->prepare(
        "INSERT INTO email_otps (email, full_name, password_hash, otp_hash, attempts, expires_at, last_sent_at)
         VALUES (?, ?, ?, ?, 0, NOW() + INTERVAL $mins MINUTE, NOW())
         ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), password_hash = VALUES(password_hash),
            otp_hash = VALUES(otp_hash), attempts = 0, expires_at = VALUES(expires_at), last_sent_at = NOW()"
    );
    $stmt->bind_param("ssss", $email, $name, $pwHash, $hash);
    $stmt->execute();
    $stmt->close();
    return $code;
}
