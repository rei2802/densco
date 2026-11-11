<?php
// Customer profile API (used by account.html and cart.php).
//   GET                      -> { success, profile: { name, email, phone, address } }
//   POST action=info         -> update name, email, phone, address
//   POST action=contact      -> update only phone + address (saved from checkout)
//   POST action=password     -> change password (needs the current password)
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

function out($ok, $extra = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok], $extra));
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    out(false, ['error' => 'Please log in again.'], 401);
}

try {
    require_once 'config.php';

    // The users table started with only name / email / password.
    // Add the phone and address columns the first time they are needed.
    $have = [];
    $cres = $conn->query("SHOW COLUMNS FROM users");
    while ($c = $cres->fetch_assoc()) {
        $have[$c['Field']] = true;
    }
    if (empty($have['phone']))   $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL");
    if (empty($have['address'])) $conn->query("ALTER TABLE users ADD COLUMN address VARCHAR(255) NULL");

    function load_profile(mysqli $conn, $userId) {
        $stmt = $conn->prepare("SELECT full_name, email, phone, address FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) return null;
        return [
            'name'    => $r['full_name'] ?? '',
            'email'   => $r['email'] ?? '',
            'phone'   => $r['phone'] ?? '',
            'address' => $r['address'] ?? '',
        ];
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $p = load_profile($conn, $userId);
        if (!$p) out(false, ['error' => 'Account not found.'], 404);
        out(true, ['profile' => $p]);
    }

    $in     = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $in['action'] ?? '';

    // ---- validators ----
    $checkPhone = function ($phone) {
        return $phone === '' || preg_match('/^09\d{9}$/', $phone);
    };

    if ($action === 'contact') {
        $phone   = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
        $address = trim((string)($in['address'] ?? ''));
        if (!$checkPhone($phone)) out(false, ['error' => 'Enter an 11-digit mobile number starting with 09.']);
        if (mb_strlen($address) > 255) out(false, ['error' => 'Address is too long (255 characters maximum).']);

        // Only fill in what was given, never blank out saved details
        if ($phone !== '') {
            $s = $conn->prepare("UPDATE users SET phone = ? WHERE user_id = ?");
            $s->bind_param("si", $phone, $userId); $s->execute(); $s->close();
        }
        if ($address !== '') {
            $s = $conn->prepare("UPDATE users SET address = ? WHERE user_id = ?");
            $s->bind_param("si", $address, $userId); $s->execute(); $s->close();
        }
        out(true);
    }

    if ($action === 'info') {
        $name    = trim((string)($in['name'] ?? ''));
        $email   = trim((string)($in['email'] ?? ''));
        $phone   = preg_replace('/\D/', '', (string)($in['phone'] ?? ''));
        $address = trim((string)($in['address'] ?? ''));

        if ($name === '' || $email === '')               out(false, ['error' => 'Name and email are required.']);
        if (mb_strlen($name) > 100 || mb_strlen($email) > 100) out(false, ['error' => 'Name or email is too long.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  out(false, ['error' => 'Enter a valid email address.']);
        if (!$checkPhone($phone))                        out(false, ['error' => 'Enter an 11-digit mobile number starting with 09.']);
        if (mb_strlen($address) > 255)                   out(false, ['error' => 'Address is too long (255 characters maximum).']);

        // Email must not belong to someone else
        $s = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
        $s->bind_param("si", $email, $userId);
        $s->execute();
        $taken = $s->get_result()->num_rows > 0;
        $s->close();
        if ($taken) out(false, ['error' => 'That email is already used by another account.']);

        $phoneDb = $phone === '' ? null : $phone;
        $addrDb  = $address === '' ? null : $address;
        $s = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?");
        $s->bind_param("ssssi", $name, $email, $phoneDb, $addrDb, $userId);
        $s->execute();
        $s->close();

        $_SESSION['user_name'] = $name;
        out(true, ['profile' => load_profile($conn, $userId)]);
    }

    if ($action === 'password') {
        $cur = (string)($in['current'] ?? '');
        $new = (string)($in['new'] ?? '');
        $new2 = (string)($in['confirm'] ?? '');

        if ($cur === '' || $new === '')  out(false, ['error' => 'Fill in your current and new password.']);
        if (strlen($new) < 6)            out(false, ['error' => 'New password must be at least 6 characters.']);
        if ($new !== $new2)              out(false, ['error' => "The two new passwords don't match."]);

        $s = $conn->prepare("SELECT password FROM users WHERE user_id = ? LIMIT 1");
        $s->bind_param("i", $userId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row || !password_verify($cur, $row['password'])) {
            out(false, ['error' => 'Your current password is incorrect.']);
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $s->bind_param("si", $hash, $userId);
        $s->execute();
        $s->close();
        out(true);
    }

    out(false, ['error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('profile.php: ' . $e->getMessage());
    out(false, ['error' => 'Could not save right now. Please try again.', 'detail' => $e->getMessage()], 500);
}
?>