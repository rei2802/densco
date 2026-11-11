<?php
// Customer cancels one of THEIR OWN pending orders. The stock reserved at
// checkout is returned to the shop in the same transaction.
session_start();
require_once 'config.php';
require_once 'orders_lib.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in again.']);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing order ID']);
    exit;
}

$r = change_order_status($conn, $orderId, 'cancelled', $userId);
echo json_encode($r['ok']
    ? ['success' => true]
    : ['success' => false, 'error' => $r['error']]);
