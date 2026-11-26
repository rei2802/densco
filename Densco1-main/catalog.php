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
        "sold"  => 0,
    ];
}

// Tally units sold per product from non-cancelled order line items, so the
// Shop can surface a real "Best-Selling Products" section.
$soldByProduct = [];
$soldRes = $conn->query(
    "SELECT oi.product_id, SUM(oi.quantity) AS units_sold
         FROM order_items oi
         JOIN orders o ON o.order_id = oi.order_id
         WHERE o.status <> 'cancelled'
         GROUP BY oi.product_id"
);
while ($soldRow = $soldRes->fetch_assoc()) {
    $soldByProduct[(int)$soldRow["product_id"]] = (int)$soldRow["units_sold"];
}
foreach ($products as &$p) {
    $p["sold"] = $soldByProduct[$p["id"]] ?? 0;
}
unset($p);
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

    <section id="bestSellersSection" class="bestsellers hide">
      <h2 class="sec">Best-Selling Products</h2>
      <div class="pgrid" id="bestSellersGrid"></div>
    </section>

    <div class="shop">
      <aside class="card side">
        <h4>Categories</h4>
        <div id="cats"></div>
      </aside>

      <section>
        <div class="toolbar">
          <input id="q" type="search" placeholder="Search products" aria-label="Search products" style="max-width:none;flex:1">
          <label class="sort">Sort by
            <select id="sort" aria-label="Sort products">
              <option value="">Default</option>
              <option value="best">Best selling</option>
              <option value="price-asc">Price: low to high</option>
              <option value="price-desc">Price: high to low</option>
              <option value="name-asc">Name: A to Z</option>
              <option value="name-desc">Name: Z to A</option>
            </select>
          </label>
          <span class="muted" id="count" style="align-self:center"></span>
        </div>
        <div class="pgrid" id="grid"></div>

        <section id="soldOutSection" class="soldout-section hide">
          <h2 class="sec">Out of Stock</h2>
          <div class="pgrid" id="soldOutGrid"></div>
        </section>
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
    let sort = qs.get("sort") || "";

    const $ = (id) => document.getElementById(id);
    $("q").value = term;
    if (![...$("sort").options].some((o) => o.value === sort)) sort = "";
    $("sort").value = sort;

    const SORTS = {
      "price-asc": (a, b) => a.price - b.price,
      "price-desc": (a, b) => b.price - a.price,
      "name-asc": (a, b) => a.name.localeCompare(b.name),
      "name-desc": (a, b) => b.name.localeCompare(a.name),
      best: (a, b) => (b.sold || 0) - (a.sold || 0),
    };
    const sorted = (list) => (SORTS[sort] ? [...list].sort(SORTS[sort]) : list);

    function renderCategoryButton(c) {
      return `<button class="${c[0] === cat ? "on" : ""}" data-cat="${c[0]}"><span>${c[1]}</span><span>${c[2]}</span></button>`;
    }
    function isSoldOut(p) {
      return !DHP.canOrder(p);
    }

        function renderProductCard(p) {
          const soldOut = isSoldOut(p);
          const thumb = p.image
            ? `<img src="${p.image}" alt="${DHP.esc(p.name)}" style="display:block;width:100%;height:100%;object-fit:cover">`
            : "Product image";

          return (
            `<article class="pcard${soldOut ? " is-soldout" : ""}">` +
              `<a class="pthumb" href="product.php?id=${p.id}" style="display:block;overflow:hidden;aspect-ratio:1/1">${thumb}</a>` +
              `<div class="pbody">` +
                `<div class="pcat">${p.cat}</div>` +
                `<a class="pname" href="product.php?id=${p.id}">${DHP.esc(p.name)}</a>` +
                `<div class="stock ${DHP.state(p)}">${DHP.stateLabel(p)}</div>` +
                `<div class="pfoot">` +
                  `<span class="price">${DHP.peso(p.price)}</span>` +
                  `<button class="btn ${soldOut ? "btn-out-of-stock" : "btn-red"} btn-sm" ${soldOut ? "disabled" : `data-add="${p.id}"`}>${soldOut ? "Out of Stock" : "Add to Cart"}</button>` +
                `</div>` +
              `</div>` +
            `</article>`
          );
        }

    function renderBestSellers() {
      const bestSellers = DHP.bestSellers(8);

      const section = $("bestSellersSection");
      section.classList.toggle("hide", !bestSellers.length);
      if (!bestSellers.length) return;
      $("bestSellersGrid").innerHTML = bestSellers.map(renderProductCard).join("");
    }

    function handleAddToCart(e) {
      const b = e.target.closest("[data-add]");
      if (!b) return;

      if (!DHP.user()) {
        location.href = "login.php?next=catalog.php";
        return;
      }

      const id = +b.dataset.add;
      DHP.cartNotice(id, 1, DHP.addToCart(id, 1));
    }

    function render() {
      const all = DHP.products().filter((p) => !p.hidden);

      const cats = [["", "All products", all.length]].concat(
        DHP.categories.map((c) => [c, c, all.filter((p) => p.cat === c).length])
      );
      $("cats").innerHTML = cats.map(renderCategoryButton).join("");

            const matches = all.filter(
        (p) => (!cat || p.cat === cat) && p.name.toLowerCase().includes(term.toLowerCase())
      );
      const L = sorted(matches.filter((p) => !isSoldOut(p)));
      const S = sorted(matches.filter(isSoldOut));

      $("count").textContent = L.length + " product" + (L.length === 1 ? "" : "s") +
        (S.length ? " · " + S.length + " out of stock" : "");

      $("grid").innerHTML = L.length
        ? L.map(renderProductCard).join("")
        : matches.length
          ? '<div class="card empty" style="grid-column:1/-1">Everything matching your search is currently out of stock.</div>'
          : '<div class="card empty" style="grid-column:1/-1">No products match your search. Try another name or category.</div>';

      $("soldOutSection").classList.toggle("hide", !S.length);
      $("soldOutGrid").innerHTML = S.map(renderProductCard).join("");
      renderBestSellers();
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    $("sort").addEventListener("change", (e) => {
      sort = e.target.value;
      render();
    });

    $("cats").addEventListener("click", (e) => {
      const b = e.target.closest("button");
      if (b) {
        cat = b.dataset.cat;
        render();
      }
    });

    $("grid").addEventListener("click", handleAddToCart);
    $("bestSellersGrid").addEventListener("click", handleAddToCart);
    $("soldOutGrid").addEventListener("click", handleAddToCart);

    render();
  </script>
</body>
</html>