<?php
session_start();

function json_out($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

$isApi = isset($_GET['json']);
$role  = $_SESSION['staff_role'] ?? null;

// Must be logged in as staff (real session, checked on the server)
if (!$role) {
    if ($isApi) json_out(401, ['success' => false, 'error' => 'Please log in again.']);
    header('Location: login.php');
    exit;
}

// Only the owner and admin may see sales records
if (!in_array($role, ['owner', 'admin'], true)) {
    if ($isApi) json_out(403, ['success' => false, 'error' => 'Not allowed.']);
    header('Location: dashboard.html');
    exit;
}

try {
    require_once '../config.php';
    require_once '../orders_lib.php';

    // A "sale" is a completed, paid transaction — this is the finalized
    // ledger, not the in-progress queue (that's the Orders page). Orders
    // still pending/confirmed/shipped show up here once staff mark them
    // completed on the Orders page.
    if ($isApi) {
        $orders = fetch_orders($conn);
        $sales = array_values(array_filter($orders, function ($o) {
            return $o['status'] === 'completed';
        }));
        json_out(200, ['success' => true, 'sales' => $sales]);
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
  <title>Sales & Records — Densco Staff Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="../css/style.css" />
</head>
<body data-page="sales-records" data-roles="owner admin">
  <div class="app">
    <aside class="sidebar" id="sidebar"></aside>
    <div class="app-main">
      <main>
        <div class="page-head">
          <h1>Sales and records</h1>
          <p class="muted">Completed order transaction history.</p>
        </div>

        <div class="grid4" id="kpis"></div>

        <div class="toolbar">
          <input id="q" type="search" placeholder="Search receipt or order ID" />
        </div>

        <div class="card flush">
          <table class="tbl">
            <thead>
              <tr>
                <th>Receipt / Order #</th>
                <th>Fulfillment</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
              </tr>
            </thead>
            <tbody id="rows"></tbody>
          </table>
        </div>
      </main>
    </div>
  </div>

  <script src="../js/store.js"></script>
  <script src="../js/staff-portal.js"></script>
  <script>
    const $ = (id) => document.getElementById(id);
    let term = "";
    let SALES = [];
    let loaded = false;

    function renderKpis() {
      const total = SALES.reduce((s, r) => s + r.total, 0);
      const pickups = SALES.filter((r) => r.fulfillment === "Store Pickup").length;
      const deliveries = SALES.length - pickups;

      const kpis = [
        ["Total transactions", SALES.length],
        ["Store pickups", pickups],
        ["Deliveries", deliveries],
        ["Total value", DHP.peso(total)]
      ];

      $("kpis").innerHTML = kpis
        .map((k) => `<div class="kpi"><small>${k[0]}</small><b>${k[1]}</b></div>`)
        .join("");
    }

    function renderRow(r) {
      const fulfillLabel = r.fulfillment === "Store Pickup" ? "Store Pickup" : "Delivery";
      const fulfillBadgeClass = r.fulfillment === "Store Pickup" ? "" : "online";

      return (
        `<tr>` +
        `<td><strong>${DHP.esc(r.id)}</strong></td>` +
        `<td><span class="badge ${fulfillBadgeClass}">${fulfillLabel}</span></td>` +
        `<td>${DHP.esc(r.customer)}</td>` +
        `<td>${DHP.esc(r.date)}</td>` +
        `<td>${DHP.esc(r.summary)}</td>` +
        `<td>${DHP.peso(r.total)}</td>` +
        `<td>${DHP.esc(r.payment)}</td>` +
        `</tr>`
      );
    }

    function render() {
      if (!loaded) {
        $("rows").innerHTML = '<tr><td colspan="7"><div class="empty">Loading sales records...</div></td></tr>';
        return;
      }

      const t = term.toLowerCase();
      const L = SALES.filter(
        (r) => r.id.toLowerCase().includes(t) || r.customer.toLowerCase().includes(t)
      );

      $("rows").innerHTML = L.length
        ? L.map(renderRow).join("")
        : '<tr><td colspan="7"><div class="empty">' +
          (SALES.length ? "No records match your search." : "No completed sales yet.") +
          "</div></td></tr>";
    }

    function load() {
      return fetch("sales-records.php?json=1", { credentials: "same-origin" })
        .then((r) => {
          if (r.status === 401) {
            location.href = "login.php";
            return null;
          }
          return r.json();
        })
        .then((d) => {
          if (!d) return;
          if (!d.success) throw new Error(d.error || "Could not load sales records.");
          SALES = d.sales;
          loaded = true;
          renderKpis();
          render();
        })
        .catch(() => {
          $("rows").innerHTML =
            '<tr><td colspan="7"><div class="empty">Could not load sales records. Please refresh.</div></td></tr>';
        });
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    renderKpis();
    render();
    load();
  </script>
</body>
</html>