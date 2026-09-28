<?php
require_once 'config.php';

$products = [];
$res = $conn->query("SELECT product_id, product_name, category, price, stock_quantity, description, image_path FROM products");
while ($row = $res->fetch_assoc()) {
    $products[] = [
        "id"    => (int)$row["product_id"],
        "name"  => $row["product_name"],
        "cat"   => $row["category"],
        "price" => (float)$row["price"],
        "stock" => (int)$row["stock_quantity"],
        "desc"  => $row["description"],
        "image" => $row["image_path"] ?: null,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Cart and checkout — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>

  <main class="page wrap">

    <div class="page-head">
      <h1>Cart and checkout</h1>
    </div>

    <div class="card empty hide" id="empty">
      Your cart is empty. <a href="catalog.php">Browse the shop</a>
    </div>

    <div class="co hide" id="checkout">
      <div>
        <div class="card">
          <h3>Your items (<span id="count">0</span>)</h3>
          <div id="items"></div>
        </div>

        <div class="card">
          <h3>Fulfillment</h3>
          <div class="opts">
            <label class="opt">
              <input type="radio" name="ful" value="delivery" checked>
              <b>Delivery</b>
              <span id="feeTxt">Metro Manila, 1 to 2 days</span>
            </label>
            <label class="opt">
              <input type="radio" name="ful" value="pickup">
              <b>Store pickup</b>
              <span>Quezon City warehouse, free</span>
            </label>
          </div>

          <div class="field mt hide" id="addrBox">
            <label for="addr">Delivery address</label>
            <textarea id="addr" rows="2" placeholder="Street, barangay, city, province"></textarea>
          </div>

          <div class="field">
            <label for="phone">Contact phone</label>
            <input id="phone" placeholder="09XX XXX XXXX">
          </div>

          <h3 class="mt2">Payment</h3>
          <div class="opts">
            <label class="opt">
              <input type="radio" name="pay" value="GCash / QR PH" checked>
              <b>GCash / QR PH</b>
              <span>Digital payment</span>
            </label>
            <label class="opt">
              <input type="radio" name="pay" value="Bank Transfer">
              <b>Bank transfer</b>
              <span>BDO or BPI deposit</span>
            </label>
            <label class="opt">
              <input type="radio" name="pay" value="Cash">
              <b>Cash</b>
              <span>On pickup or delivery</span>
            </label>
          </div>

          <div class="field mt" id="proofBox">
            <label for="proof">Proof of payment</label>
            <input type="file" id="proof" accept="image/*,.pdf">
            <p class="muted sm" style="margin-top:6px">Upload a screenshot or photo of your receipt or deposit slip. Staff verify it before confirming your order.</p>
          </div>
        </div>
      </div>

      <div class="card sum">
        <h3>Order summary</h3>
        <div id="sum"></div>
        <p class="err mt" id="err"></p>
        <button class="btn btn-red btn-lg btn-block" id="place">Place order</button>
      </div>
    </div>

  </main>

  <footer class="ftr" id="siteFooter"></footer>

  <script>window.DHP_PRODUCTS = <?php echo json_encode($products); ?>;</script>
  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/chatbot.js"></script>
  <script>
    const $ = (id) => document.getElementById(id);
    const val = (n) => document.querySelector(`input[name=${n}]:checked`).value;
    const FEE = DHP.settings().deliveryFee;

    function lines() {
      const P = DHP.products();
      return DHP.cart()
        .map((c) => ({ p: P.find((x) => x.id === c.id), qty: c.qty }))
        .filter((l) => l.p);
    }

    function renderLine(l) {
      const thumb = l.p.image
        ? `<img src="${l.p.image}" alt="" style="display:block;width:100%;height:100%;object-fit:cover">`
        : "Product";

      return (
        `<div class="line">` +
          `<div class="thumb-s" style="overflow:hidden">${thumb}</div>` +
          `<div>` +
            `<div class="ln-name">${DHP.esc(l.p.name)}</div>` +
            `<div class="muted sm">${l.p.cat}, ${DHP.peso(l.p.price)} each</div>` +
          `</div>` +
          `<div class="qty">` +
            `<button data-act="minus" data-id="${l.p.id}">−</button>` +
            `<input value="${l.qty}" readonly>` +
            `<button data-act="plus" data-id="${l.p.id}">+</button>` +
          `</div>` +
          `<div class="right">` +
            `<b>${DHP.peso(l.p.price * l.qty)}</b><br>` +
            `<button class="link" data-act="rm" data-id="${l.p.id}">Remove</button>` +
          `</div>` +
        `</div>`
      );
    }

    function render() {
      const L = lines();
      const del = val("ful") === "delivery";
      const sub = L.reduce((n, l) => n + l.p.price * l.qty, 0);
      const fee = del ? FEE : 0;

      $("empty").classList.toggle("hide", L.length > 0);
      $("checkout").classList.toggle("hide", !L.length);
      $("count").textContent = L.reduce((n, l) => n + l.qty, 0);
      $("feeTxt").textContent = "Metro Manila, 1 to 2 days, " + DHP.peso(FEE);
      $("addrBox").classList.toggle("hide", !del);
      $("proofBox").classList.toggle("hide", val("pay") === "Cash");

      $("items").innerHTML = L.map(renderLine).join("");

      $("sum").innerHTML =
        `<div class="sum-row"><span>Items subtotal</span><span>${DHP.peso(sub)}</span></div>` +
        `<div class="sum-row"><span>Delivery fee</span><span>${DHP.peso(fee)}</span></div>` +
        `<div class="sum-row total"><span>Total</span><span class="red">${DHP.peso(sub + fee)}</span></div>`;
    }

    $("items").addEventListener("click", (e) => {
      const b = e.target.closest("[data-act]");
      if (!b) return;

      const id = +b.dataset.id;
      const P = DHP.products();
      let c = DHP.cart();

      c.forEach((i) => {
        if (i.id === id) {
          const max = P.find((x) => x.id === id).stock;
          if (b.dataset.act === "plus") i.qty = Math.min(max, i.qty + 1);
          if (b.dataset.act === "minus") i.qty = Math.max(1, i.qty - 1);
        }
      });

      if (b.dataset.act === "rm") c = c.filter((i) => i.id !== id);

      DHP.saveCart(c);
      render();
    });

    document.addEventListener("change", (e) => {
      if (e.target.name === "ful" || e.target.name === "pay") render();
    });

    $("place").onclick = async () => {
      const L = lines();
      const del = val("ful") === "delivery";
      const pay = val("pay");
      const file = $("proof").files[0];
      const err = $("err");

      err.textContent = "";

      if (del && !$("addr").value.trim()) {
        err.textContent = "Enter a delivery address.";
        return;
      }

      if (!$("phone").value.trim()) {
        err.textContent = "Enter a contact phone number.";
        return;
      }

      if (pay !== "Cash" && !file) {
        err.textContent = "Upload your proof of payment for " + pay + ".";
        return;
      }

      const sub = L.reduce((s, l) => s + l.p.price * l.qty, 0);
      const total = sub + (del ? FEE : 0);

      const items = L.map((l) => ({
        product_id: l.p.id,
        quantity: l.qty,
        price: l.p.price
      }));

      const orderData = {
        customer: DHP.user(),
        total: total,
        fulfillment: del ? "Delivery" : "Store Pickup",
        address: del ? $("addr").value.trim() : "",
        phone: $("phone").value.trim(),
        payment: pay,
        proof: file ? file.name : null,
        items: items
      };

      try {
        const response = await fetch("checkout.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(orderData)
        });

        const data = await response.json();

        if (!data.success) {
          err.textContent = data.error || "Failed to place order.";
          return;
        }

        DHP.saveCart([]);
        alert("Order placed successfully!\nOrder ID: " + data.order_id);
        location.href = "account.html";
      } catch (error) {
        console.error(error);
        err.textContent = "Something went wrong while placing the order.";
      }
    };

    render();
  </script>
</body>
</html>