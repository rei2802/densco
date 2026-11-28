<?php
// Shared helpers for the "Forgot password" flow (customers only).
// A reset link carries a random token. Only a SHA-256 hash of the token is
// stored, so a copy of the database can't be used to reset anyone's password.

const RESET_TOKEN_MINUTES = 60;   // how long a link stays valid
const RESET_COOLDOWN_SECONDS = 60; // minimum time between two requests per account

function ensure_reset_table(mysqli $conn) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS password_resets (
            reset_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (token_hash),
            INDEX (user_id)
        )"
    );
}

function site_base_url() {
    $configured = getenv('APP_URL') ?: getenv('RENDER_EXTERNAL_URL');
    if ($configured) {
        $url = parse_url($configured);
        if (!$url || !in_array($url['scheme'] ?? '', ['https', 'http'], true) || empty($url['host'])
            || isset($url['query']) || isset($url['fragment']) || isset($url['user'])) {
            throw new RuntimeException('Invalid application URL.');
        }
        return rtrim($configured, '/');
    }
    if (getenv('RENDER') || !is_local_host()) {
        throw new RuntimeException('Configure APP_URL for password reset links.');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

function is_local_host() {
    $h = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return in_array($h, ['localhost', '127.0.0.1', '::1'], true);
}
