<?php

// header('Content-Type: application/json');

// echo json_encode([
//     'success' => true,
//     'received' => $_POST
// ]);

session_start();
require 'config.php';

header('Content-Type: application/json');

$orderId = $_POST['order_id'] ?? '';

if (!$orderId) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing order ID'
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE orders
    SET status = 'cancelled'
    WHERE order_id = ?
    AND status = 'pending'
");

$stmt->bind_param("i", $orderId);

$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode([
        'success' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Order not found or already processed.'
    ]);
}

?>