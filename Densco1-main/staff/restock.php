<?php
// Restock API (Admin and Inventory Staff): lists products and records a restock, adding the
// quantity to products.stock_quantity (the same value the shop displays).
session_start();

function json_out($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

$role = $_SESSION['staff_role'] ?? null;
if (!$role) json_out(401, ['success' => false, 'error' => 'Please log in again.']);
if (!in_array($role, ['admin', 'inventory'], true)) json_out(403, ['success' => false, 'error' => 'Only the Administrator or Inventory Staff can restock.']);

$inTx = false;

try {
    require_once '../config.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in         = json_decode(file_get_contents('php://input'), true) ?: [];
        $product_id = (int)($in['product_id'] ?? 0);
        $supplier   = trim($in['supplier'] ?? '');
        $quantity   = (int)($in['quantity'] ?? 0);
        $date       = trim($in['date'] ?? '');
        $notes      = trim($in['notes'] ?? '');
        $staff_id   = (int)($_SESSION['staff_id'] ?? 0);

        if ($product_id <= 0)                          json_out(400, ['success' => false, 'error' => 'Select a product.']);
        if ($supplier === '' || strlen($supplier) > 150) json_out(400, ['success' => false, 'error' => 'Enter a supplier name (max 150 characters).']);
        if ($quantity < 1 || $quantity > 1000000)      json_out(400, ['success' => false, 'error' => 'Quantity must be between 1 and 1,000,000.']);
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date)      json_out(400, ['success' => false, 'error' => 'Enter a valid date.']);
        if (strlen($notes) > 1000)                     json_out(400, ['success' => false, 'error' => 'Notes are too long (max 1000 characters).']);

        // Restock history table (created automatically the first time)
        $conn->query("CREATE TABLE IF NOT EXISTS restocks (
            restock_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            supplier_name VARCHAR(150) NOT NULL,
            quantity INT NOT NULL,
            restock_date DATE NOT NULL,
            notes TEXT NULL,
            staff_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $conn->begin_transaction();
        $inTx = true;

        $stmt = $conn->prepare("SELECT product_name, stock_quantity FROM products WHERE product_id = ? AND hidden = 0 FOR UPDATE");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) {
            $conn->rollback();
            json_out(404, ['success' => false, 'error' => 'Product not found.']);
        }

        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        $stmt->bind_param("ii", $quantity, $product_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO restocks (product_id, supplier_name, quantity, restock_date, notes, staff_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isissi", $product_id, $supplier, $quantity, $date, $notes, $staff_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $inTx = false;

        json_out(200, [
            'success' => true,
            'product' => $p['product_name'],
            'stock'   => (int)$p['stock_quantity'] + $quantity,
        ]);
    }

    // Product list for the form: the same products currently listed in the shop (hidden = 0)
    if (isset($_GET['products'])) {
        $products = [];
        $res = $conn->query("SELECT product_id, product_name, category, stock_quantity FROM products WHERE hidden = 0 ORDER BY product_name, product_id");
        while ($r = $res->fetch_assoc()) {
            $products[] = [
                'id'    => (int)$r['product_id'],
                'name'  => $r['product_name'],
                'cat'   => $r['category'],
                'stock' => (int)$r['stock_quantity'],
            ];
        }
        json_out(200, ['success' => true, 'products' => $products]);
    }

    json_out(400, ['success' => false, 'error' => 'Invalid request.']);
} catch (Throwable $e) {
    if ($inTx) { try { $conn->rollback(); } catch (Throwable $x) {} }
    json_out(500, ['success' => false, 'error' => 'Server error.']);
}