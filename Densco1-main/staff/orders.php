<?php
session_start();

function json_out($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

$isApi = isset($_GET['json']) || isset($_GET['summary']) || $_SERVER['REQUEST_METHOD'] === 'POST';
$role  = $_SESSION['staff_role'] ?? null;

// Must be logged in as staff (real session, checked on the server)
if (!$role) {
    if ($isApi) json_out(401, ['success' => false, 'error' => 'Please log in again.']);
    header('Location: login.php');
    exit;
}

// All three staff roles may review orders and update fulfillment.
if (!in_array($role, ['owner', 'admin', 'inventory'], true)) {
    if ($isApi) json_out(403, ['success' => false, 'error' => 'Not allowed.']);
    header('Location: dashboard.html');
    exit;
}

try {
    require_once '../config.php';
    require_once '../orders_lib.php';

    // Summary for the dashboard: any staff role may read it. All numbers come
    // straight from the orders table.
    if (isset($_GET['summary'])) {
        $all = fetch_orders($conn);                 // newest first
        $count = ['pending' => 0, 'confirmed' => 0, 'shipped' => 0, 'completed' => 0, 'cancelled' => 0];
        $revenue = 0.0;
        $pending = [];
        foreach ($all as $o) {
            if (isset($count[$o['status']])) $count[$o['status']]++;
            if ($o['status'] === 'completed') $revenue += $o['total'];
        }
        // Pending orders that have waited longest come first
        foreach (array_reverse($all) as $o) {
            if ($o['status'] === 'pending') $pending[] = $o;
        }
        $payload = [
            'success'       => true,
            'active'        => $count['pending'] + $count['confirmed'] + $count['shipped'],
            'pending'       => $count['pending'],
            'completed'     => $count['completed'],
            'cancelled'     => $count['cancelled'],
            'pending_list'  => array_slice($pending, 0, 5),
            'recent'        => array_slice($all, 0, 4),
        ];
        // Revenue is only shown to the owner
        if ($role === 'owner') $payload['revenue'] = $revenue;
        json_out(200, $payload);
    }

    // Update an order's status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in     = json_decode(file_get_contents('php://input'), true);
        $oid    = (int)($in['oid'] ?? 0);
        $status = $in['status'] ?? '';
        if ($oid <= 0 || !in_array($status, ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'], true)) {
            json_out(400, ['success' => false, 'error' => 'Invalid order or status.']);
        }

        // Same rules as the customer's cancel button; cancelling returns the stock.
        $r = change_order_status($conn, $oid, $status);
        if (!$r['ok']) {
            json_out($r['code'], ['success' => false, 'error' => $r['error']]);
        }
        json_out(200, ['success' => true]);
    }

    // List of all customer orders
    if (isset($_GET['json'])) {
        json_out(200, ['success' => true, 'orders' => fetch_orders($conn)]);
    }
} catch (Throwable $e) {
    error_log('staff/orders.php: ' . $e->getMessage());
    if ($isApi) json_out(500, ['success' => false, 'error' => 'Server error.', 'detail' => $e->getMessage()]);
    http_response_code(500);
    echo 'Server error.';
    exit;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Orders — Densco Staff Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="../css/style.css" />
</head>
<body data-page="orders" data-roles="owner admin inventory">
  <div class="app">
    <aside class="sidebar" id="sidebar"></aside>
    <div class="app-main">
      <main>
        <div class="page-head">
          <h1>Order management</h1>
          <p class="muted">Review payment references and update fulfillment status.</p>
        </div>

        <div class="toolbar">
          <input id="q" type="search" placeholder="Search order ID or customer" />
        </div>

        <div class="card flush order-list">
          <table class="tbl orders-table" role="table">
            <thead role="rowgroup">
              <tr role="row">
                <th scope="col" role="columnheader">Order</th>
                <th scope="col" role="columnheader">Customer</th>
                <th scope="col" role="columnheader">Date</th>
                <th scope="col" role="columnheader">Items</th>
                <th scope="col" role="columnheader">Total</th>
                <th scope="col" role="columnheader">Fulfillment</th>
                <th scope="col" role="columnheader">Payment</th>
                <th scope="col" role="columnheader">Reference #</th>
                <th scope="col" role="columnheader">Status</th>
              </tr>
            </thead>
            <tbody id="rows" role="rowgroup"></tbody>
          </table>
        </div>

      </main>
    </div>
  </div>

  <script src="../js/store.js"></script>
  <script src="../js/staff-portal.js"></script>
  <script src="../js/popup.js"></script>
  <script>
    const $ = (id) => document.getElementById(id);
    let term = "";
    let ORDERS = [];
    let loaded = false;

    function statusOption(value, label, current) {
      return `<option value="${value}"${current === value ? " selected" : ""}>${label}</option>`;
    }

    function renderRow(o) {
      const shippedLabel = o.fulfillment === "Store Pickup" ? "Ready for pickup" : "Shipped";

      // A cancelled order is final (its stock was already returned), so it is shown
      // locked. "Cancelled" can only be chosen while the order is still pending.
      let statusSelect;
      if (o.status === "cancelled") {
        statusSelect =
          `<select class="status-sel st-cancelled" data-status="${o.oid}" aria-label="Status for ${DHP.esc(o.id)}" disabled title="Cancelled orders can't be changed">` +
          statusOption("cancelled", "Cancelled", o.status) +
          `</select>`;
      } else {
        statusSelect =
          `<select class="status-sel st-${o.status}" data-status="${o.oid}" aria-label="Status for ${DHP.esc(o.id)}">` +
          statusOption("pending", "Pending", o.status) +
          (o.status === "pending" ? statusOption("cancelled", "Cancelled", o.status) : "") +
          statusOption("confirmed", "Confirmed", o.status) +
          statusOption("shipped", shippedLabel, o.status) +
          statusOption("completed", "Completed", o.status) +
          `</select>`;
      }

      const contact = [o.address, o.phone].filter(Boolean).map(DHP.esc).join(" · ");
      const fulfillCell =
        DHP.esc(o.fulfillment) +
        (contact ? `<br><span class="muted sm">${contact}</span>` : "");

      return (
        `<tr role="row">` +
        `<td role="cell" data-label="Order"><strong>${DHP.esc(o.id)}</strong></td>` +
        `<td role="cell" data-label="Customer">${DHP.esc(o.customer)}</td>` +
        `<td role="cell" data-label="Date">${DHP.esc(o.date)}</td>` +
        `<td role="cell" data-label="Items">${DHP.esc(o.summary)}</td>` +
        `<td role="cell" data-label="Total">${DHP.peso(o.total)}</td>` +
        `<td role="cell" data-label="Fulfillment">${fulfillCell}</td>` +
        `<td role="cell" data-label="Payment">${DHP.esc(o.payment)}</td>` +
        `<td role="cell" data-label="Reference #">${o.reference_number ? DHP.esc(o.reference_number) : '<span class="muted sm">N/A</span>'}</td>` +
        `<td role="cell" data-label="Status">${statusSelect}` +
          (o.status === "cancelled" && o.cancel_reason
            ? `<div class="muted sm" style="margin-top:6px;max-width:220px">Reason: ${DHP.esc(o.cancel_reason)}</div>`
            : "") +
        `</td>` +
        `</tr>`
      );
    }

    function render() {
      if (!loaded) {
        $("rows").innerHTML = '<tr role="row"><td role="cell" colspan="9"><div class="empty">Loading orders...</div></td></tr>';
        return;
      }
      const t = term.toLowerCase();
      const O = ORDERS.filter(
        (o) => o.id.toLowerCase().includes(t) || o.customer.toLowerCase().includes(t)
      );

      $("rows").innerHTML = O.length
        ? O.map(renderRow).join("")
        : '<tr role="row"><td role="cell" colspan="9"><div class="empty">' +
          (ORDERS.length ? "No orders match your search." : "No orders yet.") +
          "</div></td></tr>";
    }

    function load() {
      return fetch("orders.php?json=1", { credentials: "same-origin" })
        .then((r) => {
          if (r.status === 401) {
            location.href = "login.php";
            return null;
          }
          return r.json();
        })
        .then((d) => {
          if (!d) return;
          if (!d.success) throw new Error(d.error || "Could not load orders.");
          ORDERS = d.orders;
          loaded = true;
          render();
        })
        .catch((err) => {
          $("rows").innerHTML =
            '<tr role="row"><td role="cell" colspan="9"><div class="empty">Could not load orders. Please refresh.</div></td></tr>';
        });
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    function saveStatus(oid, status) {
      return fetch("orders.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ oid: oid, status: status })
      })
        .then((r) => r.json())
        .then((d) => {
          if (!d.success) throw new Error(d.error || "Could not update the order.");
          // Reload so the table (and any stock-dependent info) matches the server
          return load().then(() => DHP.toast(status === "cancelled" ? "Order cancelled, stock returned" : "Order updated"));
        })
        .catch((err) => {
          DHP.toast(err.message || "Could not update the order");
          return load(); // put the dropdown back to the real status
        });
    }

    $("rows").addEventListener("change", (e) => {
      const s = e.target.closest("[data-status]");
      if (!s) return;

      const oid = Number(s.dataset.status);
      const status = s.value;
      s.className = "status-sel st-" + status;

      if (status !== "cancelled") {
        saveStatus(oid, status);
        return;
      }

      // Cancelling is permanent and returns the items to stock: confirm first.
      const o = ORDERS.find((x) => x.oid === oid) || {};
      const items = [...(o.summary || "").matchAll(/(?:^|,\s)(.+?)\s×(\d+)(?=,\s|$)/g)]
        .map((m) => ({ name: m[1], qty: m[2] }));
      DHP.confirmCancelPopup({
        orderId: o.id || String(oid),
        items: items.length ? items : [{ name: o.summary || "" }],
        onConfirm: () => saveStatus(oid, "cancelled"),
        onKeep: () => load()
      });
    });

    render();
    load();
  </script>
</body>
</html>
