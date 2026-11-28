<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['error' => 'Use POST to log out.']);
    exit;
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    http_response_code(415);
    echo json_encode(['error' => 'Send a JSON request.']);
    exit;
}

if (!session_start()) {
    http_response_code(503);
    echo json_encode(['error' => 'Could not open your session.']);
    exit;
}
unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['pending_login_uid'],
    $_SESSION['pending_login_email'], $_SESSION['pending_login_name']);

// Preserve a separate staff login when both identities use the same session.
if (!empty($_SESSION['staff_id'])) {
    $closed = session_write_close();
} else {
    $_SESSION = [];
    $closed = session_destroy();
    if ($closed && ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'],
        ]);
    }
}

http_response_code($closed ? 200 : 503);
echo json_encode(['success' => $closed]);
