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
  <title>Product details — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>

  <main class="page wrap">

    <div class="crumb"><a href="index.html">Home</a> / <a href="catalog.php">Shop</a> / <span id="crumbName">Product</span></div>
    <div class="card" id="pd"></div>

  </main>

  <footer class="ftr" id="siteFooter"></footer>

  <script>window.DHP_PRODUCTS = <?php echo json_encode($products); ?>;</script>
  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/chatbot.js"></script>
  <script>
    const params = new URLSearchParams(location.search);
    const p = DHP.products().filter((x) => x.id === +params.get("id") && !x.hidden)[0];
    const box = document.getElementById("pd");

    if (!p) {
      box.innerHTML = '<div class="empty">This product isn\'t available. <a href="catalog.php">Back to the shop</a></div>';
    } else {
      document.getElementById("crumbName").textContent = p.name;
      document.title = p.name + " — Densco Health Products";

      const B = DHP.biz();
      let qty = 1;

      box.className = "card pd";

      const thumb = p.image
        ? `<img src="${p.image}" alt="${DHP.esc(p.name)}" style="display:block;width:100%;height:100%;object-fit:cover">`
        : "Product image";

      box.innerHTML =
        `<div class="pd-img" style="overflow:hidden;aspect-ratio:1/1">${thumb}</div>` +
        `<div>` +
          `<div class="pcat">${p.cat}</div>` +
          `<h1>${DHP.esc(p.name)}</h1>` +
          `<span class="price">${DHP.peso(p.price)}</span>` +
          `<div class="stock ${DHP.state(p)}">${DHP.stateLabel(p)}</div>` +
          `<p class="pd-desc">${DHP.esc(p.desc)}</p>` +
          `<div class="flex"><b>Quantity</b><div class="qty"><button id="m">−</button><input id="q" value="1" readonly><button id="pl">+</button></div></div>` +
          `<div class="flex mt">` +
            `<button class="btn btn-red btn-lg" id="now" ${p.stock <= 0 ? "disabled" : ""}>Order Now</button>` +
            `<button class="btn btn-line btn-lg" id="add" ${p.stock <= 0 ? "disabled" : ""}>Add to Cart</button>` +
          `</div>` +
          `<p class="muted sm mt">Pickup at our Quezon City warehouse or delivery in Metro Manila (1 to 2 days). Pay by GCash / QR PH, bank transfer or cash.</p>` +
        `</div>`;

      const setQty = (n) => {
        qty = Math.max(1, Math.min(p.stock, n));
        document.getElementById("q").value = qty;
      };

      document.getElementById("m").onclick = () => setQty(qty - 1);
      document.getElementById("pl").onclick = () => setQty(qty + 1);

      const buy = (go) => {
        if (!DHP.user()) {
          location.href = "login.php?next=" + encodeURIComponent("product.php?id=" + p.id);
          return;
        }

        DHP.addToCart(p.id, qty);
        if (go) location.href = "cart.php";
        else DHP.toast("Added to your cart");
      };

      document.getElementById("add").onclick = () => buy(false);
      document.getElementById("now").onclick = () => buy(true);
    }
  </script>
</body>
</html>