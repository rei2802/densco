<?php
header("Content-Type: application/json");
include "config.php";
session_start();

try {
    // Get JSON data from JavaScript
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["success" => false, "error" => "No order data received."]);
        exit;
    }

    $customer    = trim($data["customer"] ?? "");
    $total       = floatval($data["total"] ?? 0);
    $fulfillment = $data["fulfillment"] ?? "";
    $address     = trim($data["address"] ?? "");
    $phone       = trim($data["phone"] ?? "");
    $payment     = $data["payment"] ?? "";
    $proof       = $data["proof"] ?? null;
    $items       = $data["items"] ?? [];

    // Basic validation
    if ($total <= 0) {
        throw new Exception("Invalid order total.");
    }
    if (empty($items)) {
        throw new Exception("Your cart is empty.");
    }

    // The order belongs to the customer who is logged in (checked on the server)
    $user_id = (int)($_SESSION["user_id"] ?? 0);
    if ($user_id <= 0) {
        throw new Exception("Your session expired. Please log out, log in again, then place your order.");
    }

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Customer account not found. Please log in again.");
    }
    $stmt->close();

    // Start transaction
    $conn->begin_transaction();

    // ============================================
    // Check stock and reserve it (row-locked so two
    // simultaneous checkouts can't both oversell the same item)
    // ============================================
    $stockStmt = $conn->prepare("SELECT stock_quantity, product_name FROM products WHERE product_id = ? FOR UPDATE");
    $decrementStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");

    foreach ($items as $item) {
        $product_id = intval($item["product_id"]);
        $quantity = intval($item["quantity"]);

        $stockStmt->bind_param("i", $product_id);
        $stockStmt->execute();
        $stockResult = $stockStmt->get_result();
        $product = $stockResult->fetch_assoc();

        if (!$product) {
            throw new Exception("A product in your cart no longer exists.");
        }
        if ($product["stock_quantity"] < $quantity) {
            throw new Exception(
                "Not enough stock for " . $product["product_name"] .
                " (only " . $product["stock_quantity"] . " left)."
            );
        }

        $decrementStmt->bind_param("ii", $quantity, $product_id);
        $decrementStmt->execute();
    }
    $stockStmt->close();
    $decrementStmt->close();

    // Insert order
    $stmt = $conn->prepare(
        "INSERT INTO orders
        (user_id, total_amount, status, fulfillment, address, phone, payment_method, proof)
        VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("idsssss", $user_id, $total, $fulfillment, $address, $phone, $payment, $proof);
    $stmt->execute();

    // Get newly created order ID
    $order_id = $conn->insert_id;
    $stmt->close();

    // Insert each cart item
    $stmt = $conn->prepare(
        "INSERT INTO order_items
        (order_id, product_id, quantity, price)
        VALUES (?, ?, ?, ?)"
    );

    foreach ($items as $item) {
        $product_id = intval($item["product_id"]);
        $quantity = intval($item["quantity"]);
        $price = floatval($item["price"]);

        $stmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
        $stmt->execute();
    }
    $stmt->close();

    // Save everything (order + order_items + stock decrement) together
    $conn->commit();

    echo json_encode(["success" => true, "order_id" => "DHP-" . str_pad((string)$order_id, 5, "0", STR_PAD_LEFT)]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();