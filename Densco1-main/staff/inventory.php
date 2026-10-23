<?php
require_once '../config.php';

// Uploads an image to Cloudinary (permanent storage - Render's disk is wiped on every deploy)
// and returns its https URL, null if no file was uploaded, or false if the upload failed
// or the file type isn't allowed. A .jfif file is a JPEG, so it is detected as image/jpeg.
function handleProductImageUpload($fileKey) {
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $tmp  = $_FILES[$fileKey]['tmp_name'];
    $mime = mime_content_type($tmp);
    if (!isset($allowed[$mime])) {
        return false;
    }

    $cloud  = getenv('CLOUDINARY_CLOUD_NAME');
    $key    = getenv('CLOUDINARY_API_KEY');
    $secret = getenv('CLOUDINARY_API_SECRET');
    if (!$cloud || !$key || !$secret) {
        return false;
    }

    $timestamp = time();
    $folder    = 'densco/products';
    // Cloudinary signature: sha1 of the sorted params + API secret
    $signature = sha1("folder=$folder&timestamp=$timestamp" . $secret);

    $ch = curl_init("https://api.cloudinary.com/v1_1/$cloud/image/upload");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_POSTFIELDS     => [
            'file'      => new CURLFile($tmp, $mime, 'upload.' . $allowed[$mime]),
            'api_key'   => $key,
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code !== 200) {
        return false;
    }
    $data = json_decode($resp, true);
    return $data['secure_url'] ?? false;
}

