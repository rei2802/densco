<?php
require_once 'config.php';
session_start();

function reg_fail($field, $msg, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => false, 'field' => $field, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            reg_fail('form', 'We could not read your details. Please reload the page and try again.', 400);
        }
        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $pw    = (string)($input['pw'] ?? '');
        // The confirm-password box is checked in the browser and is NOT sent over
        // the network. If an older client still sends it, it must match.
        if (isset($input['pw2']) && $input['pw2'] !== $pw) {
            reg_fail('pw2', "The two passwords don't match.");
        }

        if ($name === '')  reg_fail('name',  'Enter your full name.');
        if ($email === '') reg_fail('email', 'Enter your email address.');
        if ($pw === '')    reg_fail('pw',    'Enter a password.');
        if (strlen($name) > 100)  reg_fail('name',  'Name is too long (100 characters at most).');
        if (strlen($email) > 100) reg_fail('email', 'Email is too long (100 characters at most).');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            reg_fail('email', 'Enter a valid email address, for example name@example.com.');
        }
        if (strlen($pw) < 6)  reg_fail('pw', 'Password must be at least 6 characters.');
        if (strlen($pw) > 72) reg_fail('pw', 'Password must be 72 characters or fewer.');

        // Is the email already used?
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($exists) {
            reg_fail('email', 'That email is already registered. Please log in.');
        }

        // Create the account. The password is stored only as a salted one-way
        // hash (bcrypt via password_hash) - never as plain text.
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hash);
        $stmt->execute();
        session_regenerate_id(true);
        $_SESSION['user_id']   = $conn->insert_id;
        $_SESSION['user_name'] = $name;
        $stmt->close();

        echo json_encode(['success' => true, 'name' => $name]);
    } catch (Throwable $e) {
        error_log('register.php failed: ' . $e->getMessage());
        reg_fail('form', 'We could not create your account right now. Please try again in a moment.', 500);
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
        <div class="field">
          <label for="n">Full name</label>
          <input id="n" name="name" autocomplete="name" maxlength="100">
          <p class="field-err" id="err-name" role="alert"></p>
        </div>

        <div class="field">
          <label for="e">Email address</label>
          <input id="e" name="email" type="email" autocomplete="email" maxlength="100" placeholder="name@example.com">
          <p class="field-err" id="err-email" role="alert"></p>
        </div>

        <div class="field">
          <label for="p">Password</label>
          <input id="p" name="pw" type="password" autocomplete="new-password" maxlength="72" aria-describedby="pwHint">
          <p class="field-hint" id="pwHint">Use at least 6 characters. Your password is stored securely and can't be read by anyone, including staff.</p>
          <p class="field-err" id="err-pw" role="alert"></p>
        </div>

        <div class="field">
          <label for="p2">Confirm password</label>
          <input id="p2" name="pw2" type="password" autocomplete="new-password" maxlength="72">
          <p class="field-err" id="err-pw2" role="alert"></p>
        </div>

        <button class="btn btn-red btn-lg btn-block" id="submitBtn">Create account</button>
        <p class="field-err form-err" id="formErr" role="alert"></p>
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