<?php
// Automatic report generation for the Reports page.
// Builds Sales and Inventory reports straight from the existing tables
// (orders, order_items, products, restocks, users). Nothing is typed in by hand.
//
// Each generator returns a plain array that is stored with the report (as JSON)
// and rendered by staff/reports.php:
//   [ 'kind', 'meta', 'kpis' => [[label, value]...], 'summary' => [sentence...],
//     'sections' => [ ['title','columns','align','rows','empty','note'] ... ] ]
// Money and dates are pre-formatted strings so the report reads the same forever.

require_once __DIR__ . '/orders_lib.php';

const RP_MAX_DAYS = 366;

function rp_peso($n) {
    return '₱' . number_format((float)$n, 2);
}

function rp_int($n) {
    return number_format((int)$n);
}

function rp_day($ymd) {
    $d = DateTime::createFromFormat('Y-m-d', $ymd);
    return $d ? $d->format('M j, Y') : $ymd;
}

function rp_period_label($from, $to) {
    return $from === $to ? rp_day($from) : rp_day($from) . ' – ' . rp_day($to);
}

/** Returns an error message, or null when the range is fine. */
function rp_check_range($from, $to) {
    $a = DateTime::createFromFormat('Y-m-d', (string)$from);
    $b = DateTime::createFromFormat('Y-m-d', (string)$to);
    if (!$a || $a->format('Y-m-d') !== $from || !$b || $b->format('Y-m-d') !== $to) {
        return 'Choose a valid start and end date.';
    }
    if ($a > $b) return 'The start date must be on or before the end date.';
    if ($a->diff($b)->days > RP_MAX_DAYS) return 'Choose a period of one year or less.';
    return null;
}

/** Orders are stored in UTC; the report period is in Philippine time. */
function rp_utc_range($from, $to) {
    $manila = new DateTimeZone('Asia/Manila');
    $utc    = new DateTimeZone('UTC');
    $a = new DateTime($from . ' 00:00:00', $manila);
    $b = new DateTime($to . ' 23:59:59', $manila);
    $a->setTimezone($utc);
    $b->setTimezone($utc);
    return [$a->format('Y-m-d H:i:s'), $b->format('Y-m-d H:i:s')];
}

function rp_now_label() {
    $d = new DateTime('now', new DateTimeZone('Asia/Manila'));
    return $d->format('M j, Y g:i A') . ' (PHT)';
}

function rp_bump(array &$arr, $key, $amount) {
    if (!isset($arr[$key])) $arr[$key] = ['n' => 0, 'v' => 0.0];
    $arr[$key]['n']++;
    $arr[$key]['v'] += $amount;
}

function rp_sort_desc_by(array &$arr, $field) {
    uasort($arr, function ($x, $y) use ($field) {
        return $y[$field] <=> $x[$field];
    });
}

/**
 * A chart is plain data; the browser draws it as SVG (so it also prints).
 * $type: 'bar' = horizontal bars (labelled rows), 'col' = vertical columns (time series).
 * Returns null when there is nothing worth drawing.
 */
function rp_chart($title, $type, array $labels, array $values, array $texts) {
    if (!$values || max($values) <= 0) return null;
    return ['title' => $title, 'type' => $type, 'labels' => array_values($labels),
            'values' => array_values($values), 'texts' => array_values($texts)];
}

