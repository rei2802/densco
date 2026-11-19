<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'field' => 'form', 'error' => 'We could not read your details. Please reload the page and try again.']);
            exit;
        }
        $userInput = trim($input['user'] ?? '');
        $pw = (string)($input['pw'] ?? '');

        if ($userInput === '') {
            echo json_encode(['success' => false, 'field' => 'user', 'error' => 'Enter your email or username.']);
            exit;
        }
        if ($pw === '') {
            echo json_encode(['success' => false, 'field' => 'pw', 'error' => 'Enter your password.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT user_id, full_name, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $userInput);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($pw, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $row['user_id'];
            $_SESSION['user_name'] = $row['full_name'];
            echo json_encode(['success' => true, 'name' => $row['full_name']]);
            exit;
        }

        // Not a customer? One login form for everyone: check the staff accounts too.
        $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, password, role FROM staff WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $userInput);
        $stmt->execute();
        $staffRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($staffRow && password_verify($pw, $staffRow['password'])) {
            $fullName = $staffRow['first_name'] . ' ' . $staffRow['last_name'];
            session_regenerate_id(true);
            $_SESSION['staff_id']   = $staffRow['staff_id'];
            $_SESSION['staff_name'] = $fullName;
            $_SESSION['staff_role'] = $staffRow['role'];
            echo json_encode(['success' => true, 'staff' => true, 'name' => $fullName, 'role' => $staffRow['role']]);
            exit;
        }

        // Deliberately generic: never reveal whether the email exists or the password was wrong.
        echo json_encode(['success' => false, 'field' => 'pw', 'error' => 'Invalid email or password.']);
    } catch (Throwable $e) {
        error_log('login.php failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'field' => 'form', 'error' => 'We could not log you in right now. Please try again in a moment.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Log in — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>

  <main class="page wrap">

    <div class="auth-box">
      <h2>Log in</h2>
      <p class="muted">Access your account to order and track deliveries.</p>

      <form id="loginForm" novalidate>
        <div class="field">
          <label for="u">Email or username</label>
          <input id="u" name="user" autocomplete="username">
          <p class="field-err" id="err-user" role="alert"></p>
        </div>

        <div class="field">
          <label for="p">Password</label>
          <input id="p" name="pw" type="password" autocomplete="current-password">
          <p class="field-err" id="err-pw" role="alert"></p>
          <p class="forgot"><a href="forgot_password.php">Forgot your password?</a></p>
        </div>

        <button class="btn btn-red btn-lg btn-block" id="submitBtn">Log in</button>
        <p class="field-err form-err" id="formErr" role="alert"></p>
      </form>

      <div class="auth-foot">
        <p>New to Densco? <a href="register.php">Create an account</a></p>
      </div>
    </div>

  </main>

  <footer class="ftr" id="siteFooter"></footer>

  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
</body>
</html>