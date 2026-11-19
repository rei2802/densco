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
echo json_encode(['success' => true]);
