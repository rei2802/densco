<?php
// Manage Accounts API (Owner + Admin).
//  - Owner: creates / deletes accounts, edits any account directly, approves or rejects change requests.
//  - Admin: cannot create or delete accounts. Can only REQUEST changes to Inventory Staff details;
//           a request does nothing until the Owner approves it.
// All of this is enforced here on the server, not just in the UI.
session_start();

function json_out($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}


// Full name -> first name (everything before the last word) + last name (last word)
function split_name($name) {
    $pos = strrpos($name, ' ');
    return [$pos === false ? $name : substr($name, 0, $pos), $pos === false ? '' : substr($name, $pos + 1)];
}

function email_taken($conn, $email, $exceptId) {
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE email = ? AND staff_id <> ? LIMIT 1");
    $stmt->bind_param("si", $email, $exceptId);
    $stmt->execute();
    $taken = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $taken;
}

// Validates name/email/password input for an edit and returns only the fields that
// actually change: ['first'=>..,'last'=>..,'email'=>..,'hash'=>..] (missing key = unchanged).
// Sends the JSON error and exits when the input is invalid.
function build_changes($conn, $target, $in) {
    $changes = [];

    $name = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? '')));
    if ($name === '') json_out(400, ['success' => false, 'error' => 'Enter the full name.']);
    list($first, $last) = split_name($name);
    if (mb_strlen($first) > 50 || mb_strlen($last) > 50) {
        json_out(400, ['success' => false, 'error' => 'The name is too long.']);
    }
    if ($first !== $target['first_name'] || $last !== $target['last_name']) {
        $changes['first'] = $first;
        $changes['last']  = $last;
    }

    $email = trim((string)($in['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        json_out(400, ['success' => false, 'error' => 'Enter a valid email address.']);
    }
    if ($email !== $target['email']) {
        if (email_taken($conn, $email, (int)$target['staff_id'])) {
            json_out(409, ['success' => false, 'error' => 'That email is already used by another account.']);
        }
        $changes['email'] = $email;
    }

    $pw = (string)($in['password'] ?? '');
    if ($pw !== '') {
        if (strlen($pw) < 8) json_out(400, ['success' => false, 'error' => 'Password must be at least 8 characters.']);
        $changes['hash'] = password_hash($pw, PASSWORD_DEFAULT);
    }

    if (!$changes) json_out(400, ['success' => false, 'error' => 'No changes to save.']);
    return $changes;
}

function apply_changes($conn, $id, $c) {
    $sets = []; $types = ''; $vals = [];
    if (isset($c['first']))  { $sets[] = 'first_name = ?'; $types .= 's'; $vals[] = $c['first']; }
    if (isset($c['last']))   { $sets[] = 'last_name = ?';  $types .= 's'; $vals[] = $c['last']; }
    if (isset($c['email']))  { $sets[] = 'email = ?';      $types .= 's'; $vals[] = $c['email']; }
    if (isset($c['hash']))   { $sets[] = 'password = ?';   $types .= 's'; $vals[] = $c['hash']; }
    if (!$sets) return;
    $types .= 'i'; $vals[] = $id;
    $stmt = $conn->prepare("UPDATE staff SET " . implode(', ', $sets) . " WHERE staff_id = ?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
}

function get_staff($conn, $id) {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

$role = $_SESSION['staff_role'] ?? null;
$myId = (int)($_SESSION['staff_id'] ?? 0);

if (!$role) json_out(401, ['success' => false, 'error' => 'Please log in again.']);
if (!in_array($role, ['owner', 'admin'], true)) json_out(403, ['success' => false, 'error' => 'Not allowed.']);

try {
    require_once '../config.php';

    // Admin-submitted changes waiting for the Owner's decision
    $conn->query("CREATE TABLE IF NOT EXISTS staff_change_requests (
        request_id     INT AUTO_INCREMENT PRIMARY KEY,
        target_id      INT NOT NULL,
        requested_by   INT NOT NULL,
        new_first_name VARCHAR(50)  NULL,
        new_last_name  VARCHAR(50)  NULL,
        new_email      VARCHAR(100) NULL,
        new_password   VARCHAR(255) NULL,
        status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decided_by     INT NULL,
        decided_at     TIMESTAMP NULL,
        INDEX (target_id, status)
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in     = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $in['action'] ?? '';
        $id     = (int)($in['id'] ?? 0);

        // Only the Owner may add or remove staff accounts
        if (in_array($action, ['create', 'delete'], true) && $role !== 'owner') {
            json_out(403, ['success' => false, 'error' => 'Only the Owner can create or delete staff accounts.']);
        }


        // ---- Owner: edit any account's details directly
        if ($action === 'update') {
            if ($role !== 'owner') json_out(403, ['success' => false, 'error' => 'Only the Owner can edit accounts directly. Submit a change request instead.']);
            $target = get_staff($conn, $id);
            if (!$target) json_out(404, ['success' => false, 'error' => 'Account not found.']);
            $changes = build_changes($conn, $target, $in);
            apply_changes($conn, $id, $changes);
            json_out(200, ['success' => true]);
        }

        // ---- Admin: ask the Owner to change an Inventory Staff account
        if ($action === 'request_update') {
            if ($role !== 'admin') json_out(403, ['success' => false, 'error' => 'Not allowed.']);
            $target = get_staff($conn, $id);
            if (!$target) json_out(404, ['success' => false, 'error' => 'Account not found.']);
            if ($target['role'] !== 'inventory') {
                json_out(403, ['success' => false, 'error' => 'Administrators can only request changes to Inventory Staff accounts.']);
            }
            $stmt = $conn->prepare("SELECT 1 FROM staff_change_requests WHERE target_id = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $hasPending = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($hasPending) {
                json_out(409, ['success' => false, 'error' => 'This account already has a change waiting for the Owner\'s approval.']);
            }

            $c     = build_changes($conn, $target, $in);
            $first = $c['first'] ?? null;
            $last  = $c['last']  ?? null;
            $email = $c['email'] ?? null;
            $hash  = $c['hash']  ?? null;
            $stmt = $conn->prepare("INSERT INTO staff_change_requests
                (target_id, requested_by, new_first_name, new_last_name, new_email, new_password)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $id, $myId, $first, $last, $email, $hash);
            $stmt->execute();
            $stmt->close();
            json_out(200, ['success' => true]);
        }

        // ---- Owner: approve or reject a pending request
        if ($action === 'approve' || $action === 'reject') {
            if ($role !== 'owner') json_out(403, ['success' => false, 'error' => 'Only the Owner can approve or reject changes.']);
            $rid = (int)($in['request_id'] ?? 0);

            $stmt = $conn->prepare("SELECT * FROM staff_change_requests WHERE request_id = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param("i", $rid);
            $stmt->execute();
            $req = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$req) json_out(404, ['success' => false, 'error' => 'This request was already handled or no longer exists.']);

            $status = 'rejected';
            $target = get_staff($conn, (int)$req['target_id']);

            if ($action === 'approve') {
                if (!$target || $target['role'] !== 'inventory') {
                    // Account was deleted (or changed role) after the request was made
                    $stmt = $conn->prepare("UPDATE staff_change_requests SET status = 'rejected', decided_by = ?, decided_at = NOW() WHERE request_id = ?");
                    $stmt->bind_param("ii", $myId, $rid);
                    $stmt->execute();
                    $stmt->close();
                    json_out(409, ['success' => false, 'error' => 'That account no longer exists, so the request was closed.']);
                }
                if ($req['new_email'] !== null && email_taken($conn, $req['new_email'], (int)$target['staff_id'])) {
                    json_out(409, ['success' => false, 'error' => 'That email is now used by another account. Reject this request.']);
                }

                $conn->begin_transaction();
                apply_changes($conn, (int)$target['staff_id'], [
                    'first' => $req['new_first_name'], 'last' => $req['new_last_name'],
                    'email' => $req['new_email'],      'hash' => $req['new_password'],
                ]);
                $status = 'approved';
            }

            $stmt = $conn->prepare("UPDATE staff_change_requests SET status = ?, decided_by = ?, decided_at = NOW() WHERE request_id = ? AND status = 'pending'");
            $stmt->bind_param("sii", $status, $myId, $rid);
            $stmt->execute();
            $stmt->close();
            if ($action === 'approve') $conn->commit();

            json_out(200, ['success' => true]);
        }

        if ($action === 'create') {
            $type  = $in['role'] ?? '';
            $name  = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? '')));
            $email = trim((string)($in['email'] ?? ''));
            $pw    = (string)($in['password'] ?? '');

            if (!in_array($type, ['admin', 'inventory'], true)) {
                json_out(400, ['success' => false, 'error' => 'Choose an account type.']);
            }
            if ($name === '') {
                json_out(400, ['success' => false, 'error' => 'Enter the full name.']);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                json_out(400, ['success' => false, 'error' => 'Enter a valid email address.']);
            }
            if (strlen($pw) < 8) {
                json_out(400, ['success' => false, 'error' => 'Password must be at least 8 characters.']);
            }

            // Full name -> first name (everything before the last word) + last name (last word)
            $pos   = strrpos($name, ' ');
            $first = $pos === false ? $name : substr($name, 0, $pos);
            $last  = $pos === false ? '' : substr($name, $pos + 1);
            if (mb_strlen($first) > 50 || mb_strlen($last) > 50) {
                json_out(400, ['success' => false, 'error' => 'The name is too long.']);
            }

            $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if ($exists) {
                json_out(409, ['success' => false, 'error' => 'That email is already used by another account.']);
            }

            $hash = password_hash($pw, PASSWORD_DEFAULT);
            try {
                $stmt = $conn->prepare("INSERT INTO staff (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $first, $last, $email, $hash, $type);
                $stmt->execute();
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() === 1062) {
                    json_out(409, ['success' => false, 'error' => 'That email is already used by another account.']);
                }
                throw $e;
            }

            json_out(200, ['success' => true]);
        }

        if ($action !== 'delete' || $id <= 0) {
            json_out(400, ['success' => false, 'error' => 'Invalid request.']);
        }
        if ($id === $myId) {
            json_out(400, ['success' => false, 'error' => "You can't delete your own account."]);
        }

        $stmt = $conn->prepare("SELECT role FROM staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) json_out(404, ['success' => false, 'error' => 'Account not found.']);
        if ($row['role'] === 'owner') {
            json_out(403, ['success' => false, 'error' => "The owner account can't be deleted."]);
        }

        $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        json_out(200, ['success' => true]);
    }

    // List every account (never returns passwords)
    $utc    = new DateTimeZone('UTC');
    $manila = new DateTimeZone('Asia/Manila');
    $accounts = [];
    $res = $conn->query("SELECT staff_id, first_name, last_name, email, role, created_at
                         FROM staff
                         ORDER BY FIELD(role, 'owner', 'admin', 'inventory'), staff_id");
    while ($r = $res->fetch_assoc()) {
        $dt = new DateTime($r['created_at'], $utc);
        $dt->setTimezone($manila);
        $accounts[] = [
            'id'      => (int)$r['staff_id'],
            'name'    => trim($r['first_name'] . ' ' . $r['last_name']),
            'first'   => $r['first_name'],
            'last'    => $r['last_name'],
            'email'   => $r['email'],
            'role'    => $r['role'],
            'created' => $dt->format('M j, Y'),
        ];
    }
    // Pending change requests (Owner sees all, Admin sees only their own)
    $requests = [];
    $sql = "SELECT r.request_id, r.target_id, r.new_first_name, r.new_last_name, r.new_email,
                   (r.new_password IS NOT NULL) AS pw_changed, r.created_at,
                   t.first_name AS t_first, t.last_name AS t_last, t.email AS t_email,
                   b.first_name AS b_first, b.last_name AS b_last
            FROM staff_change_requests r
            JOIN staff t ON t.staff_id = r.target_id
            LEFT JOIN staff b ON b.staff_id = r.requested_by
            WHERE r.status = 'pending'" . ($role === 'owner' ? '' : ' AND r.requested_by = ' . $myId) . "
            ORDER BY r.request_id";
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) {
        $dt = new DateTime($r['created_at'], $utc);
        $dt->setTimezone($manila);
        $newName = ($r['new_first_name'] !== null)
            ? trim($r['new_first_name'] . ' ' . $r['new_last_name']) : null;
        $requests[] = [
            'id'        => (int)$r['request_id'],
            'target'    => (int)$r['target_id'],
            'targetName'=> trim($r['t_first'] . ' ' . $r['t_last']),
            'oldEmail'  => $r['t_email'],
            'newName'   => $newName,
            'newEmail'  => $r['new_email'],
            'password'  => (bool)$r['pw_changed'],
            'by'        => trim(($r['b_first'] ?? '') . ' ' . ($r['b_last'] ?? '')) ?: 'Unknown',
            'created'   => $dt->format('M j, Y g:i A'),
        ];
    }

    json_out(200, ['success' => true, 'accounts' => $accounts, 'requests' => $requests, 'me' => $myId, 'role' => $role]);
} catch (Throwable $e) {
    json_out(500, ['success' => false, 'error' => 'Server error.']);
}