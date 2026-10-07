<?php
require_once '../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['user'] ?? '');
    $pw = $input['pw'] ?? '';

    if ($email === '' || $pw === '') {
        echo json_encode(['success' => false, 'error' => 'Enter your username/email and password.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, password, role FROM staff WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && password_verify($pw, $row['password'])) {
        $fullName = $row['first_name'] . ' ' . $row['last_name'];
        $_SESSION['staff_id']   = $row['staff_id'];
        $_SESSION['staff_name'] = $fullName;
        $_SESSION['staff_role'] = $row['role'];
        echo json_encode(['success' => true, 'name' => $fullName, 'role' => $row['role']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid username/email or password.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Staff log in — Densco Health Products</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body data-page="login" data-roles="">

  <div class="login-wrap">
    <div class="auth-box">
      <a class="brand center" href="../index.html"><img class="logo" src="../assets/logonobg.png" alt="Densco Health Products logo"><span class="brand-t"><b>DENSCO</b><i>HEALTH PRODUCTS</i></span></a>
      <h2>Staff log in</h2>
      <p class="muted">Internal access for owner, administrator and inventory staff.</p>

      <form id="staffLogin" novalidate>
        <p class="err" id="formErr"></p>

        <div class="field">
          <label for="r">Role</label>
          <select id="r" name="role">
            <option value="owner">Business Owner</option>
            <option value="admin">Administrator</option>
            <option value="inventory">Inventory Staff</option>
          </select>
        </div>

        <div class="field">
          <label for="u">Staff username or email</label>
          <input id="u" name="user" autocomplete="username">
        </div>

        <div class="field">
          <label for="p">Password</label>
          <input id="p" name="pw" type="password" autocomplete="current-password">
        </div>

        <button class="btn btn-red btn-lg btn-block">Log in to dashboard</button>
      </form>

      <div class="auth-foot">
        <a href="../login.php">Return to customer portal</a>
      </div>
    </div>
  </div>

  <script src="../js/store.js"></script>
  <script src="../js/staff-portal.js"></script>
</body>
</html>