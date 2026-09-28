<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $userInput = trim($input['user'] ?? '');
    $pw = $input['pw'] ?? '';

    if ($userInput === '' || $pw === '') {
        echo json_encode(['success' => false, 'error' => 'Enter your email and password.']);
        exit;
    }

    // Your schema has no username column — match on email only
    $stmt = $conn->prepare("SELECT user_id, full_name, password FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $userInput);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && password_verify($pw, $row['password'])) {
        $_SESSION['user_id']   = $row['user_id'];
        $_SESSION['user_name'] = $row['full_name'];
        echo json_encode(['success' => true, 'name' => $row['full_name']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Create account — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>

  <main class="page wrap">

    <div class="auth-box">
      <h2>Create an account</h2>
      <p class="muted">Register to order supplies and track your orders.</p>

      <form id="registerForm" novalidate>
        <p class="err" id="formErr"></p>

        <div class="field">
          <label for="n">Full name</label>
          <input id="n" name="name" autocomplete="name">
        </div>

        <div class="field">
          <label for="e">Email address</label>
          <input id="e" name="email" type="email" autocomplete="email">
        </div>

        <div class="field">
          <label for="p">Password</label>
          <input id="p" name="pw" type="password" autocomplete="new-password">
        </div>

        <div class="field">
          <label for="p2">Confirm password</label>
          <input id="p2" name="pw2" type="password" autocomplete="new-password">
        </div>

        <button class="btn btn-red btn-lg btn-block">Create account</button>
      </form>

      <div class="auth-foot">
        <p>Already registered? <a href="login.php">Log in</a></p>
      </div>
    </div>

  </main>

  <footer class="ftr" id="siteFooter"></footer>

  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
</body>
</html>