<?php
// Tells the browser whether the customer's PHP session is still alive.
// Used by js/auth.js to show the "session expired" pop-up and log the customer
// out automatically.
//
// read_and_close: the session is only READ and closed again, so this check does
// not extend the session's lifetime (otherwise the page would keep a session
// alive forever and it could never expire).
header('Content-Type: application/json');
header('Cache-Control: no-store');

session_start(['read_and_close' => true]);

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'session_expired' => true]);
    exit;
}

// Customers who were already logged in when email verification was introduced
// must verify too: end their session so the next login asks for the code.
require_once __DIR__ . '/config.php';
$uid = (int)$_SESSION['user_id'];
$col = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'");
if ($col->num_rows > 0) {
    $st = $conn->prepare("SELECT email_verified_at FROM users WHERE user_id = ? LIMIT 1");
    $st->bind_param("i", $uid);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if ($u && $u['email_verified_at'] === null) {
        session_start();          // reopen for writing (the first call was read-only)
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'session_expired' => true, 'needs_verification' => true]);
        exit;
    }
}
echo json_encode(['success' => true]);
