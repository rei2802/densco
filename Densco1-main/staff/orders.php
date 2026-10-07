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

try {
    require_once '../config.php';
    require_once '../orders_lib.php';

    // Small summary for the dashboard: any staff role may read it
    if (isset($_GET['summary'])) {
        $row = $conn->query("SELECT COUNT(*) AS c FROM orders WHERE status <> 'completed'")->fetch_assoc();
        json_out(200, [
            'success' => true,
            'active'  => (int)$row['c'],
            'recent'  => fetch_orders($conn, null, 4),
        ]);
    }

    // Only the owner and admin may see / manage customer orders
    if (!in_array($role, ['owner', 'admin'], true)) {
        if ($isApi) json_out(403, ['success' => false, 'error' => 'Not allowed.']);
        header('Location: dashboard.html');
        exit;
    }

    // Update an order's status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in     = json_decode(file_get_contents('php://input'), true);
        $oid    = (int)($in['oid'] ?? 0);
        $status = $in['status'] ?? '';
        if ($oid <= 0 || !in_array($status, ['pending', 'confirmed', 'shipped', 'completed'], true)) {
            json_out(400, ['success' => false, 'error' => 'Invalid order or status.']);
        }
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param("si", $status, $oid);
        $stmt->execute();
        $stmt->close();
        json_out(200, ['success' => true]);
    }

    // List of all customer orders
    if (isset($_GET['json'])) {
        json_out(200, ['success' => true, 'orders' => fetch_orders($conn)]);
    }
} catch (Throwable $e) {
    if ($isApi) json_out(500, ['success' => false, 'error' => 'Server error.']);
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
<body data-page="orders" data-roles="owner admin">
  <div class="app">
    <aside class="sidebar" id="sidebar"></aside>
    <div class="app-main">
      <main>
        <div class="page-head">
          <h1>Order management</h1>
          <p class="muted">Verify payment proofs and update fulfillment status.</p>
        </div>

        <div class="toolbar">
          <input id="q" type="search" placeholder="Search order ID or customer" />
        </div>

        <div class="card flush">
          <table class="tbl">
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Items</th>
                <th>Total</th>
                <th>Fulfillment</th>
                <th>Payment</th>
                <th>Proof</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="rows"></tbody>
          </table>
        </div>

        <div class="modal" id="proofModal">
          <div class="modal-box">
            <div class="modal-head">
              <h3 id="proofTitle">Payment proof</h3>
              <button class="modal-x" data-close>&times;</button>
            </div>
            <div class="proof" id="proofBody"></div>
            <button class="btn btn-line" data-close>Close</button>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script src="../js/store.js"></script>
  <script src="../js/staff-portal.js"></script>
  <script>
    const $ = (id) => document.getElementById(id);
    let term = "";
    let ORDERS = [];
    let loaded = false;

    function statusOption(value, label, current) {
      return `<option value="${value}"${current === value ? " selected" : ""}>${label}</option>`;
    }

    function renderRow(o) {
      const proofCell = o.proof
        ? `<button class="link" data-proof="${o.oid}">View proof</button>`
        : '<span class="muted sm">N/A</span>';

      const shippedLabel = o.fulfillment === "Store Pickup" ? "Ready for pickup" : "Shipped";

      const statusSelect =
        `<select data-status="${o.oid}">` +
        statusOption("pending", "Pending", o.status) +
        statusOption("confirmed", "Confirmed", o.status) +
        statusOption("shipped", shippedLabel, o.status) +
        statusOption("completed", "Completed", o.status) +
        `</select>`;

      const contact = [o.address, o.phone].filter(Boolean).map(DHP.esc).join(" · ");
      const fulfillCell =
        DHP.esc(o.fulfillment) +
        (contact ? `<br><span class="muted sm">${contact}</span>` : "");

      return (
        `<tr>` +
        `<td><strong>${DHP.esc(o.id)}</strong></td>` +
        `<td>${DHP.esc(o.customer)}</td>` +
        `<td>${DHP.esc(o.date)}</td>` +
        `<td>${DHP.esc(o.summary)}</td>` +
        `<td>${DHP.peso(o.total)}</td>` +
        `<td>${fulfillCell}</td>` +
        `<td>${DHP.esc(o.payment)}</td>` +
        `<td>${proofCell}</td>` +
        `<td>${statusSelect}</td>` +
        `</tr>`
      );
    }

    function render() {
      if (!loaded) {
        $("rows").innerHTML = '<tr><td colspan="9"><div class="empty">Loading orders...</div></td></tr>';
        return;
      }
      const t = term.toLowerCase();
      const O = ORDERS.filter(
        (o) => o.id.toLowerCase().includes(t) || o.customer.toLowerCase().includes(t)
      );

      $("rows").innerHTML = O.length
        ? O.map(renderRow).join("")
        : '<tr><td colspan="9"><div class="empty">' +
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
            '<tr><td colspan="9"><div class="empty">Could not load orders. Please refresh.</div></td></tr>';
        });
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    $("rows").addEventListener("click", (e) => {
      const b = e.target.closest("[data-proof]");
      if (!b) return;

      const o = ORDERS.find((x) => String(x.oid) === b.dataset.proof);
      if (!o) return;
      $("proofTitle").textContent = "Payment proof, " + o.id;
      $("proofBody").innerHTML =
        `<div><strong>Uploaded file</strong><p class="muted sm mt">${DHP.esc(o.proof)}</p></div>`;
      $("proofModal").classList.add("open");
    });

    $("rows").addEventListener("change", (e) => {
      const s = e.target.closest("[data-status]");
      if (!s) return;

      const oid = Number(s.dataset.status);
      fetch("orders.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ oid: oid, status: s.value })
      })
        .then((r) => r.json())
        .then((d) => {
          if (!d.success) throw new Error(d.error);
          ORDERS.forEach((o) => {
            if (o.oid === oid) o.status = s.value;
          });
          DHP.toast("Order updated");
        })
        .catch(() => {
          DHP.toast("Could not update the order");
          load();
        });
    });

    render();
    load();
  </script>
</body>
</html>
