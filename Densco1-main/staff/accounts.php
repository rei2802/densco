<?php
// Manage Accounts API: list all staff accounts, view details, create and delete accounts.
session_start();

function json_out($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

$role = $_SESSION['staff_role'] ?? null;
$myId = (int)($_SESSION['staff_id'] ?? 0);

if (!$role) json_out(401, ['success' => false, 'error' => 'Please log in again.']);
if (!in_array($role, ['owner', 'admin'], true)) json_out(403, ['success' => false, 'error' => 'Not allowed.']);

try {
    require_once '../config.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $in     = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $in['action'] ?? '';
        $id     = (int)($in['id'] ?? 0);

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
    json_out(200, ['success' => true, 'accounts' => $accounts, 'me' => $myId]);
} catch (Throwable $e) {
    json_out(500, ['success' => false, 'error' => 'Server error.']);
}