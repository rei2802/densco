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
    ];
}
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
      <h2 class="sec">Shop by category</h2>
      <div class="grid4" id="cats"></div>
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
      document.getElementById("cats").innerHTML = DHP.categories
        .map((c) => {
          const n = P.filter((p) => p.cat === c).length;
          return `<a class="tile" href="catalog.php?cat=${encodeURIComponent(c)}"><b>${c}</b><span>${n} product${n === 1 ? "" : "s"}</span></a>`;
        })
        .join("");
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