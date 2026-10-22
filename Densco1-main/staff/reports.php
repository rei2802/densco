<?php
// Reports (Admin, Inventory Staff, Owner).
//  - Admin & Inventory Staff: write reports/documents, attach images, save drafts, print,
//    and submit them to the Owner for review. They only see their own reports.
//  - Owner: sees every SUBMITTED report (never other people's drafts), can approve it or
//    return it for revision with a comment, and can print it.
// This file is both the page and its JSON API (?api=1&action=...).
session_start();

function json_out($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

$isApi = isset($_GET['api']);
$role  = $_SESSION['staff_role'] ?? null;
$myId  = (int)($_SESSION['staff_id'] ?? 0);

if (!$role) {
    if ($isApi) json_out(401, ['success' => false, 'error' => 'Please log in again.']);
    header('Location: login.php');
    exit;
}
if (!in_array($role, ['owner', 'admin', 'inventory'], true)) {
    if ($isApi) json_out(403, ['success' => false, 'error' => 'Not allowed.']);
    header('Location: dashboard.html');
    exit;
}

const REPORT_TYPES    = ['General', 'Inventory', 'Restock', 'Sales', 'Orders', 'Customer Inquiries', 'Incident', 'Other'];
const MAX_IMAGES      = 10;
const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

function ensure_tables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS reports (
        report_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        author_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        report_type VARCHAR(50) NOT NULL DEFAULT 'General',
        period_label VARCHAR(100) NULL,
        report_date DATE NOT NULL,
        body MEDIUMTEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        owner_comment TEXT NULL,
        submitted_at DATETIME NULL,
        reviewed_at DATETIME NULL,
        reviewed_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY author_id (author_id),
        KEY status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS report_images (
        image_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_id INT NOT NULL,
        mime VARCHAR(50) NOT NULL,
        data LONGBLOB NOT NULL,
        caption VARCHAR(255) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        KEY report_id (report_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function can_view($row, $role, $myId) {
    if ($role === 'owner') return $row['status'] !== 'draft';
    return (int)$row['author_id'] === $myId;
}

function report_row($conn, $id) {
    $stmt = $conn->prepare(
        "SELECT r.*, a.first_name AS a_first, a.last_name AS a_last, a.role AS a_role,
                v.first_name AS v_first, v.last_name AS v_last
         FROM reports r
         LEFT JOIN staff a ON a.staff_id = r.author_id
         LEFT JOIN staff v ON v.staff_id = r.reviewed_by
         WHERE r.report_id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function shape($r, $myId) {
    $author   = trim(($r['a_first'] ?? '') . ' ' . ($r['a_last'] ?? ''));
    $reviewer = trim(($r['v_first'] ?? '') . ' ' . ($r['v_last'] ?? ''));
    return [
        'id'         => (int)$r['report_id'],
        'title'      => $r['title'],
        'type'       => $r['report_type'],
        'period'     => $r['period_label'] ?? '',
        'date'       => $r['report_date'],
        'status'     => $r['status'],
        'comment'    => $r['owner_comment'] ?? '',
        'author'     => $author !== '' ? $author : 'Unknown',
        'authorRole' => $r['a_role'] ?? '',
        'mine'       => (int)$r['author_id'] === $myId,
        'submitted'  => $r['submitted_at'],
        'reviewed'   => $r['reviewed_at'],
        'reviewer'   => $reviewer,
        'updated'    => $r['updated_at'],
    ];
}

function full_report($conn, $id, $myId) {
    $r = report_row($conn, $id);
    if (!$r) return null;
    $out = shape($r, $myId);
    $out['body'] = $r['body'] ?? '';
    $out['images'] = [];
    $stmt = $conn->prepare("SELECT image_id, caption FROM report_images WHERE report_id = ? ORDER BY sort_order, image_id");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($i = $res->fetch_assoc()) {
        $out['images'][] = ['id' => (int)$i['image_id'], 'caption' => $i['caption'] ?? ''];
    }
    $stmt->close();
    return $out;
}

function valid_date($s) {
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

if ($isApi) {
    $inTx = false;
    try {
        require_once '../config.php';
        ensure_tables($conn);
        $action = $_GET['action'] ?? '';

        // ---- Image (streamed, with the same access check as the report) ----
        if ($action === 'image') {
            $iid = (int)($_GET['id'] ?? 0);
            $stmt = $conn->prepare(
                "SELECT i.mime, i.data, r.author_id, r.status
                 FROM report_images i JOIN reports r ON r.report_id = i.report_id
                 WHERE i.image_id = ?"
            );
            $stmt->bind_param("i", $iid);
            $stmt->execute();
            $img = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$img || !can_view($img, $role, $myId)) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: ' . $img['mime']);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, max-age=3600');
            echo $img['data'];
            exit;
        }

        // ---- List ----
        if ($action === 'list') {
            if ($role === 'owner') {
                $res = $conn->query(
                    "SELECT r.*, a.first_name AS a_first, a.last_name AS a_last, a.role AS a_role,
                            v.first_name AS v_first, v.last_name AS v_last
                     FROM reports r
                     LEFT JOIN staff a ON a.staff_id = r.author_id
                     LEFT JOIN staff v ON v.staff_id = r.reviewed_by
                     WHERE r.status <> 'draft'
                     ORDER BY (r.status = 'submitted') DESC, COALESCE(r.submitted_at, r.updated_at) DESC
                     LIMIT 500"
                );
            } else {
                $stmt = $conn->prepare(
                    "SELECT r.*, a.first_name AS a_first, a.last_name AS a_last, a.role AS a_role,
                            v.first_name AS v_first, v.last_name AS v_last
                     FROM reports r
                     LEFT JOIN staff a ON a.staff_id = r.author_id
                     LEFT JOIN staff v ON v.staff_id = r.reviewed_by
                     WHERE r.author_id = ?
                     ORDER BY r.updated_at DESC, r.report_id DESC
                     LIMIT 500"
                );
                $stmt->bind_param("i", $myId);
                $stmt->execute();
                $res = $stmt->get_result();
            }
            $list = [];
            while ($r = $res->fetch_assoc()) $list[] = shape($r, $myId);
            json_out(200, ['success' => true, 'role' => $role, 'name' => (string)($_SESSION['staff_name'] ?? ''), 'types' => REPORT_TYPES, 'reports' => $list]);
        }

        // ---- Single report ----
        if ($action === 'get') {
            $id = (int)($_GET['id'] ?? 0);
            $r  = report_row($conn, $id);
            if (!$r || !can_view($r, $role, $myId)) json_out(404, ['success' => false, 'error' => 'Report not found.']);
            json_out(200, ['success' => true, 'report' => full_report($conn, $id, $myId)]);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(400, ['success' => false, 'error' => 'Invalid request.']);
        $in = json_decode(file_get_contents('php://input'), true) ?: [];

        // ---- Save (create or update a draft) — Admin / Inventory only ----
        if ($action === 'save') {
            if ($role === 'owner') json_out(403, ['success' => false, 'error' => 'The Owner reviews reports and cannot write them.']);

            $id     = (int)($in['id'] ?? 0);
            $title  = trim((string)($in['title'] ?? ''));
            $type   = (string)($in['type'] ?? 'General');
            $period = trim((string)($in['period'] ?? ''));
            $date   = trim((string)($in['date'] ?? ''));
            $body   = (string)($in['body'] ?? '');
            $images = is_array($in['images'] ?? null) ? $in['images'] : [];

            if ($title === '' || mb_strlen($title) > 200)      json_out(400, ['success' => false, 'error' => 'Enter a report title (max 200 characters).']);
            if (!in_array($type, REPORT_TYPES, true))          json_out(400, ['success' => false, 'error' => 'Choose a report type.']);
            if (mb_strlen($period) > 100)                      json_out(400, ['success' => false, 'error' => 'The period is too long (max 100 characters).']);
            if (!valid_date($date))                            json_out(400, ['success' => false, 'error' => 'Enter a valid report date.']);
            if (mb_strlen($body) > 50000)                      json_out(400, ['success' => false, 'error' => 'The report text is too long (max 50,000 characters).']);
            if (count($images) > MAX_IMAGES)                   json_out(400, ['success' => false, 'error' => 'You can attach up to ' . MAX_IMAGES . ' images.']);

            // Decode and validate new images before touching the database
            $prepared = [];
            foreach ($images as $n => $im) {
                $caption = mb_substr(trim((string)($im['caption'] ?? '')), 0, 255);
                $imgId   = (int)($im['id'] ?? 0);
                if ($imgId > 0) {
                    $prepared[] = ['id' => $imgId, 'caption' => $caption, 'order' => $n];
                    continue;
                }
                $data = (string)($im['data'] ?? '');
                if (!preg_match('#^data:image/(?:jpeg|png|gif|webp);base64,([A-Za-z0-9+/=\s]+)$#', $data, $m)) {
                    json_out(400, ['success' => false, 'error' => 'One of the images is not a supported format (JPG, PNG, GIF or WEBP).']);
                }
                $bin = base64_decode($m[1], true);
                if ($bin === false || $bin === '' || strlen($bin) > MAX_IMAGE_BYTES) {
                    json_out(400, ['success' => false, 'error' => 'Each image must be under 4 MB.']);
                }
                $info = @getimagesizefromstring($bin);
                if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                    json_out(400, ['success' => false, 'error' => 'One of the images could not be read.']);
                }
                $prepared[] = ['id' => 0, 'bin' => $bin, 'mime' => $info['mime'], 'caption' => $caption, 'order' => $n];
            }

            $conn->begin_transaction();
            $inTx = true;

            if ($id > 0) {
                $stmt = $conn->prepare("SELECT author_id, status FROM reports WHERE report_id = ? FOR UPDATE");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $cur = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$cur || (int)$cur['author_id'] !== $myId) {
                    $conn->rollback(); $inTx = false;
                    json_out(404, ['success' => false, 'error' => 'Report not found.']);
                }
                if (!in_array($cur['status'], ['draft', 'returned'], true)) {
                    $conn->rollback(); $inTx = false;
                    json_out(409, ['success' => false, 'error' => 'This report was already submitted. Recall it first to edit it.']);
                }
                $stmt = $conn->prepare("UPDATE reports SET title = ?, report_type = ?, period_label = ?, report_date = ?, body = ? WHERE report_id = ?");
                $stmt->bind_param("sssssi", $title, $type, $period, $date, $body, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO reports (author_id, title, report_type, period_label, report_date, body, status) VALUES (?, ?, ?, ?, ?, ?, 'draft')");
                $stmt->bind_param("isssss", $myId, $title, $type, $period, $date, $body);
                $stmt->execute();
                $id = (int)$conn->insert_id;
                $stmt->close();
            }

            // Existing images still in the report
            $keep = [];
            foreach ($prepared as $p) if ($p['id'] > 0) $keep[] = $p['id'];
            $existing = [];
            $stmt = $conn->prepare("SELECT image_id FROM report_images WHERE report_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($e = $res->fetch_assoc()) $existing[] = (int)$e['image_id'];
            $stmt->close();

            foreach ($existing as $eid) {
                if (!in_array($eid, $keep, true)) {
                    $stmt = $conn->prepare("DELETE FROM report_images WHERE image_id = ? AND report_id = ?");
                    $stmt->bind_param("ii", $eid, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            foreach ($prepared as $p) {
                if ($p['id'] > 0) {
                    if (!in_array($p['id'], $existing, true)) continue; // not this report's image
                    $stmt = $conn->prepare("UPDATE report_images SET caption = ?, sort_order = ? WHERE image_id = ? AND report_id = ?");
                    $stmt->bind_param("siii", $p['caption'], $p['order'], $p['id'], $id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO report_images (report_id, mime, data, caption, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssi", $id, $p['mime'], $p['bin'], $p['caption'], $p['order']);
                }
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $inTx = false;
            json_out(200, ['success' => true, 'report' => full_report($conn, $id, $myId)]);
        }

        // ---- Submit / recall / delete — author only ----
        if (in_array($action, ['submit', 'recall', 'delete'], true)) {
            if ($role === 'owner') json_out(403, ['success' => false, 'error' => 'Not allowed.']);
            $id = (int)($in['id'] ?? 0);
            $r  = report_row($conn, $id);
            if (!$r || (int)$r['author_id'] !== $myId) json_out(404, ['success' => false, 'error' => 'Report not found.']);

            if ($action === 'submit') {
                if (!in_array($r['status'], ['draft', 'returned'], true)) json_out(409, ['success' => false, 'error' => 'This report was already submitted.']);
                $hasBody = trim((string)$r['body']) !== '';
                $cnt = 0;
                $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM report_images WHERE report_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $cnt = (int)$stmt->get_result()->fetch_assoc()['c'];
                $stmt->close();
                if (!$hasBody && $cnt === 0) json_out(400, ['success' => false, 'error' => 'Add some text or an image before submitting.']);
                $stmt = $conn->prepare("UPDATE reports SET status = 'submitted', submitted_at = NOW(), owner_comment = NULL, reviewed_at = NULL, reviewed_by = NULL WHERE report_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            } elseif ($action === 'recall') {
                if ($r['status'] !== 'submitted') json_out(409, ['success' => false, 'error' => 'Only a report awaiting review can be recalled.']);
                $stmt = $conn->prepare("UPDATE reports SET status = 'draft', submitted_at = NULL WHERE report_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
            } else {
                if (!in_array($r['status'], ['draft', 'returned'], true)) json_out(409, ['success' => false, 'error' => 'Recall the report before deleting it.']);
                $conn->begin_transaction();
                $inTx = true;
                $stmt = $conn->prepare("DELETE FROM report_images WHERE report_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("DELETE FROM reports WHERE report_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                $inTx = false;
                json_out(200, ['success' => true]);
            }
            json_out(200, ['success' => true, 'report' => full_report($conn, $id, $myId)]);
        }

        // ---- Review — Owner only ----
        if ($action === 'review') {
            if ($role !== 'owner') json_out(403, ['success' => false, 'error' => 'Only the Owner can review reports.']);
            $id       = (int)($in['id'] ?? 0);
            $decision = (string)($in['decision'] ?? '');
            $comment  = trim((string)($in['comment'] ?? ''));
            if (!in_array($decision, ['approved', 'returned'], true)) json_out(400, ['success' => false, 'error' => 'Choose approve or return.']);
            if ($decision === 'returned' && $comment === '')          json_out(400, ['success' => false, 'error' => 'Add a comment so the author knows what to change.']);
            if (mb_strlen($comment) > 1000)                           json_out(400, ['success' => false, 'error' => 'The comment is too long (max 1000 characters).']);

            $r = report_row($conn, $id);
            if (!$r || $r['status'] === 'draft') json_out(404, ['success' => false, 'error' => 'Report not found.']);
            if ($r['status'] !== 'submitted')    json_out(409, ['success' => false, 'error' => 'This report is not awaiting review.']);

            $stmt = $conn->prepare("UPDATE reports SET status = ?, owner_comment = ?, reviewed_at = NOW(), reviewed_by = ? WHERE report_id = ?");
            $stmt->bind_param("ssii", $decision, $comment, $myId, $id);
            $stmt->execute();
            $stmt->close();
            json_out(200, ['success' => true, 'report' => full_report($conn, $id, $myId)]);
        }

        json_out(400, ['success' => false, 'error' => 'Invalid request.']);
    } catch (Throwable $e) {
        if ($inTx) { try { $conn->rollback(); } catch (Throwable $x) {} }
        json_out(500, ['success' => false, 'error' => 'Server error.']);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Reports — Densco Staff Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />
  <link rel="stylesheet" href="../css/style.css" />
  <style>
    .modal-box.wide { max-width: 900px; }
    .rp-paper { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 32px 36px; }
    .rp-head { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid var(--red); padding-bottom: 14px; margin-bottom: 18px; }
    .rp-head img { height: 44px; width: auto; }
    .rp-head b { display: block; font-size: 15px; letter-spacing: .04em; color: var(--ink); }
    .rp-head span { font-size: 12px; color: var(--mute); }
    .rp-title { font-size: 24px; margin-bottom: 12px; }
    .rp-meta { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 18px; }
    .rp-meta td { padding: 5px 8px; border: 1px solid var(--line); }
    .rp-meta td:nth-child(odd) { background: #FAFAF9; font-weight: 600; width: 14%; white-space: nowrap; }
    .rp-body { white-space: pre-wrap; word-wrap: break-word; font-size: 14px; line-height: 1.65; color: var(--body); }
    .rp-figs { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 22px; }
    .rp-figs figure { margin: 0; break-inside: avoid; page-break-inside: avoid; }
    .rp-figs img { width: 100%; max-height: 360px; object-fit: contain; border: 1px solid var(--line); border-radius: 6px; background: #FAFAF9; display: block; }
    .rp-figs figcaption { font-size: 12px; color: var(--mute); margin-top: 4px; text-align: center; }
    .rp-sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 44px; font-size: 12px; color: var(--mute); }
    .rp-sign div { border-top: 1px solid #94A3B8; padding-top: 5px; }
    .rp-note { border-left: 4px solid var(--red); background: var(--red-l); padding: 10px 14px; border-radius: 6px; font-size: 13.5px; margin-bottom: 16px; }
    .rp-note b { display: block; margin-bottom: 2px; }
    .rp-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; margin-top: 18px; }
    .rp-review { border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-top: 18px; background: #FAFAF9; }
    .rp-imgs { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; margin-top: 10px; }
    .rp-img { border: 1px solid var(--line); border-radius: 8px; padding: 8px; background: #fff; }
    .rp-img img { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; display: block; margin-bottom: 6px; }
    .rp-img input { font-size: 12.5px; padding: 6px 8px; margin-bottom: 6px; }
    .rp-body-in { min-height: 260px; resize: vertical; line-height: 1.55; }
    .badge.draft { background: #F1F5F9; color: #334155; }
    .rp-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; }
    #printArea { display: none; }

    @media (max-width: 640px) {
      .rp-paper { padding: 20px 16px; }
      .rp-figs, .rp-sign { grid-template-columns: 1fr; }
      .rp-meta td:nth-child(odd) { width: auto; }
    }

    @media print {
      @page { margin: 16mm; }
      body.printing { background: #fff !important; }
      body.printing .app,
      body.printing .modal { display: none !important; }
      body.printing #printArea { display: block !important; }
      body.printing #printArea .rp-paper { border: none; padding: 0; border-radius: 0; }
      body.printing #printArea .rp-head,
      body.printing #printArea .rp-meta td,
      body.printing #printArea .rp-meta td:nth-child(odd) { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body data-page="reports" data-roles="owner admin inventory">
  <div class="app">
    <aside class="sidebar" id="sidebar"></aside>
    <div class="app-main">
      <main>
        <div class="page-head">
          <div>
            <h1>Reports</h1>
            <p class="muted" id="sub"></p>
          </div>
          <button class="btn btn-red" id="newBtn" data-roles="admin inventory">New report</button>
        </div>

        <div class="grid4" id="kpis"></div>

        <div class="toolbar mt">
          <input id="q" type="search" placeholder="Search title, type or author" />
          <select id="statusFilter">
            <option value="">All statuses</option>
            <option value="draft" data-roles="admin inventory">Draft</option>
            <option value="submitted">Awaiting review</option>
            <option value="approved">Approved</option>
            <option value="returned">Returned for revision</option>
          </select>
        </div>

        <div class="card flush">
          <table class="tbl">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th id="thAuthor">Prepared by</th>
                <th>Date</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="rows"></tbody>
          </table>
        </div>
      </main>
    </div>
  </div>

  <!-- Viewer -->
  <div class="modal" id="viewModal">
    <div class="modal-box wide">
      <div class="modal-head">
        <h3>Report</h3>
        <button class="modal-x" data-close aria-label="Close">&times;</button>
      </div>
      <div id="viewDoc"></div>
      <div id="viewReview"></div>
      <p class="err" id="viewErr"></p>
      <div class="rp-actions" id="viewActions"></div>
    </div>
  </div>

  <!-- Editor -->
  <div class="modal" id="editModal">
    <div class="modal-box wide">
      <div class="modal-head">
        <h3 id="editHead">New report</h3>
        <button class="modal-x" id="editX" aria-label="Close">&times;</button>
      </div>
      <div id="editNote"></div>
      <div class="field">
        <label for="fTitle">Report title</label>
        <input id="fTitle" maxlength="200" placeholder="e.g. Weekly inventory count" />
      </div>
      <div class="row2">
        <div class="field">
          <label for="fType">Report type</label>
          <select id="fType"></select>
        </div>
        <div class="field">
          <label for="fDate">Report date</label>
          <input id="fDate" type="date" />
        </div>
      </div>
      <div class="field">
        <label for="fPeriod">Period covered <span class="muted">(optional)</span></label>
        <input id="fPeriod" maxlength="100" placeholder="e.g. October 13–19, 2026" />
      </div>
      <div class="field">
        <label for="fBody">Report content</label>
        <textarea id="fBody" class="rp-body-in" maxlength="50000" placeholder="Write the report here. Line breaks and spacing are kept when printed."></textarea>
      </div>
      <div class="field">
        <label>Images <span class="muted">(up to 10 — JPG, PNG, GIF or WEBP)</span></label>
        <div class="rp-tools">
          <button type="button" class="btn btn-line btn-sm" id="addImgBtn">Upload images</button>
          <input id="fImages" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden />
        </div>
        <div class="rp-imgs" id="imgList"></div>
      </div>
      <p class="err" id="editErr"></p>
      <div class="rp-actions">
        <button class="btn btn-line" id="editCancel">Cancel</button>
        <button class="btn btn-line" id="editPrint">Print</button>
        <button class="btn btn-dark" id="editSave">Save draft</button>
        <button class="btn btn-red" id="editSubmit">Save &amp; submit to Owner</button>
      </div>
    </div>
  </div>

  <div id="printArea"></div>

  <script src="../js/store.js"></script>
  <script src="../js/staff-portal.js"></script>
  <script>
    (function () {
      const $ = (id) => document.getElementById(id);
      const esc = DHP.esc;
      const API = "reports.php?api=1";
      const IMG = (id) => API + "&action=image&id=" + id;
      const STATUS = {
        draft: ["Draft", "draft"],
        submitted: ["Awaiting review", "pending"],
        approved: ["Approved", "completed"],
        returned: ["Returned", "out"]
      };

      let ROLE = DHP.role;
      let NAME = "";
      let REPORTS = [];
      let TYPES = [];
      let loaded = false;
      let current = null; // report open in the viewer
      let ED = null;      // editor state
      let dirty = false;
      let busy = false;

      const isOwner = () => ROLE === "owner";

      // ---------- helpers ----------
      function call(action, payload) {
        const opt = { credentials: "same-origin" };
        if (payload) {
          opt.method = "POST";
          opt.headers = { "Content-Type": "application/json" };
          opt.body = JSON.stringify(payload);
        }
        return fetch(API + "&action=" + action, opt).then((r) => {
          if (r.status === 401) {
            location.href = "login.php";
            return new Promise(() => {});
          }
          return r.json().catch(() => ({ success: false, error: "Server error." }));
        });
      }

      function fmtDate(s) {
        if (!s) return "";
        const d = new Date(String(s).replace(" ", "T"));
        if (isNaN(d)) return s;
        return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric" });
      }

      function fmtDateTime(s) {
        if (!s) return "";
        const d = new Date(String(s).replace(" ", "T"));
        if (isNaN(d)) return s;
        return d.toLocaleString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
      }

      function today() {
        const d = new Date();
        const m = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return d.getFullYear() + "-" + m + "-" + day;
      }

      function badge(s) {
        const x = STATUS[s] || [s, ""];
        return `<span class="badge ${x[1]}">${x[0]}</span>`;
      }

      function upsert(rep) {
        const i = REPORTS.findIndex((r) => r.id === rep.id);
        if (i >= 0) REPORTS[i] = rep;
        else REPORTS.unshift(rep);
      }

      // ---------- document rendering (viewer + print) ----------
      // d: {title,type,period,date,body,status,author,reviewer,reviewed,images:[{src,caption}]}
      function docHtml(d) {
        const rows = [
          ["Type", d.type],
          ["Report date", fmtDate(d.date)],
          ["Period", d.period || "—"],
          ["Prepared by", d.author || "—"],
          ["Status", (STATUS[d.status] || [d.status])[0]]
        ];
        if (d.status === "approved" && d.reviewer) rows.push(["Approved by", d.reviewer + (d.reviewed ? ", " + fmtDate(d.reviewed) : "")]);
        let meta = "";
        for (let i = 0; i < rows.length; i += 2) {
          const a = rows[i], b = rows[i + 1];
          meta += `<tr><td>${a[0]}</td><td>${esc(a[1])}</td>` + (b ? `<td>${b[0]}</td><td>${esc(b[1])}</td>` : `<td></td><td></td>`) + `</tr>`;
        }
        const figs = (d.images || []).length
          ? `<div class="rp-figs">` +
            d.images.map((im, n) =>
              `<figure><img src="${esc(im.src)}" alt="Figure ${n + 1}">` +
              `<figcaption>Figure ${n + 1}${im.caption ? ": " + esc(im.caption) : ""}</figcaption></figure>`
            ).join("") + `</div>`
          : "";
        return (
          `<div class="rp-paper">` +
          `<div class="rp-head"><img src="../assets/logonobg.png" alt=""><div><b>DENSCO HEALTH PRODUCTS</b><span>Staff report</span></div></div>` +
          `<h2 class="rp-title">${esc(d.title || "Untitled report")}</h2>` +
          `<table class="rp-meta">${meta}</table>` +
          `<div class="rp-body">${esc(d.body || "")}</div>` +
          figs +
          `<div class="rp-sign"><div>Prepared by: ${esc(d.author || "")}</div><div>Reviewed by (Owner)</div></div>` +
          `</div>`
        );
      }

      function reportToDoc(r) {
        return Object.assign({}, r, {
          images: (r.images || []).map((i) => ({ src: IMG(i.id), caption: i.caption }))
        });
      }

      function printDoc(d) {
        const area = $("printArea");
        area.innerHTML = docHtml(d);
        const imgs = Array.from(area.querySelectorAll("img"));
        const ready = Promise.all(
          imgs.map((im) => (im.complete ? Promise.resolve() : new Promise((res) => { im.onload = im.onerror = res; })))
        );
        const timeout = new Promise((res) => setTimeout(res, 4000));
        Promise.race([ready, timeout]).then(() => {
          const prevTitle = document.title;
          document.title = (d.title || "Report").replace(/[\\/:*?"<>|]/g, "-");
          document.body.classList.add("printing");
          const done = () => {
            document.body.classList.remove("printing");
            document.title = prevTitle;
            area.innerHTML = "";
            window.removeEventListener("afterprint", done);
          };
          window.addEventListener("afterprint", done);
          window.print();
        });
      }

      // ---------- list ----------
      function renderKpis() {
        const c = (s) => REPORTS.filter((r) => r.status === s).length;
        const k = isOwner()
          ? [["Awaiting your review", c("submitted")], ["Approved", c("approved")], ["Returned", c("returned")], ["Total received", REPORTS.length]]
          : [["Drafts", c("draft")], ["Awaiting review", c("submitted")], ["Approved", c("approved")], ["Returned", c("returned")]];
        $("kpis").innerHTML = k.map((x) => `<div class="kpi"><small>${x[0]}</small><b>${x[1]}</b></div>`).join("");
      }

      function renderRows() {
        const body = $("rows");
        const cols = 6;
        if (!loaded) {
          body.innerHTML = `<tr><td colspan="${cols}"><div class="empty">Loading reports...</div></td></tr>`;
          return;
        }
        const t = $("q").value.trim().toLowerCase();
        const sf = $("statusFilter").value;
        const L = REPORTS.filter(
          (r) =>
            (!sf || r.status === sf) &&
            (!t || (r.title + " " + r.type + " " + r.author).toLowerCase().includes(t))
        );
        if (!L.length) {
          body.innerHTML =
            `<tr><td colspan="${cols}"><div class="empty">` +
            (REPORTS.length
              ? "No reports match your search."
              : isOwner()
                ? "No reports have been submitted to you yet."
                : "You haven't written any reports yet. Click <b>New report</b> to start.") +
            `</div></td></tr>`;
          return;
        }
        body.innerHTML = L.map((r) => {
          const editable = r.mine && (r.status === "draft" || r.status === "returned");
          return (
            `<tr>` +
            `<td><strong>${esc(r.title)}</strong></td>` +
            `<td>${esc(r.type)}</td>` +
            `<td>${esc(r.author)}</td>` +
            `<td>${fmtDate(r.date)}</td>` +
            `<td>${badge(r.status)}</td>` +
            `<td style="text-align:right;white-space:nowrap">` +
            `<button class="btn btn-line btn-sm" data-view="${r.id}">${isOwner() && r.status === "submitted" ? "Review" : "View"}</button> ` +
            (editable ? `<button class="btn btn-dark btn-sm" data-edit="${r.id}">Edit</button>` : "") +
            `</td></tr>`
          );
        }).join("");
      }

      function renderAll() {
        renderKpis();
        renderRows();
      }

      function load() {
        return call("list")
          .then((d) => {
            if (!d.success) throw new Error(d.error);
            ROLE = d.role;
            NAME = d.name || "";
            TYPES = d.types;
            REPORTS = d.reports;
            loaded = true;
            renderAll();
          })
          .catch(() => {
            $("rows").innerHTML = '<tr><td colspan="6"><div class="empty">Could not load reports. Please refresh.</div></td></tr>';
          });
      }

      // ---------- viewer ----------
      function openView(id) {
        $("viewErr").textContent = "";
        $("viewDoc").innerHTML = '<div class="empty">Loading...</div>';
        $("viewReview").innerHTML = "";
        $("viewActions").innerHTML = "";
        $("viewModal").classList.add("open");
        call("get&id=" + id).then((d) => {
          if (!d.success) {
            $("viewDoc").innerHTML = `<div class="empty">${esc(d.error || "Could not load the report.")}</div>`;
            return;
          }
          current = d.report;
          renderView();
        });
      }

      function renderView() {
        const r = current;
        let note = "";
        if (r.comment && (r.status === "returned" || r.status === "approved")) {
          note =
            `<div class="rp-note"><b>${r.status === "returned" ? "Returned for revision" : "Owner's comment"}` +
            `${r.reviewer ? " — " + esc(r.reviewer) : ""}${r.reviewed ? ", " + fmtDate(r.reviewed) : ""}</b>${esc(r.comment)}</div>`;
        }
        $("viewDoc").innerHTML = note + docHtml(reportToDoc(r));

        // Owner review panel
        if (isOwner() && r.status === "submitted") {
          $("viewReview").innerHTML =
            `<div class="rp-review"><div class="field" style="margin-bottom:0">` +
            `<label for="rvComment">Comment <span class="muted">(required when returning for revision)</span></label>` +
            `<textarea id="rvComment" rows="3" maxlength="1000" placeholder="Tell ${esc(r.author)} what looks good or what to change."></textarea>` +
            `</div></div>`;
        } else {
          $("viewReview").innerHTML = "";
        }

        const A = [`<button class="btn btn-line" data-close>Close</button>`, `<button class="btn btn-line" id="vPrint">Print</button>`];
        if (isOwner() && r.status === "submitted") {
          A.push(`<button class="btn btn-line" id="vReturn">Return for revision</button>`);
          A.push(`<button class="btn btn-red" id="vApprove">Approve</button>`);
        }
        if (r.mine) {
          if (r.status === "submitted") A.push(`<button class="btn btn-line" id="vRecall">Recall to edit</button>`);
          if (r.status === "draft" || r.status === "returned") {
            A.push(`<button class="btn btn-line" id="vDelete">Delete</button>`);
            A.push(`<button class="btn btn-dark" id="vEdit">Edit</button>`);
            A.push(`<button class="btn btn-red" id="vSubmit">Submit to Owner</button>`);
          }
        }
        $("viewActions").innerHTML = A.join("");
      }

      function viewAction(fn) {
        if (busy) return;
        busy = true;
        $("viewErr").textContent = "";
        fn().then((d) => {
          busy = false;
          if (!d) return;
          if (!d.success) {
            $("viewErr").textContent = d.error || "Something went wrong.";
            return;
          }
          if (d.report) {
            current = d.report;
            upsert(d.report);
            renderAll();
            renderView();
          } else {
            REPORTS = REPORTS.filter((r) => r.id !== current.id);
            renderAll();
            $("viewModal").classList.remove("open");
          }
        }).catch(() => {
          busy = false;
          $("viewErr").textContent = "Something went wrong. Please try again.";
        });
      }

      $("viewActions").addEventListener("click", (e) => {
        const id = e.target.id;
        if (!current) return;
        if (id === "vPrint") printDoc(reportToDoc(current));
        else if (id === "vEdit") { $("viewModal").classList.remove("open"); openEditor(current); }
        else if (id === "vSubmit") viewAction(() => call("submit", { id: current.id }));
        else if (id === "vRecall") viewAction(() => call("recall", { id: current.id }));
        else if (id === "vDelete") {
          if (confirm("Delete this report? This cannot be undone.")) viewAction(() => call("delete", { id: current.id }));
        } else if (id === "vApprove") {
          viewAction(() => call("review", { id: current.id, decision: "approved", comment: ($("rvComment") || {}).value || "" }));
        } else if (id === "vReturn") {
          const c = ($("rvComment") || {}).value || "";
          if (!c.trim()) { $("viewErr").textContent = "Add a comment so the author knows what to change."; return; }
          viewAction(() => call("review", { id: current.id, decision: "returned", comment: c }));
        }
      });

      // ---------- editor ----------
      function openEditor(r) {
        ED = {
          id: r ? r.id : 0,
          images: r ? r.images.map((i) => ({ id: i.id, src: IMG(i.id), caption: i.caption || "" })) : []
        };
        $("editHead").textContent = r ? "Edit report" : "New report";
        $("editNote").innerHTML =
          r && r.status === "returned" && r.comment
            ? `<div class="rp-note"><b>Returned by the Owner</b>${esc(r.comment)}</div>`
            : "";
        $("fType").innerHTML = TYPES.map((t) => `<option>${esc(t)}</option>`).join("");
        $("fTitle").value = r ? r.title : "";
        $("fType").value = r ? r.type : "General";
        $("fDate").value = r ? r.date : today();
        $("fPeriod").value = r ? r.period : "";
        $("fBody").value = r ? r.body : "";
        $("editErr").textContent = "";
        dirty = false;
        renderImgs();
        $("editModal").classList.add("open");
      }

      function renderImgs() {
        $("imgList").innerHTML = ED.images.map((im, n) =>
          `<div class="rp-img"><img src="${esc(im.src)}" alt="">` +
          `<input data-cap="${n}" maxlength="255" placeholder="Caption (optional)" value="${esc(im.caption)}">` +
          `<button type="button" class="btn btn-line btn-sm" data-rm="${n}">Remove</button></div>`
        ).join("");
        $("addImgBtn").disabled = ED.images.length >= 10;
      }

      function closeEditor(force) {
        if (!force && dirty && !confirm("Discard your unsaved changes?")) return;
        $("editModal").classList.remove("open");
        ED = null;
        dirty = false;
      }

      function readImage(file) {
        return new Promise((resolve, reject) => {
          const url = URL.createObjectURL(file);
          const img = new Image();
          img.onload = () => {
            const MAX = 1600;
            let w = img.naturalWidth, h = img.naturalHeight;
            const s = Math.min(1, MAX / Math.max(w, h));
            w = Math.round(w * s); h = Math.round(h * s);
            const cv = document.createElement("canvas");
            cv.width = w; cv.height = h;
            const cx = cv.getContext("2d");
            cx.fillStyle = "#fff";
            cx.fillRect(0, 0, w, h);
            cx.drawImage(img, 0, 0, w, h);
            URL.revokeObjectURL(url);
            resolve(cv.toDataURL("image/jpeg", 0.82));
          };
          img.onerror = () => { URL.revokeObjectURL(url); reject(); };
          img.src = url;
        });
      }

      $("addImgBtn").addEventListener("click", () => $("fImages").click());
      $("fImages").addEventListener("change", (e) => {
        const files = Array.from(e.target.files || []);
        e.target.value = "";
        if (!ED) return;
        const room = 10 - ED.images.length;
        if (files.length > room) $("editErr").textContent = "Only " + room + " more image(s) can be added (max 10).";
        else $("editErr").textContent = "";
        Promise.all(
          files.slice(0, Math.max(room, 0)).map((f) =>
            /^image\/(jpeg|png|gif|webp)$/.test(f.type)
              ? readImage(f).then((data) => ({ id: 0, src: data, data: data, caption: "" })).catch(() => null)
              : Promise.resolve(null)
          )
        ).then((list) => {
          if (!ED) return;
          const ok = list.filter(Boolean);
          if (ok.length < Math.min(files.length, Math.max(room, 0))) $("editErr").textContent = "Some files were skipped (only JPG, PNG, GIF or WEBP images can be added).";
          if (ok.length) { ED.images = ED.images.concat(ok); dirty = true; renderImgs(); }
        });
      });

      $("imgList").addEventListener("input", (e) => {
        const n = e.target.getAttribute("data-cap");
        if (n !== null && ED) { ED.images[+n].caption = e.target.value; dirty = true; }
      });
      $("imgList").addEventListener("click", (e) => {
        const n = e.target.getAttribute("data-rm");
        if (n !== null && ED) { ED.images.splice(+n, 1); dirty = true; renderImgs(); }
      });
      ["fTitle", "fType", "fDate", "fPeriod", "fBody"].forEach((id) =>
        $(id).addEventListener("input", () => { dirty = true; })
      );

      function editorDoc() {
        return {
          title: $("fTitle").value.trim(),
          type: $("fType").value,
          date: $("fDate").value,
          period: $("fPeriod").value.trim(),
          body: $("fBody").value,
          status: "draft",
          author: NAME,
          images: ED.images.map((i) => ({ src: i.src, caption: i.caption }))
        };
      }

      function saveEditor(thenSubmit) {
        if (busy || !ED) return;
        const d = editorDoc();
        if (!d.title) { $("editErr").textContent = "Enter a report title."; return; }
        if (!d.date) { $("editErr").textContent = "Enter a report date."; return; }
        busy = true;
        $("editErr").textContent = "";
        const btns = ["editSave", "editSubmit"];
        btns.forEach((b) => ($(b).disabled = true));
        const payload = {
          id: ED.id, title: d.title, type: d.type, date: d.date, period: d.period, body: d.body,
          images: ED.images.map((i) => (i.id ? { id: i.id, caption: i.caption } : { data: i.data, caption: i.caption }))
        };
        const finish = () => { busy = false; btns.forEach((b) => ($(b).disabled = false)); };
        call("save", payload).then((res) => {
          if (!res.success) { finish(); $("editErr").textContent = res.error || "Could not save the report."; return; }
          const rep = res.report;
          upsert(rep);
          renderAll();
          ED.id = rep.id;
          ED.images = rep.images.map((i) => ({ id: i.id, src: IMG(i.id), caption: i.caption || "" }));
          dirty = false;
          if (!thenSubmit) {
            finish();
            closeEditor(true);
            openView(rep.id);
            return;
          }
          call("submit", { id: rep.id }).then((s) => {
            finish();
            if (!s.success) { $("editErr").textContent = (s.error || "Could not submit.") + " Your draft was saved."; renderImgs(); return; }
            upsert(s.report);
            renderAll();
            closeEditor(true);
            openView(rep.id);
          });
        }).catch(() => { finish(); $("editErr").textContent = "Something went wrong. Please try again."; });
      }

      $("editSave").addEventListener("click", () => saveEditor(false));
      $("editSubmit").addEventListener("click", () => {
        if (confirm("Submit this report to the Owner for review? You won't be able to edit it unless you recall it.")) saveEditor(true);
      });
      $("editPrint").addEventListener("click", () => { if (ED) printDoc(editorDoc()); });
      $("editCancel").addEventListener("click", () => closeEditor(false));
      $("editX").addEventListener("click", () => closeEditor(false));
      // A stray click on the dark backdrop must not throw away a report in progress
      $("editModal").addEventListener("click", (e) => { if (e.target === $("editModal")) e.stopPropagation(); });
      window.addEventListener("beforeunload", (e) => { if (ED && dirty) { e.preventDefault(); e.returnValue = ""; } });

      // ---------- wiring ----------
      $("rows").addEventListener("click", (e) => {
        const v = e.target.closest("[data-view]");
        if (v) return openView(+v.getAttribute("data-view"));
        const ed = e.target.closest("[data-edit]");
        if (ed) {
          call("get&id=" + ed.getAttribute("data-edit")).then((d) => {
            if (d.success) openEditor(d.report);
          });
        }
      });
      $("newBtn").addEventListener("click", () => openEditor(null));
      $("q").addEventListener("input", renderRows);
      $("statusFilter").addEventListener("change", renderRows);

      $("sub").textContent = isOwner()
        ? "Reports submitted to you by Administrators and Inventory Staff. Review, approve or return them, and print."
        : "Write reports, attach images, save drafts, print them, and submit them to the Owner for review.";
      $("thAuthor").textContent = isOwner() ? "Submitted by" : "Prepared by";
      DHP.applyRoles(document);
      renderAll();
      load();
    })();
  </script>
</body>
</html>