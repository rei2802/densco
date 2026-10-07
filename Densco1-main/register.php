<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $pw    = $input['pw'] ?? '';
    $pw2   = $input['pw2'] ?? '';

    if ($name === '' || $email === '' || $pw === '') {
        echo json_encode(['success' => false, 'error' => 'Fill in your name, email and a password.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Enter a valid email address.']);
        exit;
    }
    if (strlen($name) > 100 || strlen($email) > 100) {
        echo json_encode(['success' => false, 'error' => 'Name or email is too long.']);
        exit;
    }
    if ($pw !== $pw2) {
        echo json_encode(['success' => false, 'error' => "The two passwords don't match."]);
        exit;
    }
    if (strlen($pw) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters.']);
        exit;
    }

    // Is the email already used?
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($exists) {
        echo json_encode(['success' => false, 'error' => 'That email is already registered. Please log in.']);
        exit;
    }

    // Create the account
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hash);
    $stmt->execute();
    $_SESSION['user_id']   = $conn->insert_id;
    $_SESSION['user_name'] = $name;
    $stmt->close();

    echo json_encode(['success' => true, 'name' => $name]);
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