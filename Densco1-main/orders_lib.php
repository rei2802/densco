<?php
// Shared helpers for reading orders from the database.

function order_ref($id) {
    return 'DHP-' . str_pad((string)(int)$id, 5, '0', STR_PAD_LEFT);
}

/**
 * Returns orders (newest first) in the shape the website pages expect.
 * $userId = only that customer's orders (customer account page); null = all orders (staff).
 */
function fetch_orders(mysqli $conn, $userId = null, $limit = null) {
    // Only read optional columns that really exist in this database, so an older
    // table (e.g. without reference_number) can't break the whole order list.
    $have = [];
    $cres = $conn->query("SHOW COLUMNS FROM orders");
    while ($c = $cres->fetch_assoc()) {
        $have[$c['Field']] = true;
    }
    $col = function ($name) use ($have) {
        return isset($have[$name]) ? "o.$name" : "NULL AS $name";
    };

    $sql = "SELECT o.order_id, " . $col('order_date') . ", o.total_amount, o.status, " . $col('fulfillment') . ",
                   " . $col('address') . ", " . $col('phone') . ", " . $col('payment_method') . ", " . $col('proof') . ", " . $col('reference_number') . ", u.full_name AS customer
            FROM orders o
            LEFT JOIN users u ON u.user_id = o.user_id";
    if ($userId !== null) {
        $sql .= " WHERE o.user_id = " . (int)$userId;
    }
    $sql .= " ORDER BY o.order_id DESC";
    if ($limit !== null) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $orders = [];
    $res = $conn->query($sql);
    // The database stores UTC; show dates in Philippine time.
    $utc = new DateTimeZone('UTC');
    $manila = new DateTimeZone('Asia/Manila');

    while ($r = $res->fetch_assoc()) {
        $dt = new DateTime($r['order_date'] ?: 'now', $utc);
        $dt->setTimezone($manila);
        $orders[(int)$r['order_id']] = [
            'oid'         => (int)$r['order_id'],
            'id'          => order_ref($r['order_id']),
            'customer'    => $r['customer'] ?? 'Unknown customer',
            'date'        => $dt->format('M j, Y'),
            'summary'     => '',
            'items'       => 0,
            'total'       => (float)$r['total_amount'],
            'fulfillment' => $r['fulfillment'] ?? '',
            'address'     => $r['address'] ?? '',
            'phone'       => $r['phone'] ?? '',
            'payment'     => $r['payment_method'] ?? '',
            'proof'       => $r['proof'],
            'reference_number' => $r['reference_number'] ?? '',
            'status'      => $r['status'] ?: 'pending',
        ];
    }

    if ($orders) {
        $in = implode(',', array_map('intval', array_keys($orders)));
        $ires = $conn->query(
            "SELECT oi.order_id, oi.quantity, p.product_name
             FROM order_items oi
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id IN ($in)
             ORDER BY oi.order_item_id"
        );
        $parts = [];
        while ($i = $ires->fetch_assoc()) {
            $oid = (int)$i['order_id'];
            $name = $i['product_name'] ?? 'Removed product';
            $parts[$oid][] = $name . ' ×' . (int)$i['quantity'];
            $orders[$oid]['items'] += (int)$i['quantity'];
        }
        foreach ($parts as $oid => $list) {
            $orders[$oid]['summary'] = implode(', ', $list);
        }
    }

    return array_values($orders);
}

/**
 * Changes an order's status safely (used by both the customer cancel button and
 * the staff Orders page, so the rules live in one place).
 *
 *  - Everything runs in one transaction, with the order row locked.
 *  - Only a PENDING order can be cancelled.
 *  - A CANCELLED order is final (its stock was already returned, so it can't be
 *    re-opened without re-reserving stock).
 *  - Cancelling returns every item's quantity to products.stock_quantity, once.
 *
 * $userId: when set, the order must belong to that customer.
 * Returns ['ok' => bool, 'error' => string|null, 'code' => int HTTP-style].
 */
function change_order_status(mysqli $conn, $orderId, $newStatus, $userId = null) {
    $allowed = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'];
    $orderId = (int)$orderId;
    if ($orderId <= 0 || !in_array($newStatus, $allowed, true)) {
        return ['ok' => false, 'error' => 'Invalid order or status.', 'code' => 400];
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT status, user_id FROM orders WHERE order_id = ? FOR UPDATE");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order || ($userId !== null && (int)$order['user_id'] !== (int)$userId)) {
            $conn->rollback();
            return ['ok' => false, 'error' => 'Order not found.', 'code' => 404];
        }

        $current = $order['status'] ?: 'pending';

        if ($current === $newStatus) {
            $conn->commit();
            return ['ok' => true, 'error' => null, 'code' => 200];
        }
        if ($current === 'cancelled') {
            $conn->rollback();
            return ['ok' => false, 'error' => 'This order was cancelled and can no longer be changed.', 'code' => 400];
        }
        if ($newStatus === 'cancelled' && $current !== 'pending') {
            $conn->rollback();
            return ['ok' => false, 'error' => 'Only pending orders can be cancelled. This order is already ' . $current . '.', 'code' => 400];
        }

        $upd = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $upd->bind_param("si", $newStatus, $orderId);
        $upd->execute();
        $upd->close();

        // Cancelled: put the reserved stock back (the order was pending, so it was
        // reserved at checkout and has not been returned before).
        if ($newStatus === 'cancelled') {
            $items = $conn->prepare("SELECT product_id, SUM(quantity) AS qty FROM order_items WHERE order_id = ? GROUP BY product_id");
            $items->bind_param("i", $orderId);
            $items->execute();
            $rows = $items->get_result()->fetch_all(MYSQLI_ASSOC);
            $items->close();

            $restock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
            foreach ($rows as $r) {
                $qty = max(0, (int)$r['qty']);
                $pid = (int)$r['product_id'];
                $restock->bind_param("ii", $qty, $pid);
                $restock->execute();
            }
            $restock->close();
        }

        $conn->commit();
        return ['ok' => true, 'error' => null, 'code' => 200];
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('change_order_status failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not update the order. Please try again.', 'code' => 500];
    }
}

?>