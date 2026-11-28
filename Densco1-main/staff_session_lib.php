<?php

function current_staff_role(mysqli $db): ?string {
    $id = (int) ($_SESSION['staff_id'] ?? 0);
    if (!$id) return null;
    $staff = $db->execute_query('SELECT role FROM staff WHERE staff_id = ?', [$id])->fetch_assoc();
    $role = $staff['role'] ?? null;
    if (!in_array($role, ['owner', 'admin', 'inventory'], true)) {
        unset($_SESSION['staff_id'], $_SESSION['staff_name'], $_SESSION['staff_role']);
        return null;
    }
    $_SESSION['staff_role'] = $role;
    return $role;
}

function require_current_staff(mysqli $db, bool $api, array $allowed = ['owner', 'admin', 'inventory']): string {
    $role = current_staff_role($db);
    if (in_array($role, $allowed, true)) return $role;
    if ($api) {
        http_response_code($role ? 403 : 401);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['success' => false, 'error' => $role ? 'Not allowed.' : 'Please log in again.']);
    } else {
        header('Location: ' . ($role ? 'dashboard.html' : 'login.php'));
    }
    exit;
}
