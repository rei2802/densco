<?php

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/payment_lib.php';
require_once __DIR__ . '/settings_lib.php';

function checkout_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    checkout_response(405, ['success' => false, 'error' => 'Use POST to place an order.']);
}
if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    checkout_response(415, ['success' => false, 'error' => 'Send the order as JSON.']);
}
session_start();
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    checkout_response(401, [
        'success' => false,
        'session_expired' => true,
        'error' => 'Your session expired. Please log in again, then place your order.'
    ]);
}

$transaction = false;
try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) throw new DomainException('No order data received.');

    $fulfillment = $data['fulfillment'] ?? '';
    if (!in_array($fulfillment, ['Delivery', 'Store Pickup'], true)) {
        throw new DomainException('Choose delivery or store pickup.');
    }
    $address = is_string($data['address'] ?? null) ? trim($data['address']) : '';
    if ($fulfillment === 'Delivery' && ($address === '' || mb_strlen($address) > 255)) {
        throw new DomainException('Enter a delivery address of up to 255 characters.');
    }
    if ($fulfillment === 'Store Pickup') $address = '';

    $phone = is_string($data['phone'] ?? null) ? trim($data['phone']) : '';
    if (!preg_match('/^09\d{9}$/D', $phone)) {
        throw new DomainException('Enter a valid 11-digit mobile number starting with 09.');
    }

    $payment = $data['payment'] ?? '';
    if (!in_array($payment, ['GCash / QR PH', 'Cash'], true)) {
        throw new DomainException('Choose GCash / QR PH or Cash as your payment method.');
    }
    $reference = '';
    if ($payment !== 'Cash') {
        $reference = normalize_reference_number(is_string($data['reference_number'] ?? null) ? $data['reference_number'] : '');
        $error = reference_number_error($payment, $reference);
        if ($error !== null) throw new DomainException($error);
    }

    $items = $data['items'] ?? null;
    if (!is_array($items) || !$items) throw new DomainException('Your cart is empty.');
    $merged = [];
    $positiveInteger = ['options' => ['min_range' => 1, 'max_range' => 2147483647]];
    foreach ($items as $item) {
        $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, $positiveInteger);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, $positiveInteger);
        if ($productId === false || $quantity === false) throw new DomainException('Invalid quantity in your cart.');
        $merged[$productId] = ($merged[$productId] ?? 0) + $quantity;
    }
    ksort($merged);

    $throwDatabaseConnectionErrors = true;
    require_once __DIR__ . '/config.php';
    if (!$conn->execute_query('SELECT user_id FROM users WHERE user_id = ?', [$userId])->fetch_assoc()) {
        checkout_response(401, ['success' => false, 'session_expired' => true, 'error' => 'Customer account not found. Please log in again.']);
    }

    // Keep cash checkout working on older databases. Electronic payments require reference storage.
    $hasReference = $conn->query("SHOW COLUMNS FROM orders LIKE 'reference_number'")->num_rows > 0;
    if (!$hasReference && $payment !== 'Cash') {
        try {
            $conn->query('ALTER TABLE orders ADD COLUMN reference_number VARCHAR(100) NULL');
        } catch (mysqli_sql_exception $error) {
            // A concurrent checkout may have added the column.
            if ($error->getCode() !== 1060) throw $error;
        }
        $hasReference = true;
    }

    $conn->begin_transaction();
    $transaction = true;
    $totalCents = $fulfillment === 'Delivery'
        ? (int) round(business_settings($conn)['deliveryFee'] * 100)
        : 0;
    $lines = [];
    $productLookup = $conn->prepare('SELECT stock_quantity, product_name, price, hidden FROM products WHERE product_id = ? FOR UPDATE');
    $productLookup->bind_param('i', $productId);
    foreach ($merged as $productId => $quantity) {
        $productLookup->execute();
        $product = $productLookup->get_result()->fetch_assoc();
        if (!$product || $product['hidden']) throw new DomainException('A product in your cart is no longer available.');
        if ((int) $product['stock_quantity'] < $quantity) {
            throw new DomainException('Not enough stock for ' . $product['product_name'] . ' (only ' . max(0, (int) $product['stock_quantity']) . ' left).');
        }
        $priceCents = (int) round((float) $product['price'] * 100);
        if ($product['price'] === null || $priceCents < 0 || $priceCents > intdiv(PHP_INT_MAX - $totalCents, $quantity)) {
            throw new DomainException('A product price is unavailable. Please contact our staff.');
        }
        $lines[$productId] = $product['price'];
        $totalCents += $priceCents * $quantity;
    }
    if ($totalCents <= 0) throw new DomainException('Invalid order total.');
    if (array_key_exists('quoted_total_cents', $data) && $data['quoted_total_cents'] !== $totalCents) {
        throw new DomainException('The order total changed. Refresh your cart before placing the order. If you already sent payment, contact staff.', 409);
    }

    $total = intdiv($totalCents, 100) . '.' . str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT);
    if ($hasReference) {
        $conn->execute_query(
            "INSERT INTO orders (user_id, total_amount, status, fulfillment, address, phone, payment_method, proof, reference_number)
             VALUES (?, ?, 'pending', ?, ?, ?, ?, NULL, ?)",
            [$userId, $total, $fulfillment, $address, $phone, $payment, $reference]
        );
    } else {
        $conn->execute_query(
            "INSERT INTO orders (user_id, total_amount, status, fulfillment, address, phone, payment_method, proof)
             VALUES (?, ?, 'pending', ?, ?, ?, ?, NULL)",
            [$userId, $total, $fulfillment, $address, $phone, $payment]
        );
    }
    $orderId = $conn->insert_id;
    $reserve = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?');
    $reserve->bind_param('iii', $quantity, $productId, $quantity);
    $orderLine = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
    $orderLine->bind_param('iiis', $orderId, $productId, $quantity, $price);
    foreach ($merged as $productId => $quantity) {
        $price = $lines[$productId];
        $reserve->execute();
        if ($reserve->affected_rows !== 1) throw new RuntimeException('Could not reserve product stock.');
        $orderLine->execute();
    }
    $conn->commit();
    checkout_response(200, ['success' => true, 'order_id' => 'DHP-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT)]);
} catch (DomainException $error) {
    if ($transaction) $conn->rollback();
    checkout_response($error->getCode() === 409 ? 409 : 422, ['success' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    if ($transaction) $conn->rollback();
    error_log('Checkout: ' . $error->getMessage());
    checkout_response(503, ['success' => false, 'error' => 'Checkout is temporarily unavailable. Please try again.']);
}