// ============================================
// GET ?summary=1 — total product count as shown on the Shop (catalog.php),
// i.e. excluding hidden products. Used by the staff dashboards so "Total
// Products" reflects the real database instead of an empty client-side list.
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['summary'])) {
    header('Content-Type: application/json');
    $res = $conn->query("SELECT COUNT(*) AS c FROM products WHERE hidden = 0");
    $row = $res->fetch_assoc();
    echo json_encode(['success' => true, 'total' => (int)$row['c']]);
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
        $stmt = $conn->prepare("UPDATE products SET stock_quantity = GREATEST(stock_quantity + ?, 0) WHERE product_id = ?");
        $stmt->bind_param("ii", $delta, $id);
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

        $stock = array_key_exists('stock', $data) ? intval($data['stock']) : ($curRow ? (int)$curRow['stock_quantity'] : 0);

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
        // variables / placeholders below (9 now that availability_status
        // was added — a mismatch here throws a fatal mysqli_sql_exception
        // and crashes every save with a 500).
        $stmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, price = ?, stock_quantity = ?, description = ?, hidden = ?, image_path = ?, availability_status = ? WHERE product_id = ?");
        $stmt->bind_param("ssdisissi", $name, $cat, $price, $stock, $desc, $hidden, $imagePath, $status, $id);
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

        $stmt = $conn->prepare("INSERT INTO products (product_name, category, price, stock_quantity, description, image_path, availability_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdisss", $name, $cat, $price, $stock, $desc, $imagePath, $status);
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
$res = $conn->query("SELECT product_id, product_name, category, price, stock_quantity, description, hidden, image_path, availability_status FROM products");
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
              <input id="nStock" type="number" min="0">
            </div>
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
              <div class="field hide">
                <label>Stock quantity</label>
                <input id="eStock" type="number" min="0">
              </div>
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

        <div class="modal" id="restockModal">
          <div class="modal-box">
            <div class="modal-head">
              <h3>Request restock</h3>
              <button class="modal-x" data-close>&times;</button>
            </div>
            <div class="field">
              <label>Product</label>
              <input id="rProduct" readonly>
            </div>
            <div class="field">
              <label>Current stock</label>
              <input id="rStock" readonly>
            </div>
            <div class="field">
              <label>Requested quantity</label>
              <input id="rQty" type="number" min="1" placeholder="e.g. 50">
            </div>
            <div class="field">
              <label>Reason / notes</label>
              <textarea id="rReason" rows="3" placeholder="Why this product needs restocking"></textarea>
            </div>
            <p class="err" id="rErr"></p>
            <div class="flex mt" style="justify-content:flex-end">
              <button class="btn btn-line" data-close>Cancel</button>
              <button class="btn btn-red" id="rSubmit">Submit request</button>
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
    let reqProductId = null;
    // Images are now full Cloudinary URLs; older ones were local paths
    const imgUrl = (path) => (/^https?:\/\//.test(path) ? path : "../" + path);

    ["nCat", "eCat"].forEach((id) => {
      $(id).innerHTML = DHP.categories.map((c) => `<option>${c}</option>`).join("");
    });

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
        const reqBtn = `<button class="btn btn-line btn-sm" data-req="${p.id}">Request restock</button>`;
        const editBtn = `<button class="link" data-edit="${p.id}">Edit</button>`;
        // Inventory Staff can always edit, even when the product is low or out of stock
        const action = (st !== "in")
          ? (DHP.role === "inventory" ? reqBtn + ` <span style="margin-left:10px">${editBtn}</span>` : reqBtn)
          : editBtn;
        const thumb = p.image
          ? `<img src="${imgUrl(p.image)}" alt="" style="display:inline-block;width:40px;height:40px;object-fit:cover;border-radius:6px;margin-right:8px;vertical-align:middle">`
          : "";

        // Status column: dropdown (In Stock / Not Available). A small badge is kept next to it
        // only when the quantity is actually low or out, so staff still see the warning.
        const stockWarn = st === "low" ? ' <span class="badge low">Low Stock</span>'
          : st === "out" ? ' <span class="badge out">Out of Stock</span>' : "";
        const availSel = `<select class="avail-select" data-avail-id="${p.id}">
          <option value="in_stock" ${p.avail !== "not_available" ? "selected" : ""}>In Stock</option>
          <option value="not_available" ${p.avail === "not_available" ? "selected" : ""}>Not Available</option>
        </select>`;

        return `<tr><td>${thumb}<strong>${DHP.esc(p.name)}</strong>${p.hidden ? ' <span class="badge">Hidden</span>' : ""}</td><td>${p.cat}</td><td>${DHP.peso(p.price)}</td><td>${qtyCell}</td><td>${availSel}${stockWarn}</td><td class="acts">${action}</td></tr>`;
      }).join("") || '<tr><td colspan="6"><div class="empty">No products match your search.</div></td></tr>';
    }

    $("q").addEventListener("input", (e) => {
      term = e.target.value;
      render();
    });

    // Stock status control — settable here by Owner, Admin and Inventory
    // Staff (this whole page is already restricted to those three roles).
    $("rows").addEventListener("change", async (e) => {
      const sel = e.target.closest("[data-avail-id]");
      if (!sel) return;

      const id = +sel.dataset.availId;
      const status = sel.value;

      const res = await api({ action: "set_status", id: id, status: status });
      if (!res.success) {
        DHP.toast(res.error || "Could not update status");
        return;
      }

      const p = DHP.products().find((x) => x.id === id);
      if (p) p.avail = status;
      DHP.toast(status === "not_available" ? "Marked Not Available" : "Marked In Stock");
    });

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

      const req = e.target.closest("[data-req]");
      if (req) {
        const id = +req.dataset.req;
        const p = DHP.products().find((x) => x.id === id);
        const R = DHP.restockRequests();

        if (R.some((r) => r.productId === id && (r.status === "pending" || r.status === "admin_approved"))) {
          DHP.toast("A restock request for this product is already in progress");
          return;
        }

        reqProductId = id;
        $("rProduct").value = p.name;
        $("rStock").value = p.stock + " units";
        $("rQty").value = "";
        $("rReason").value = "";
        $("rErr").textContent = "";
        document.getElementById("restockModal").classList.add("open");
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
        $("eDesc").value = p.desc;
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

    $("saveBtn").onclick = async () => {
      const fd = new FormData();
      fd.append("action", "edit");
      fd.append("id", editing);
      fd.append("name", $("eName").value.trim());
      fd.append("cat", $("eCat").value);
      fd.append("price", +$("ePrice").value || 0);
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

    $("rSubmit").onclick = () => {
      const res = DHP.requestRestock(reqProductId, +$("rQty").value, $("rReason").value);
      if (!res.ok) return $("rErr").textContent = res.error;

      document.getElementById("restockModal").classList.remove("open");
      DHP.toast("Restock request sent to Admin for review");
      render();
    };

    render();
  </script>
</body>
</html>