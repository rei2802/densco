<?php
require_once 'config.php';

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

// Tally units sold per product from order line items, so the homepage
// can surface a real "Best-Selling Products" section.
$soldByProduct = [];
$soldRes = $conn->query(
    "SELECT product_id, SUM(quantity) AS units_sold FROM order_items GROUP BY product_id"
);
while ($soldRow = $soldRes->fetch_assoc()) {
    $soldByProduct[(int)$soldRow["product_id"]] = (int)$soldRow["units_sold"];
}
foreach ($products as &$p) {
    $p["sold"] = $soldByProduct[$p["id"]] ?? 0;
}
unset($p);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Medical Equipment & Supplies — Densco Health Products</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
      href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="css/style.css" />
  </head>
  <body>
    <header class="hdr" id="siteHeader"></header>
    <main class="page wrap">
      <section class="hero">
        <h1>
          Trusted healthcare and medical equipment, delivered from Quezon City
        </h1>
        <p>
          Densco Health Products manufactures and distributes certified supplies
          for hospitals, clinics and home care. Find what you need, check live
          stock and order online.
        </p>
        <form class="search" action="catalog.php">
          <input
            name="q"
            placeholder="Search products, e.g. hospital bed"
            aria-label="Search products"
          /><button class="btn btn-red btn-lg">Search</button>
        </form>
        <div class="chips">
          <span>Browse:</span
          ><a class="chip" href="catalog.php?cat=Hospital%20Bed">Hospital Bed</a
          ><a class="chip" href="catalog.php?cat=Stretcher%20%2F%20Ambulance">Stretcher / Ambulance</a
          ><a class="chip" href="catalog.php?cat=Table">Table</a
          ><a class="chip" href="catalog.php">All products</a>
        </div>
      </section>
      <section id="bestSellersSection" class="bestsellers hide">
        <h2 class="sec">Best-Selling Products</h2>
        <div class="pgrid" id="bestSellersGrid"></div>
      </section>
      <h2 class="sec">Shop by category</h2>
      <div class="grid4 cat-grid" id="cats"></div>
      <div class="cat-more">
        <button class="btn btn-line" id="catToggle" type="button">See all</button>
      </div>
      <h2 class="sec">Business information</h2>
      <div class="grid4" id="biz"></div>
    </main>
    <footer class="ftr" id="siteFooter"></footer>
    <script>window.DHP_PRODUCTS = <?php echo json_encode($products); ?>;</script>
    <script src="js/store.js"></script>
    <script src="js/auth.js"></script>
    <script src="js/chatbot.js"></script>
    <script>
      const P = DHP.products().filter((p) => !p.hidden),
        B = DHP.biz();

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
              `<div class="stock ${DHP.shopState(p)}">${DHP.shopStateLabel(p)}</div>` +
              `<div class="pfoot">` +
                `<span class="price">${DHP.peso(p.price)}</span>` +
                `<button class="btn btn-red btn-sm" data-add="${p.id}" ${DHP.canOrder(p) ? "" : "disabled"}>Add to Cart</button>` +
              `</div>` +
            `</div>` +
          `</article>`
        );
      }

      function renderBestSellers() {
        const bestSellers = DHP.bestSellers(4);
        const section = document.getElementById("bestSellersSection");
        section.classList.toggle("hide", !bestSellers.length);
        if (!bestSellers.length) return;
        document.getElementById("bestSellersGrid").innerHTML =
          bestSellers.map(renderProductCard).join("");
      }
      renderBestSellers();

      document.getElementById("bestSellersGrid").addEventListener("click", (e) => {
        const b = e.target.closest("[data-add]");
        if (!b) return;

        if (!DHP.user()) {
          location.href = "login.php?next=index.php";
          return;
        }

        DHP.addToCart(+b.dataset.add, 1);
        DHP.toast("Added to your cart");
      });

      const CAT_LIMIT = 5;
      let catsExpanded = false;
      function renderCats() {
        const cats = DHP.categories;
        document.getElementById("cats").innerHTML = cats
          .map((c, i) => {
            const n = P.filter((p) => p.cat === c).length;
            const hidden = !catsExpanded && i >= CAT_LIMIT ? " hide" : "";
            return `<a class="tile${hidden}" href="catalog.php?cat=${encodeURIComponent(c)}"><b>${c}</b><span>${n} product${n === 1 ? "" : "s"}</span></a>`;
          })
          .join("");
        const btn = document.getElementById("catToggle");
        btn.classList.toggle("hide", cats.length <= CAT_LIMIT);
        btn.textContent = catsExpanded ? "Show less" : "See all";
      }
      document.getElementById("catToggle").addEventListener("click", () => {
        catsExpanded = !catsExpanded;
        renderCats();
      });
      renderCats();
      document.getElementById("biz").innerHTML = [
        ["Location", B.address],
        ["Opening hours", B.hours],
        ["Contact us", B.contact + " are supported for inquiries."],
        [
          "Ordering",
          "Store pickup or delivery. Pay by GCash / QR PH, bank transfer or cash.",
        ],
      ]
        .map(
          (i) =>
            `<div class="info"><h4>${i[0]}</h4><p>${DHP.esc(i[1])}</p></div>`,
        )
        .join("");
    </script>
  </body>
</html>