<?php
header("Content-Type: application/json");
include "config.php";
require_once "payment_lib.php";
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
    $reference   = normalize_reference_number($data["reference_number"] ?? "");

    // Phone validation
    if (!preg_match('/^09\d{9}$/', $phone)) {
        throw new Exception(
            "Invalid phone number. Please enter an 11-digit mobile number starting with 09."
        );
    }

    // Basic validation
    if ($total <= 0) {
        throw new Exception("Invalid order total.");
    }
    if (empty($items)) {
        throw new Exception("Your cart is empty.");
    }

    // Payment proof + reference number rules (Cash needs neither)
    if ($payment !== "Cash") {
        if (empty($proof)) {
            throw new Exception("Upload your proof of payment for " . $payment . ".");
        }
        $refError = reference_number_error($payment, $reference);
        if ($refError !== null) {
            throw new Exception($refError);
        }
    } else {
        $reference = "";
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

    // Make sure orders can store the payment reference number. Older databases
    // don't have the column yet; add it here, BEFORE the transaction starts
    // (ALTER TABLE would otherwise commit the transaction early).
    $hasRef = false;
    $cols = $conn->query("SHOW COLUMNS FROM orders LIKE 'reference_number'");
    if ($cols && $cols->num_rows > 0) {
        $hasRef = true;
    } else {
        try {
            $conn->query("ALTER TABLE orders ADD COLUMN reference_number VARCHAR(100) NULL");
            $hasRef = true;
        } catch (Throwable $e) {
            error_log('checkout.php: could not add reference_number column: ' . $e->getMessage());
        }
    }

    // Start transaction
    $conn->begin_transaction();

    // ============================================
    // Check stock and reserve it (row-locked so two
    // simultaneous checkouts can't both oversell the same item)
    // ============================================
    $stockStmt = $conn->prepare("SELECT stock_quantity, product_name FROM products WHERE product_id = ? FOR UPDATE");
    // The "AND stock_quantity >= ?" guard means stock can never drop below zero,
    // even if something else changed it a moment ago.
    $decrementStmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");

    // Merge duplicate product lines and reject zero / negative quantities
    // (a negative quantity would otherwise ADD stock).
    $merged = [];
    foreach ($items as $item) {
        $product_id = intval($item["product_id"] ?? 0);
        $quantity = intval($item["quantity"] ?? 0);
        if ($product_id <= 0 || $quantity < 1) {
            throw new Exception("Invalid quantity in your cart.");
        }
        $merged[$product_id] = ($merged[$product_id] ?? 0) + $quantity;
    }
    ksort($merged); // consistent lock order avoids deadlocks between checkouts

    foreach ($merged as $product_id => $quantity) {
        $stockStmt->bind_param("i", $product_id);
        $stockStmt->execute();
        $stockResult = $stockStmt->get_result();
        $product = $stockResult->fetch_assoc();

        if (!$product) {
            throw new Exception("A product in your cart no longer exists.");
        }
        if ((int)$product["stock_quantity"] < $quantity) {
            throw new Exception(
                "Not enough stock for " . $product["product_name"] .
                " (only " . max(0, (int)$product["stock_quantity"]) . " left)."
            );
        }

        $decrementStmt->bind_param("iii", $quantity, $product_id, $quantity);
        $decrementStmt->execute();
        if ($decrementStmt->affected_rows < 1) {
            throw new Exception("Not enough stock for " . $product["product_name"] . ".");
        }
    }
    $stockStmt->close();
    $decrementStmt->close();

    // Insert order
    if ($hasRef) {
        $stmt = $conn->prepare(
            "INSERT INTO orders
            (user_id, total_amount, status, fulfillment, address, phone, payment_method, proof, reference_number)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idssssss", $user_id, $total, $fulfillment, $address, $phone, $payment, $proof, $reference);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO orders
            (user_id, total_amount, status, fulfillment, address, phone, payment_method, proof)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("idsssss", $user_id, $total, $fulfillment, $address, $phone, $payment, $proof);
    }
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
        $price = floatval($item["price"] ?? 0);

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

?>