// ---------------------------------------------------------------------------
// SALES
// ---------------------------------------------------------------------------
function rp_sales_report(mysqli $conn, $from, $to) {
    [$s, $e] = rp_utc_range($from, $to);
    $manila = new DateTimeZone('Asia/Manila');
    $utc    = new DateTimeZone('UTC');

    $stmt = $conn->prepare(
        "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.fulfillment,
                o.payment_method, u.full_name AS customer
         FROM orders o
         LEFT JOIN users u ON u.user_id = o.user_id
         WHERE o.order_date BETWEEN ? AND ?
         ORDER BY o.order_date, o.order_id"
    );
    $stmt->bind_param('ss', $s, $e);
    $stmt->execute();
    $res = $stmt->get_result();

    $done = [];                 // completed orders = sales
    $open = ['n' => 0, 'v' => 0.0];   // pending / confirmed / shipped
    $daily = []; $pay = []; $ful = []; $cust = [];
    $revenue = 0.0;

    while ($r = $res->fetch_assoc()) {
        $amt = (float)$r['total_amount'];
        if (($r['status'] ?: 'pending') !== 'completed') {
            $open['n']++;
            $open['v'] += $amt;
            continue;
        }
        $dt = new DateTime($r['order_date'], $utc);
        $dt->setTimezone($manila);
        $oid = (int)$r['order_id'];
        $name = $r['customer'] ?? 'Unknown customer';
        $pm = trim((string)$r['payment_method']) !== '' ? $r['payment_method'] : 'Not specified';
        $fl = trim((string)$r['fulfillment']) !== '' ? $r['fulfillment'] : 'Not specified';

        $done[$oid] = [order_ref($oid), $dt->format('M j, Y'), $name, $pm, $fl, rp_peso($amt)];
        $revenue += $amt;
        rp_bump($daily, $dt->format('Y-m-d'), $amt);
        rp_bump($pay, $pm, $amt);
        rp_bump($ful, $fl, $amt);
        rp_bump($cust, $name, $amt);
    }
    $stmt->close();

    // Line items of the completed orders
    $prod = []; $cat = [];
    $units = 0; $itemRevenue = 0.0;
    if ($done) {
        $in = implode(',', array_map('intval', array_keys($done)));
        $ires = $conn->query(
            "SELECT oi.product_id, oi.quantity, oi.price, p.product_name, p.category
             FROM order_items oi
             LEFT JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id IN ($in)"
        );
        while ($i = $ires->fetch_assoc()) {
            $q = (int)$i['quantity'];
            $v = $q * (float)$i['price'];
            $units += $q;
            $itemRevenue += $v;
            $pn = $i['product_name'] ?? 'Removed product';
            $pc = trim((string)$i['category']) !== '' ? $i['category'] : 'Uncategorized';
            $k = (int)$i['product_id'];
            if (!isset($prod[$k])) $prod[$k] = ['name' => $pn, 'cat' => $pc, 'u' => 0, 'v' => 0.0];
            $prod[$k]['u'] += $q;
            $prod[$k]['v'] += $v;
            if (!isset($cat[$pc])) $cat[$pc] = ['u' => 0, 'v' => 0.0];
            $cat[$pc]['u'] += $q;
            $cat[$pc]['v'] += $v;
        }
    }
    rp_sort_desc_by($prod, 'v');
    rp_sort_desc_by($cat, 'v');
    rp_sort_desc_by($pay, 'v');
    rp_sort_desc_by($ful, 'v');
    rp_sort_desc_by($cust, 'v');
    ksort($daily);

    $orderCount = count($done);
    $avg = $orderCount ? $revenue / $orderCount : 0;
    $period = rp_period_label($from, $to);

    // ---- Summary sentences ----
    $summary = [];
    if (!$orderCount) {
        $summary[] = "No completed sales were recorded for $period.";
    } else {
        $summary[] = "For $period, Densco recorded " . rp_int($orderCount) . ' completed order' . ($orderCount === 1 ? '' : 's')
            . ' worth ' . rp_peso($revenue) . ', with ' . rp_int($units) . ' unit' . ($units === 1 ? '' : 's')
            . ' sold and an average order value of ' . rp_peso($avg) . '.';
        if (count($daily) > 1) {
            $bd = $daily;
            rp_sort_desc_by($bd, 'v');
            $bestDay = array_key_first($bd);
            $summary[] = 'The best sales day was ' . rp_day($bestDay) . ' with ' . rp_peso($bd[$bestDay]['v'])
                . ' from ' . rp_int($bd[$bestDay]['n']) . ' order' . ($bd[$bestDay]['n'] === 1 ? '' : 's') . '.';
        }
        if ($prod) {
            $tp = reset($prod);
            $summary[] = 'The top-selling product was ' . $tp['name'] . ' (' . rp_int($tp['u']) . ' unit'
                . ($tp['u'] === 1 ? '' : 's') . ', ' . rp_peso($tp['v']) . ').';
        }
        if (count($cat) > 1) {
            $tc = array_key_first($cat);
            $share = $itemRevenue > 0 ? round($cat[$tc]['v'] / $itemRevenue * 100) : 0;
            $summary[] = "The leading category was $tc, making up about $share% of item sales.";
        }
    }
    if ($open['n']) {
        $summary[] = rp_int($open['n']) . ' order' . ($open['n'] === 1 ? '' : 's') . ' worth ' . rp_peso($open['v'])
            . ' placed in this period ' . ($open['n'] === 1 ? 'is' : 'are')
            . ' still pending, confirmed or shipped, so ' . ($open['n'] === 1 ? 'it is' : 'they are') . ' not counted as sales.';
    }

    // ---- Tables ----
    $sections = [];

    $rows = [];
    foreach ($prod as $p) $rows[] = [$p['name'], $p['cat'], rp_int($p['u']), rp_peso($p['v'])];
    $note = (abs($itemRevenue - $revenue) > 0.009 && $orderCount)
        ? 'Item totals (' . rp_peso($itemRevenue) . ') can differ from order totals (' . rp_peso($revenue)
          . ') when an order has no recorded line items or includes extra charges.'
        : '';
    $sections[] = ['title' => 'Sales by product', 'columns' => ['Product', 'Category', 'Units sold', 'Sales'],
        'align' => ['l', 'l', 'r', 'r'], 'rows' => $rows, 'empty' => 'No products were sold in this period.', 'note' => $note];

    $rows = [];
    foreach ($cat as $name => $c) $rows[] = [$name, rp_int($c['u']), rp_peso($c['v'])];
    $sections[] = ['title' => 'Sales by category', 'columns' => ['Category', 'Units sold', 'Sales'],
        'align' => ['l', 'r', 'r'], 'rows' => $rows, 'empty' => 'No category sales in this period.', 'note' => ''];

    $rows = [];
    foreach ($daily as $day => $d) $rows[] = [rp_day($day), rp_int($d['n']), rp_peso($d['v'])];
    $sections[] = ['title' => 'Daily sales', 'columns' => ['Date', 'Orders', 'Sales'],
        'align' => ['l', 'r', 'r'], 'rows' => $rows, 'empty' => 'No sales in this period.', 'note' => ''];

    $rows = [];
    foreach ($pay as $name => $d) $rows[] = [$name, rp_int($d['n']), rp_peso($d['v'])];
    $sections[] = ['title' => 'Sales by payment method', 'columns' => ['Payment method', 'Orders', 'Sales'],
        'align' => ['l', 'r', 'r'], 'rows' => $rows, 'empty' => 'No sales in this period.', 'note' => ''];

    $rows = [];
    foreach ($ful as $name => $d) $rows[] = [$name, rp_int($d['n']), rp_peso($d['v'])];
    $sections[] = ['title' => 'Sales by fulfillment', 'columns' => ['Fulfillment', 'Orders', 'Sales'],
        'align' => ['l', 'r', 'r'], 'rows' => $rows, 'empty' => 'No sales in this period.', 'note' => ''];

    $rows = [];
    foreach (array_slice($cust, 0, 5, true) as $name => $d) $rows[] = [$name, rp_int($d['n']), rp_peso($d['v'])];
    $sections[] = ['title' => 'Top customers', 'columns' => ['Customer', 'Orders', 'Total spent'],
        'align' => ['l', 'r', 'r'], 'rows' => $rows, 'empty' => 'No customer purchases in this period.', 'note' => ''];

    $list = array_values($done);
    $note = '';
    if (count($list) > 200) {
        $note = 'Showing the first 200 of ' . rp_int(count($list)) . ' completed orders. Totals above include all of them.';
        $list = array_slice($list, 0, 200);
    }
    $sections[] = ['title' => 'Completed orders', 'columns' => ['Order', 'Date', 'Customer', 'Payment', 'Fulfillment', 'Total'],
        'align' => ['l', 'l', 'l', 'l', 'l', 'r'], 'rows' => $list, 'empty' => 'No completed orders in this period.', 'note' => $note];

    // ---- Charts ----
    $charts = [];
    if ($orderCount) {
        $span = (new DateTime($from))->diff(new DateTime($to))->days;
        $byMonth = $span > 62;
        $series = [];
        foreach ($daily as $day => $d) {
            $k = $byMonth ? substr($day, 0, 7) : $day;
            $series[$k] = ($series[$k] ?? 0.0) + $d['v'];
        }
        $cur = new DateTime($from);
        $end = new DateTime($to);
        if ($byMonth) $cur = new DateTime($cur->format('Y-m-01'));
        $fmt = $byMonth ? 'Y-m' : 'Y-m-d';
        $lab = $byMonth ? 'M Y' : 'M j';
        $l = []; $v = []; $t = [];
        while ($cur <= $end) {
            $val = $series[$cur->format($fmt)] ?? 0.0;
            $l[] = $cur->format($lab); $v[] = $val; $t[] = rp_peso($val);
            $cur->modify($byMonth ? '+1 month' : '+1 day');
        }
        $charts[] = rp_chart($byMonth ? 'Monthly sales' : 'Daily sales', 'col', $l, $v, $t);

        $l = []; $v = []; $t = [];
        foreach (array_slice($prod, 0, 8) as $p) { $l[] = $p['name']; $v[] = $p['v']; $t[] = rp_peso($p['v']); }
        $charts[] = rp_chart('Top products by sales', 'bar', $l, $v, $t);

        $l = []; $v = []; $t = [];
        foreach (array_slice($cat, 0, 8, true) as $name => $c) { $l[] = $name; $v[] = $c['v']; $t[] = rp_peso($c['v']); }
        $charts[] = rp_chart('Sales by category', 'bar', $l, $v, $t);
    }

    return [
        'kind'    => 'Sales',
        'meta'    => ['from' => $from, 'to' => $to, 'period' => $period, 'generated' => rp_now_label()],
        'kpis'    => [
            ['Total sales', rp_peso($revenue)],
            ['Completed orders', rp_int($orderCount)],
            ['Units sold', rp_int($units)],
            ['Average order', rp_peso($avg)],
            ['Not yet completed', rp_int($open['n']) . ($open['n'] ? ' (' . rp_peso($open['v']) . ')' : '')],
        ],
        'summary' => $summary,
        'charts'  => array_values(array_filter($charts)),
        'sections' => $sections,
    ];
}

