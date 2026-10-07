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
    $sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.fulfillment,
                   o.address, o.phone, o.payment_method, o.proof, u.full_name AS customer
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
        $dt = new DateTime($r['order_date'], $utc);
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
