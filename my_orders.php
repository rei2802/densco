<?php
// Returns the logged-in customer's own orders as JSON (used by account.html).
header('Content-Type: application/json');
header('Cache-Control: no-store');

session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in again.']);
    exit;
}

try {
    require_once 'config.php';
    require_once 'orders_lib.php';
    echo json_encode([
        'success' => true,
        'orders'  => fetch_orders($conn, (int)$_SESSION['user_id']),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load your orders.']);
}
