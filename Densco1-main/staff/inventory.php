<?php
require_once '../config.php';
require_once __DIR__ . '/../config/cloudinary.php';

use Cloudinary\Api\Upload\UploadApi;
// Suppliers a product can be sourced from, and the default for every product.
// The products.supplier column is added automatically the first time this page runs
// (existing products get the default).
$ALLOWED_SUPPLIERS = ['Densco', 'Omron', 'Topcare'];
$DEFAULT_SUPPLIER  = 'Densco';
$colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'supplier'");
if ($colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN supplier VARCHAR(150) NOT NULL DEFAULT 'Densco'");
}

// Uploads an image to Cloudinary (permanent storage - Render's disk is wiped on every deploy)
// and returns its https URL, null if no file was uploaded, or false if the upload failed
// or the file type isn't allowed. A .jfif file is a JPEG, so it is detected as image/jpeg.
function handleProductImageUpload($fileKey)
{
    if (
        empty($_FILES[$fileKey]) ||
        $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    try {

        $result = (new UploadApi())->upload(
            $_FILES[$fileKey]['tmp_name'],
            [
                'folder' => 'densco/products'
            ]
        );

        return $result['secure_url'];

    } catch (Exception $e) {

        error_log($e->getMessage());
        return false;
    }
}

// ============================================
// GET ?summary=1 — total product count as shown on the Shop (catalog.php),
// i.e. excluding hidden products. Used by the staff dashboards so "Total
// Products" reflects the real database instead of an empty client-side list.
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['summary'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (session_status() === PHP_SESSION_NONE) session_start();

    $res = $conn->query("SELECT COUNT(*) AS c FROM products WHERE hidden = 0");
    $row = $res->fetch_assoc();
    $out = ['success' => true, 'total' => (int)$row['c']];

    // Low / out of stock list (staff only). Same rule as the Inventory page:
    // 0 = out of stock, up to the low-stock level from Settings = low stock.
    if (!empty($_SESSION['staff_role'])) {
        $low = max(1, (int)($_GET['low'] ?? 10));
        $stmt = $conn->prepare(
            "SELECT product_name, category, stock_quantity FROM products
             WHERE hidden = 0 AND stock_quantity <= ?
             ORDER BY stock_quantity ASC, product_name ASC"
        );
        $stmt->bind_param("i", $low);
        $stmt->execute();
        $items = [];
        $r = $stmt->get_result();
        while ($p = $r->fetch_assoc()) {
            $items[] = [
                'name'  => $p['product_name'],
                'cat'   => $p['category'],
                'stock' => max(0, (int)$p['stock_quantity']),
            ];
        }
        $stmt->close();
        $out['low_items'] = $items;
    }
    echo json_encode($out);
    exit;
}

// ============================================
// Handle POST actions from the inline script below:
// adjust stock (JSON), set availability status (JSON), add/edit a product
// (multipart form, since these can include an image file), or delete a
// product (JSON).
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!empty($_POST['action'])) {
        // Multipart form submission (add / edit), possibly with a file in $_FILES['image']
        $action = $_POST['action'];
        $data = $_POST;
    } else {
        // Plain JSON submission (adjust / delete)
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';
    }

    // Allowed product categories (keep in sync with DHP.categories in js/store.js)
    $ALLOWED_CATEGORIES = [
        'Stretcher / Ambulance',
        'Table',
        'Cart',
        'Lighting',
        'Panel Screen',
        'Stool',
        'Sink',
        'OB Accessory',
        'Oxygen Cart',
        'Oxygen Holder',
        'Basin/Pail',
        'Negatoscope',
        'Stretcher',
        'Mayo Stand',
        'Mayo Tray',
        'Cabinet',
        'Food Conveyor',
        'Hospital Bed',
        'OB/Delivery Table',
        'Chair',
        'Bedpan',
        'Chart Holder',
        'IV Stand',
        'Bucket',
        'Hamper',
        'Sterilizer',
        'Baby Bassinet',
        'Baby Crib'
    ];

    // Manual stock status: settable by Admin, Owner and Inventory Staff
    // (this whole page is already restricted to those three roles).
    $ALLOWED_STATUSES = ['in_stock', 'not_available'];
    $MAX_STOCK = 99999; // highest stock a single product can hold

    if ($action === 'set_status') {
        $id     = intval($data['id'] ?? 0);
        $status = trim($data['status'] ?? '');

        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid product.']);
            exit;
        }
        if (!in_array($status, $ALLOWED_STATUSES, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE products SET availability_status = ? WHERE product_id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'status' => $status]);
        $stmt->close();
        exit;
    }

    if ($action === 'adjust') {
        $id = intval($data['id'] ?? 0);
        $delta = intval($data['delta'] ?? 0);

        // GREATEST(...,0) stops stock from going negative
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = LEAST(GREATEST(stock_quantity + ?, 0), ?) WHERE product_id = ?");
        $stmt->bind_param("iii", $delta, $MAX_STOCK, $id);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows >= 0]);
        $stmt->close();
        exit;
    }

    if ($action === 'edit') {
        $id     = intval($data['id'] ?? 0);
        $name   = trim($data['name'] ?? '');
        $cat    = trim($data['cat'] ?? '');
        $price  = floatval($data['price'] ?? 0);
        $desc   = trim($data['desc'] ?? '');
        $hidden = !empty($data['hidden']) ? 1 : 0;

        if (!in_array($cat, $ALLOWED_CATEGORIES, true)) {
            echo json_encode(['success' => false, 'error' => 'Please choose a category from the list.']);
            exit;
        }

        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Product name is required.']);
            exit;
        }

        // Admins can't edit stock, so the front-end omits it from the payload.
        // Keep the existing value in that case instead of zeroing it out.
        // Same goes for availability status when the quick status control
        // on the row was used instead of this form.
        $cur = $conn->prepare("SELECT stock_quantity, image_path, availability_status FROM products WHERE product_id = ?");
        $cur->bind_param("i", $id);
        $cur->execute();
        $curRow = $cur->get_result()->fetch_assoc();
        $cur->close();

        $currentStock = $curRow ? (int)$curRow['stock_quantity'] : 0;
        $addStock = intval($data['add_stock'] ?? 0);
        // Add Stock only adds: negative numbers are not accepted.
        if ($addStock < 0) {
            echo json_encode(['success' => false, 'error' => 'Add Stock cannot be negative. Enter 0 or a positive number.']);
            exit;
        }

        $stock = $currentStock + $addStock;
        if ($stock > $MAX_STOCK) {
            $room = max(0, $MAX_STOCK - $currentStock);
            echo json_encode(['success' => false, 'error' => 'Stock limit is ' . number_format($MAX_STOCK) . ' units per product. You can add at most ' . number_format($room) . ' more.']);
            exit;
        }

        $status = trim($data['status'] ?? '');
        if (!in_array($status, $ALLOWED_STATUSES, true)) {
            $status = $curRow ? $curRow['availability_status'] : 'in_stock';
        }

        $imagePath = handleProductImageUpload('image');
        if ($imagePath === false) {
            echo json_encode(['success' => false, 'error' => 'Image upload failed. Use a JPG, JFIF, PNG, WEBP or GIF file (and check the Cloudinary settings).']);
            exit;
        }
        if ($imagePath === null) {
            if (!empty($data['removeImage'])) {
                $imagePath = null; // explicitly cleared by the "Remove current photo" checkbox
            } else {
                $imagePath = $curRow ? $curRow['image_path'] : null; // keep existing image
            }
        }

        // Type string must have exactly as many characters as bound
        // variables / placeholders below (10 now that supplier and availability_status
        // was added — a mismatch here throws a fatal mysqli_sql_exception
        // and crashes every save with a 500).
        $supplier = trim($data['supplier'] ?? '');
        if (!in_array($supplier, $ALLOWED_SUPPLIERS, true)) {
            echo json_encode(['success' => false, 'error' => 'Please choose a supplier from the list.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, price = ?, stock_quantity = ?, description = ?, hidden = ?, image_path = ?, availability_status = ?, supplier = ? WHERE product_id = ?");
        $stmt->bind_param("ssdisisssi", $name, $cat, $price, $stock, $desc, $hidden, $imagePath, $status, $supplier, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        $stmt->close();
        exit;
    }

    if ($action === 'add') {
        $name  = trim($data['name'] ?? '');
        $cat   = trim($data['cat'] ?? '');
        $price = floatval($data['price'] ?? 0);
        $stock = intval($data['stock'] ?? 0);
        if ($stock < 0) {
            echo json_encode(['success' => false, 'error' => 'Starting stock cannot be negative.']);
            exit;
        }
        if ($stock > $MAX_STOCK) {
            echo json_encode(['success' => false, 'error' => 'Stock limit is ' . number_format($MAX_STOCK) . ' units per product.']);
            exit;
        }
        $desc  = trim($data['desc'] ?? '') ?: 'No description yet.';

        if (!in_array($cat, $ALLOWED_CATEGORIES, true)) {
            echo json_encode(['success' => false, 'error' => 'Please choose a category from the list.']);
            exit;
        }

        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Enter a product name.']);
            exit;
        }

        $status = trim($data['status'] ?? '');
        if (!in_array($status, $ALLOWED_STATUSES, true)) {
            $status = 'in_stock';
        }

        $imagePath = handleProductImageUpload('image');
        if ($imagePath === false) {
            echo json_encode(['success' => false, 'error' => 'Image upload failed. Use a JPG, JFIF, PNG, WEBP or GIF file (and check the Cloudinary settings).']);
            exit;
        }

        $supplier = trim($data['supplier'] ?? '') ?: $DEFAULT_SUPPLIER;
        if (!in_array($supplier, $ALLOWED_SUPPLIERS, true)) {
            echo json_encode(['success' => false, 'error' => 'Please choose a supplier from the list.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO products (product_name, category, price, stock_quantity, description, image_path, availability_status, supplier) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdissss", $name, $cat, $price, $stock, $desc, $imagePath, $status, $supplier);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        $stmt->close();
        exit;
    }

    // NEW: delete a product entirely (and its image file, if any)
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid product.']);
            exit;
        }

        $cur = $conn->prepare("SELECT image_path FROM products WHERE product_id = ?");
        $cur->bind_param("i", $id);
        $cur->execute();
        $curRow = $cur->get_result()->fetch_assoc();
        $cur->close();

        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();

        if ($ok && $curRow && !empty($curRow['image_path'])) {
            $imgFile = __DIR__ . '/../' . $curRow['image_path'];
            if (file_exists($imgFile)) {
                @unlink($imgFile);
            }
        }

        echo json_encode(['success' => $ok, 'error' => $ok ? null : 'Product not found.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// ============================================
// Normal page load: fetch current products for initial render
// ============================================
$products = [];
$res = $conn->query("SELECT product_id, product_name, category, price, stock_quantity, description, hidden, image_path, availability_status, supplier FROM products");
while ($row = $res->fetch_assoc()) {
    $products[] = [
        "id"     => (int)$row["product_id"],
        "name"   => $row["product_name"],
        "cat"    => $row["category"],
        "price"  => (float)$row["price"],
        "stock"  => (int)$row["stock_quantity"],
        "desc"   => $row["description"],
        "hidden" => (bool)$row["hidden"],
        "image"  => $row["image_path"] ?: null,
        "avail"  => $row["availability_status"] ?: "in_stock",
        "supplier" => $row["supplier"] ?: $DEFAULT_SUPPLIER,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inventory — Densco Staff Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">

  
</head>
<body data-page="inventory" data-roles="owner admin inventory">
  <div class="app">
    <aside class="sidebar" id="sidebar"></aside>
    <div class="app-main">
      <main>

        <div class="page-head">
          <h1>Inventory management</h1>
          <p class="muted">Product catalog, stock control and restock requests, synced with online stock.</p>
        </div>

        <div class="toolbar">
          <input id="q" type="search" placeholder="Search products">
        </div>

        <div class="card flush">
          <table class="tbl">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="rows"></tbody>
          </table>
        </div>

        <div class="card" data-roles="inventory">
          <h3>Add new product</h3>
          <div class="row2">
            <div class="field">
              <label>Product name</label>
              <input id="nName" placeholder="e.g. Nebulizer Machine">
            </div>
            <div class="field">
              <label>Category</label>
              <select id="nCat"></select>
            </div>
          </div>
          <div class="row2">
            <div class="field">
              <label>Price (₱)</label>
              <input id="nPrice" type="number" min="0">
            </div>
            <div class="field">
              <label>Starting stock quantity</label>
              <input id="nStock" type="number" min="0" max="99999">
            </div>
          </div>
          <div class="field">
            <label>Supplier</label>
            <select id="nSupplier"></select>
          </div>
          <div class="field">
            <label>Description</label>
            <textarea id="nDesc" rows="2" placeholder="Shown on the product page"></textarea>
          </div>
          <div class="field">
            <label>Product photo</label>
            <input id="nImage" type="file" accept="image/*">
          </div>
          <button class="btn btn-red" id="addBtn">Add product</button>
        </div>

        <div class="modal" id="editModal">
          <div class="modal-box">
            <div class="modal-head">
              <h3>Edit product</h3>
              <button class="modal-x" data-close>&times;</button>
            </div>
            <div class="row2">
              <div class="field">
                <label>Product name</label>
                <input id="eName">
              </div>
              <div class="field">
                <label>Category</label>
                <select id="eCat"></select>
              </div>
            </div>
            <div class="row2">
              <div class="field">
                <label>Price (₱)</label>
                <input id="ePrice" type="number" min="0">
              </div>
              <div class="field">
                <label>Current Stock</label>
                <input id="eStock" type="number" readonly>
              </div>

              <div class="field">
                <label>Add Stock</label>
                <input id="eAddStock" type="number" min="0" max="99999" value="0">
              </div>
            </div>
            <div class="field">
              <label>Supplier</label>
              <select id="eSupplier"></select>
            </div>
            <div class="field">
              <label>Description</label>
              <textarea id="eDesc" rows="3"></textarea>
            </div>
            <div class="field">
              <img id="eImgPreview" src="" alt="" style="max-width:120px;max-height:120px;display:none;border-radius:8px;margin-bottom:8px">
              <label>Product photo (leave blank to keep current)</label>
              <input id="eImage" type="file" accept="image/*">
              <label class="flex" style="margin-top:6px" id="eRemoveImgWrap">
                <input type="checkbox" id="eRemoveImg"> Remove current photo
              </label>
            </div>
            <label class="flex">
              <input type="checkbox" id="eHidden"> Hide from shop
            </label>
            <div class="flex mt" style="justify-content:flex-end">
              <button class="btn btn-line" id="deleteBtn" style="margin-right:auto;color:#c0392b;border-color:#c0392b">Delete product</button>
              <button class="btn btn-line" data-close>Cancel</button>
              <button class="btn btn-red" id="saveBtn">Save changes</button>
            </div>
          </div>
        </div>

        

      </main>
    </div>
  </div>

  <script>window.DHP_PRODUCTS = <?php echo json_encode($products); ?>;</script>
  <script src="../js/store.js"></script>
  <script src="../js/staff-portal.js"></script>
  <script src="../js/restock.js"></script>
  <script>
    DHP.applyRoles(document);

    const $ = (id) => document.getElementById(id);
    const LOW = DHP.settings().lowStock;
    let term = "";
    let editing = null;
    // Images are now full Cloudinary URLs; older ones were local paths
    const imgUrl = (path) => (/^https?:\/\//.test(path) ? path : "../" + path);

    ["nCat", "eCat"].forEach((id) => {
      $(id).innerHTML = DHP.categories.map((c) => `<option>${c}</option>`).join("");
    });

    // Same list as $ALLOWED_SUPPLIERS above; Densco is the default for every product
    const SUPPLIERS = <?php echo json_encode($ALLOWED_SUPPLIERS); ?>;
    ["nSupplier", "eSupplier"].forEach((id) => {
      $(id).innerHTML = SUPPLIERS.map((c) => `<option>${c}</option>`).join("");
    });
    $("nSupplier").value = "<?php echo $DEFAULT_SUPPLIER; ?>";

    async function api(payload) {
      const r = await fetch("inventory.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      return r.json();
    }

    async function apiForm(formData) {
      const r = await fetch("inventory.php", { method: "POST", body: formData });
      return r.json();
    }

    function render() {
      const P = DHP.products().filter((p) => p.name.toLowerCase().includes(term.toLowerCase()));

      $("rows").innerHTML = P.map((p) => {
        const st = DHP.state(p);
        const qtyCell = `${p.stock} units`;
        const editBtn = `<button class="link" data-edit="${p.id}">Edit</button>`;
        // Inventory Staff can always edit, even when the product is low or out of stock
        const action = editBtn;
        const thumb = p.image
          ? `<img src="${imgUrl(p.image)}" alt="" style="display:inline-block;width:40px;height:40px;object-fit:cover;border-radius:6px;margin-right:8px;vertical-align:middle">`
          : "";

        // Status column: dropdown (In Stock / Not Available). A small badge is kept next to it
        // only when the quantity is actually low or out, so staff still see the warning.
        const stockWarn = st === "low" ? ' <span class="badge low">Low Stock</span>'
          : st === "out" ? ' <span class="badge out">Out of Stock</span>' : "";
        const statusBadge =
            st === "out"
              ? '<span class="badge out">Out of Stock</span>'
              : st === "low"
              ? '<span class="badge low">Low Stock</span>'
              : '<span class="badge in">In Stock</span>';
        return `<tr><td>${thumb}<strong>${DHP.esc(p.name)}</strong>${p.hidden ? ' <span class="badge">Hidden</span>' : ""}</td><td>${p.cat}</td><td>${DHP.peso(p.price)}</td><td>${qtyCell}</td><td>${statusBadge}</td><td class="acts">${action}</td></tr>`;
      }).join("") || '<tr><td colspan="6"><div class="empty">No products match your search.</div></td></tr>';
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    // Stock status control — settable here by Owner, Admin and Inventory
    // Staff (this whole page is already restricted to those three roles).
    

    $("rows").addEventListener("click", async (e) => {
      const adj = e.target.closest("[data-adj]");
      if (adj) {
        const id = +adj.dataset.id;
        const delta = +adj.dataset.adj;
        const P = DHP.products();
        P.forEach((p) => {
          if (p.id === id) p.stock = Math.max(0, p.stock + delta);
        });
        render(); // optimistic update
        await api({ action: "adjust", id: id, delta: delta });
        return;
      }

      

      const ed = e.target.closest("[data-edit]");
      if (ed) {
        const p = DHP.products().find((x) => x.id == +ed.dataset.edit);
        editing = p.id;

        $("eName").value = p.name;
        $("eCat").value = p.cat;
        $("ePrice").value = p.price;
        $("eStock").value = p.stock;
        $("eAddStock").value = 0;
        $("eDesc").value = p.desc;
        $("eSupplier").value = p.supplier || "Densco";
        $("eHidden").checked = !!p.hidden;
        $("eImage").value = "";
        $("eRemoveImg").checked = false;

        const prev = $("eImgPreview");
        if (p.image) {
          prev.src = imgUrl(p.image);
          prev.style.display = "inline-block";
          $("eRemoveImgWrap").style.display = "";
        } else {
          prev.style.display = "none";
          $("eRemoveImgWrap").style.display = "none";
        }

        document.getElementById("editModal").classList.add("open");
      }
    });

    const MAX_STOCK = 99999; // keep in sync with $MAX_STOCK above
    // Stock fields only take whole numbers from 0 up (no minus sign, no decimals)
    ["eAddStock", "nStock"].forEach((id) => {
      const el = $(id);
      if (!el) return;
      el.addEventListener("keydown", (e) => {
        if (["-", "+", "e", "E", "."].includes(e.key)) e.preventDefault();
      });
      el.addEventListener("input", () => {
        el.value = el.value.replace(/[^0-9]/g, "").slice(0, 5);
        if (+el.value > MAX_STOCK) el.value = MAX_STOCK;
      });
    });

    $("saveBtn").onclick = async () => {
      const addQty = +$("eAddStock").value || 0;
      if (addQty < 0) {
        DHP.toast("Add Stock cannot be negative");
        return;
      }
      if ((+$("eStock").value || 0) + addQty > MAX_STOCK) {
        DHP.toast("Stock limit is " + MAX_STOCK.toLocaleString() + " units per product");
        return;
      }
      const fd = new FormData();
      fd.append("action", "edit");
      fd.append("id", editing);
      fd.append("name", $("eName").value.trim());
      fd.append("cat", $("eCat").value);
      fd.append("price", +$("ePrice").value || 0);
      fd.append("add_stock", +$("eAddStock").value || 0);
      fd.append("supplier", $("eSupplier").value);
      fd.append("desc", $("eDesc").value);
      fd.append("hidden", $("eHidden").checked ? "1" : "");
      if ($("eImage").files[0]) fd.append("image", $("eImage").files[0]);
      if ($("eRemoveImg").checked) fd.append("removeImage", "1");

      const res = await apiForm(fd);
      if (!res.success) {
        DHP.toast(res.error || "Could not save changes");
        return;
      }

      document.getElementById("editModal").classList.remove("open");
      DHP.toast("Product updated");
      location.reload();
    };

    $("deleteBtn").onclick = async () => {
      if (!confirm("Delete this product? This can't be undone.")) return;

      const res = await api({ action: "delete", id: editing });
      if (!res.success) {
        DHP.toast(res.error || "Could not delete product");
        return;
      }

      document.getElementById("editModal").classList.remove("open");
      DHP.toast("Product deleted");
      location.reload();
    };

    $("addBtn").onclick = async () => {
      if (!$("nName").value.trim()) return DHP.toast("Enter a product name");

      const fd = new FormData();
      fd.append("action", "add");
      fd.append("name", $("nName").value.trim());
      fd.append("cat", $("nCat").value);
      fd.append("price", +$("nPrice").value || 0);
      fd.append("stock", +$("nStock").value || 0);
      fd.append("supplier", $("nSupplier").value);
      fd.append("desc", $("nDesc").value.trim());
      if ($("nImage").files[0]) fd.append("image", $("nImage").files[0]);

      const res = await apiForm(fd);
      if (!res.success) {
        DHP.toast(res.error || "Could not add product");
        return;
      }

      DHP.toast("Product added");
      location.reload();
    };



    render();
  </script>
</body>
</html>