// ---------------------------------------------------------------------------
// INVENTORY
// ---------------------------------------------------------------------------
function rp_inventory_report(mysqli $conn, $from, $to, $low, $levels = true) {
    $low = max(0, min(100000, (int)$low));
    [$s, $e] = rp_utc_range($from, $to);

    // Units sold in the period (completed orders only, same rule as Sales)
    $sold = [];
    $stmt = $conn->prepare(
        "SELECT oi.product_id, SUM(oi.quantity) AS q
         FROM order_items oi
         JOIN orders o ON o.order_id = oi.order_id
         WHERE o.status = 'completed' AND o.order_date BETWEEN ? AND ?
         GROUP BY oi.product_id"
    );
    $stmt->bind_param('ss', $s, $e);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $sold[(int)$r['product_id']] = (int)$r['q'];
    $stmt->close();

    // Restocks received in the period (table may not exist on a fresh database)
    $restocked = []; $restockRows = []; $restockUnits = 0;
    try {
        $stmt = $conn->prepare(
            "SELECT r.product_id, r.restock_date, r.quantity, r.supplier_name, p.product_name
             FROM restocks r
             LEFT JOIN products p ON p.product_id = r.product_id
             WHERE r.restock_date BETWEEN ? AND ?
             ORDER BY r.restock_date, r.restock_id"
        );
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $pid = (int)$r['product_id'];
            $q = (int)$r['quantity'];
            $restocked[$pid] = ($restocked[$pid] ?? 0) + $q;
            $restockUnits += $q;
            $restockRows[] = [rp_day($r['restock_date']), $r['product_name'] ?? 'Removed product', $r['supplier_name'], rp_int($q)];
        }
        $stmt->close();
    } catch (Throwable $ex) {
        $restocked = []; $restockRows = []; $restockUnits = 0;
    }

    // Current stock
    $items = [];
    $res = $conn->query("SELECT product_id, product_name, category, price, stock_quantity, hidden FROM products ORDER BY product_name");
    $totalUnits = 0; $totalValue = 0.0; $nLow = 0; $nOut = 0;
    $cats = [];
    while ($p = $res->fetch_assoc()) {
        $pid = (int)$p['product_id'];
        $stock = max(0, (int)$p['stock_quantity']);
        $price = (float)$p['price'];
        $value = $stock * $price;
        $status = $stock <= 0 ? 'Out of stock' : ($stock <= $low ? 'Low stock' : 'In stock');
        $rank = $stock <= 0 ? 0 : ($stock <= $low ? 1 : 2);
        $cname = trim((string)$p['category']) !== '' ? $p['category'] : 'Uncategorized';

        $totalUnits += $stock;
        $totalValue += $value;
        if ($rank === 0) $nOut++;
        elseif ($rank === 1) $nLow++;
        if (!isset($cats[$cname])) $cats[$cname] = ['n' => 0, 'u' => 0, 'v' => 0.0];
        $cats[$cname]['n']++;
        $cats[$cname]['u'] += $stock;
        $cats[$cname]['v'] += $value;

        $items[] = [
            'pid' => $pid,
            'name' => $p['product_name'] . ((int)$p['hidden'] ? ' (hidden)' : ''),
            'cat' => $cname, 'price' => $price, 'stock' => $stock, 'value' => $value,
            'status' => $status, 'rank' => $rank,
            'sold' => $sold[$pid] ?? 0, 'rest' => $restocked[$pid] ?? 0,
        ];
    }
    ksort($cats);
    usort($items, function ($a, $b) {
        return [$a['rank'], $a['stock'], $a['name']] <=> [$b['rank'], $b['stock'], $b['name']];
    });

    $unitsSold = array_sum($sold);
    $period = rp_period_label($from, $to);
    $nProducts = count($items);

    // ---- Summary sentences ----
    $summary = [];
    if (!$nProducts) {
        $summary[] = 'There are no products in the system yet.';
    } else {
        $summary[] = 'As of ' . rp_now_label() . ', Densco has ' . rp_int($nProducts) . ' product' . ($nProducts === 1 ? '' : 's')
            . ' with ' . rp_int($totalUnits) . ' unit' . ($totalUnits === 1 ? '' : 's') . ' on hand, valued at '
            . rp_peso($totalValue) . ' at selling price.';
        if ($nOut || $nLow) {
            $parts = [];
            if ($nOut) $parts[] = rp_int($nOut) . ' out of stock';
            if ($nLow) $parts[] = rp_int($nLow) . ' low on stock (' . $low . ' units or fewer)';
            $summary[] = 'Attention needed: ' . implode(' and ', $parts) . '.';
        } else {
            $summary[] = "Every product is above the low-stock level of $low units.";
        }
        $summary[] = "For $period, " . rp_int($unitsSold) . ' unit' . ($unitsSold === 1 ? '' : 's') . ' were sold (completed orders) and '
            . rp_int($restockUnits) . ' unit' . ($restockUnits === 1 ? '' : 's') . ' were restocked across '
            . rp_int(count($restockRows)) . ' restock entr' . (count($restockRows) === 1 ? 'y' : 'ies') . '.';
        if ($unitsSold > 0) {
            arsort($sold);
            $topId = array_key_first($sold);
            $q = $conn->prepare("SELECT product_name FROM products WHERE product_id = ?");
            $q->bind_param('i', $topId);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();
            $nm = $row['product_name'] ?? 'Removed product';
            $summary[] = "The fastest-moving product was $nm ({$sold[$topId]} unit" . ($sold[$topId] === 1 ? '' : 's') . ' sold).';
        }
    }

    // ---- Tables ----
    $sections = [];

    $rows = [];
    foreach ($items as $it) {
        if ($it['rank'] < 2) $rows[] = [$it['name'], $it['cat'], rp_int($it['stock']), rp_int($it['sold']), $it['status']];
    }
    $sections[] = ['title' => 'Needs restocking', 'columns' => ['Product', 'Category', 'In stock', 'Sold (period)', 'Status'],
        'align' => ['l', 'l', 'r', 'r', 's'], 'rows' => $rows,
        'empty' => "All products are above the low-stock level of $low units.", 'note' => "Low stock means $low units or fewer."];

    // Optional: the full per-product stock table (the author can leave it out)
    if ($levels) {
        $rows = [];
        foreach ($items as $it) {
            $rows[] = [$it['name'], $it['cat'], rp_peso($it['price']), rp_int($it['stock']), rp_peso($it['value']),
                       rp_int($it['sold']), rp_int($it['rest']), $it['status']];
        }
        $sections[] = ['title' => 'Stock levels', 'columns' => ['Product', 'Category', 'Price', 'Stock', 'Value', 'Sold', 'Restocked', 'Status'],
            'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 's'], 'rows' => $rows,
            'empty' => 'There are no products in the system.',
            'note' => 'Stock and value are the current counts when this report was generated. Sold and Restocked cover the report period only.'];
    }

    $rows = [];
    foreach ($cats as $name => $c) $rows[] = [$name, rp_int($c['n']), rp_int($c['u']), rp_peso($c['v'])];
    $sections[] = ['title' => 'Stock by category', 'columns' => ['Category', 'Products', 'Units on hand', 'Stock value'],
        'align' => ['l', 'r', 'r', 'r'], 'rows' => $rows, 'empty' => 'No categories yet.', 'note' => ''];

    $sections[] = ['title' => 'Restocks received', 'columns' => ['Date', 'Product', 'Supplier', 'Quantity'],
        'align' => ['l', 'l', 'l', 'r'], 'rows' => $restockRows, 'empty' => 'No restocks were logged in this period.', 'note' => ''];

    // ---- Charts ----
    $charts = [];
    if ($nProducts) {
        $charts[] = rp_chart('Products by stock status', 'bar',
            ['In stock', 'Low stock', 'Out of stock'],
            [$nProducts - $nLow - $nOut, $nLow, $nOut],
            [rp_int($nProducts - $nLow - $nOut), rp_int($nLow), rp_int($nOut)]);

        $l = []; $v = []; $t = [];
        $cu = $cats;
        uasort($cu, function ($a, $b) { return $b['u'] <=> $a['u']; });
        foreach (array_slice($cu, 0, 10, true) as $name => $c) { $l[] = $name; $v[] = $c['u']; $t[] = rp_int($c['u']); }
        $charts[] = rp_chart('Units on hand by category', 'bar', $l, $v, $t);

        // Lowest stock first (items are already ordered out -> low -> in, then by quantity)
        $l = []; $v = []; $t = [];
        foreach (array_slice($items, 0, 8) as $it) { $l[] = $it['name']; $v[] = $it['stock']; $t[] = rp_int($it['stock']); }
        $charts[] = rp_chart('Lowest stock products (units left)', 'bar', $l, $v, $t);

        $top = array_filter($items, function ($it) { return $it['sold'] > 0; });
        usort($top, function ($a, $b) { return $b['sold'] <=> $a['sold']; });
        $l = []; $v = []; $t = [];
        foreach (array_slice($top, 0, 8) as $it) { $l[] = $it['name']; $v[] = $it['sold']; $t[] = rp_int($it['sold']); }
        $charts[] = rp_chart('Top sellers this period (units sold)', 'bar', $l, $v, $t);
    }

    return [
        'kind'    => 'Inventory',
        'meta'    => ['from' => $from, 'to' => $to, 'period' => $period, 'low' => $low, 'levels' => (bool)$levels, 'generated' => rp_now_label()],
        'kpis'    => [
            ['Products', rp_int($nProducts)],
            ['Units on hand', rp_int($totalUnits)],
            ['Stock value', rp_peso($totalValue)],
            ['Low stock', rp_int($nLow)],
            ['Out of stock', rp_int($nOut)],
            ['Units sold (period)', rp_int($unitsSold)],
            ['Units restocked (period)', rp_int($restockUnits)],
        ],
        'summary' => $summary,
        'charts'  => array_values(array_filter($charts)),
        'sections' => $sections,
    ];
}

/** Entry point used by reports.php. $kind is 'Sales' or 'Inventory'. */
function rp_generate(mysqli $conn, $kind, $from, $to, $low = 10, $levels = true) {
    if ($kind === 'Sales')     return rp_sales_report($conn, $from, $to);
    if ($kind === 'Inventory') return rp_inventory_report($conn, $from, $to, $low, $levels);
    return null;
}
