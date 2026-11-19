<?php
// Customer cancels one or more of THEIR OWN pending orders. A reason is
// required. The stock reserved at checkout is returned to the shop in the same
// transaction (see change_order_status in orders_lib.php).
//
// POST fields:
//   order_ids[]  (or the older single order_id)
//   reason       text, 1-500 characters
session_start();
require_once 'config.php';
require_once 'orders_lib.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'session_expired' => true,
        'error' => 'Your session expired. Please log in again.',
    ]);
    exit;
}

// Accept order_ids[] (bulk) or order_id (single)
$ids = [];
if (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
    $ids = array_map('intval', $_POST['order_ids']);
} elseif (isset($_POST['order_id'])) {
    $ids = [(int)$_POST['order_id']];
}
$ids = array_values(array_unique(array_filter($ids, function ($v) { return $v > 0; })));

if (!$ids) {
    echo json_encode(['success' => false, 'error' => 'Select at least one order to cancel.']);
    exit;
}
if (count($ids) > 50) {
    echo json_encode(['success' => false, 'error' => 'You can cancel up to 50 orders at a time.']);
    exit;
}

$reason = trim((string)($_POST['reason'] ?? ''));
if ($reason === '') {
    echo json_encode(['success' => false, 'error' => 'Please tell us why you are cancelling.']);
    exit;
}
if (mb_strlen($reason) > 500) {
    echo json_encode(['success' => false, 'error' => 'The reason is too long (500 characters at most).']);
    exit;
}

$cancelled = [];
$failed = [];
foreach ($ids as $id) {
    $r = change_order_status($conn, $id, 'cancelled', $userId, $reason);
    if ($r['ok']) {
        $cancelled[] = order_ref($id);
    } else {
        $failed[] = ['id' => order_ref($id), 'error' => $r['error']];
    }
}

if ($cancelled) {
    echo json_encode([
        'success'   => true,
        'cancelled' => $cancelled,
        'failed'    => $failed,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error'   => $failed[0]['error'] ?? 'Could not cancel the order.',
        'failed'  => $failed,
    ]);
}
