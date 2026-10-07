<?php
// Manage Accounts API: list all staff accounts, view details, delete an account.
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