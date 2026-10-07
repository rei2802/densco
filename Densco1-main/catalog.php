<?php
require_once 'config.php';

// Fetch all products from the database and shape them to match
// the {id, name, cat, price, stock, desc} shape the front-end JS expects.
$products = [];
$res = $conn->query("SELECT product_id, product_name, category, price, stock_quantity, description, image_path FROM products WHERE hidden = 0");
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
  <title>Shop — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>

  <main class="page wrap">

    <div class="crumb"><a href="index.php">Home</a> / <span>Shop</span></div>

    <div class="shop">
      <aside class="card side">
        <h4>Categories</h4>
        <div id="cats"></div>
      </aside>

      <section>
        <div class="toolbar">
          <input id="q" type="search" placeholder="Search products" aria-label="Search products" style="max-width:none;flex:1">
          <span class="muted" id="count" style="align-self:center"></span>
        </div>
        <div class="pgrid" id="grid"></div>
      </section>
    </div>

  </main>

  <footer class="ftr" id="siteFooter"></footer>

  <script>window.DHP_PRODUCTS = <?php echo json_encode($products); ?>;</script>
  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/chatbot.js"></script>
  <script>
    const qs = new URLSearchParams(location.search);
    let cat = qs.get("cat") || "";
    let term = qs.get("q") || "";

    const $ = (id) => document.getElementById(id);
    $("q").value = term;

    function renderCategoryButton(c) {
      return `<button class="${c[0] === cat ? "on" : ""}" data-cat="${c[0]}"><span>${c[1]}</span><span>${c[2]}</span></button>`;
    }

    function renderProductCard(p) {
      const thumb = p.image
        ? `<img src="${p.image}" alt="${DHP.esc(p.name)}" style="display:block;width:100%;height:100%;object-fit:cover">`
        : "Product image";

      return (
        `<article class="pcard">` +
          `<a class="pthumb" href="product.php?id=${p.id}" style="display:block;overflow:hidden;aspect-ratio:1/1">${thumb}</a>` +
          `<div class="pbody">` +
            `<div class="pcat">${p.cat}</div>` +
            `<a class="pname" href="product.php?id=${p.id}">${DHP.esc(p.name)}</a>` +
            `<div class="stock ${DHP.state(p)}">${DHP.stateLabel(p)}</div>` +
            `<div class="pfoot">` +
              `<span class="price">${DHP.peso(p.price)}</span>` +
              `<button class="btn btn-red btn-sm" data-add="${p.id}" ${p.stock <= 0 ? "disabled" : ""}>Add to Cart</button>` +
            `</div>` +
          `</div>` +
        `</article>`
      );
    }

    function render() {
      const all = DHP.products().filter((p) => !p.hidden);

      const cats = [["", "All products", all.length]].concat(
        DHP.categories.map((c) => [c, c, all.filter((p) => p.cat === c).length])
      );
      $("cats").innerHTML = cats.map(renderCategoryButton).join("");

      const L = all.filter(
        (p) => (!cat || p.cat === cat) && p.name.toLowerCase().includes(term.toLowerCase())
      );
      $("count").textContent = L.length + " product" + (L.length === 1 ? "" : "s");

      $("grid").innerHTML = L.length
        ? L.map(renderProductCard).join("")
        : '<div class="card empty" style="grid-column:1/-1">No products match your search. Try another name or category.</div>';
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    $("cats").addEventListener("click", (e) => {
      const b = e.target.closest("button");
      if (b) {
        cat = b.dataset.cat;
        render();
      }
    });

    $("grid").addEventListener("click", (e) => {
      const b = e.target.closest("[data-add]");
      if (!b) return;

      if (!DHP.user()) {
        location.href = "login.php?next=catalog.php";
        return;
      }

      DHP.addToCart(+b.dataset.add, 1);
      DHP.toast("Added to your cart");
    });

    render();
  </script>
</body>
</html>