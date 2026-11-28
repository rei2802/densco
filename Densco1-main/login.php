<?php
require_once 'config.php';
require_once 'password_reset_lib.php';   // is_local_host()
require_once 'email_otp_lib.php';
session_start();

function login_fail($field, $msg, $code = 200, $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'field' => $field, 'error' => $msg], $extra));
    exit;
}

// Creates and emails a fresh code to an existing, not-yet-verified customer
// and replies with the "enter your code" step.
function login_deliver_and_reply(mysqli $conn, $uid, $email, $name) {
    $code  = login_otp_store($conn, $uid, $email);
    $reply = ['success' => true, 'step' => 'verify', 'email' => $email, 'cooldown' => OTP_COOLDOWN_SECS];
    if (!otp_send_email($email, $name, $code, true)) {
        if (is_local_host()) {
            $reply['dev_otp'] = $code;   // local testing only (no email key set up)
        } else {
            login_fail('form', 'We could not send the verification email right now. Please try again in a moment.', 502);
        }
    }
    echo json_encode($reply);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'field' => 'form', 'error' => 'We could not read your details. Please reload the page and try again.']);
            exit;
        }
        ensure_verified_column($conn);
        ensure_login_otp_table($conn);
        $action = (string)($input['action'] ?? 'login');

        // Step 2 (accounts created before email verification existed): the
        // person typed the 6-digit code that was emailed after a correct password.
        if ($action === 'verify') {
            $uid  = (int)($_SESSION['pending_login_uid'] ?? 0);
            $mail = (string)($_SESSION['pending_login_email'] ?? '');
            $code = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
            if ($uid === 0) login_fail('form', 'Your session expired. Please log in again.', 200, ['restart' => true]);
            if (strlen($code) !== 6) login_fail('code', 'Enter the 6-digit code from your email.');

            $stmt = $conn->prepare("SELECT otp_hash, attempts, (expires_at < NOW()) AS expired FROM login_otps WHERE user_id = ? LIMIT 1");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $o = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$o) login_fail('form', 'Please log in again to get a new code.', 200, ['restart' => true]);
            if ((int)$o['expired'] === 1) login_fail('code', 'This code has expired. Tap "Send a new code".');
            if ((int)$o['attempts'] >= OTP_MAX_ATTEMPTS) login_fail('code', 'Too many wrong attempts. Tap "Send a new code".');

            if (!hash_equals($o['otp_hash'], otp_hash_for($mail, $code))) {
                $up = $conn->prepare("UPDATE login_otps SET attempts = attempts + 1 WHERE user_id = ?");
                $up->bind_param("i", $uid);
                $up->execute();
                $up->close();
                $left = OTP_MAX_ATTEMPTS - ((int)$o['attempts'] + 1);
                login_fail('code', $left > 0
                    ? "That code isn't right. $left attempt" . ($left === 1 ? '' : 's') . ' left.'
                    : 'Too many wrong attempts. Tap "Send a new code".');
            }

            $mark = $conn->prepare("UPDATE users SET email_verified_at = NOW() WHERE user_id = ?");
            $mark->bind_param("i", $uid);
            $mark->execute();
            $mark->close();
            $del = $conn->prepare("DELETE FROM login_otps WHERE user_id = ?");
            $del->bind_param("i", $uid);
            $del->execute();
            $del->close();

            $name = (string)($_SESSION['pending_login_name'] ?? '');
            session_regenerate_id(true);
            unset($_SESSION['pending_login_uid'], $_SESSION['pending_login_email'], $_SESSION['pending_login_name']);
            $_SESSION['user_id']   = $uid;
            $_SESSION['user_name'] = $name;
            echo json_encode(['success' => true, 'name' => $name]);
            exit;
        }

        // "Send a new code" button.
        if ($action === 'resend') {
            $uid  = (int)($_SESSION['pending_login_uid'] ?? 0);
            $mail = (string)($_SESSION['pending_login_email'] ?? '');
            $name = (string)($_SESSION['pending_login_name'] ?? '');
            if ($uid === 0) login_fail('form', 'Your session expired. Please log in again.', 200, ['restart' => true]);
            $wait = login_otp_wait_seconds($conn, $uid);
            if ($wait > 0) login_fail('code', "Please wait $wait seconds before asking for another code.", 200, ['wait' => $wait]);
            login_deliver_and_reply($conn, $uid, $mail, $name);
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

        $stmt = $conn->prepare("SELECT user_id, full_name, email, password, email_verified_at FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $userInput);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($pw, $row['password'])) {
            if ($row['email_verified_at'] === null) {
                // Account from before email verification: the password is right, but
                // the person must prove they own the address before getting in.
                // No customer session is created until the code is confirmed.
                session_regenerate_id(true);
                unset($_SESSION['user_id'], $_SESSION['user_name']);
                $_SESSION['pending_login_uid']   = (int)$row['user_id'];
                $_SESSION['pending_login_email'] = $row['email'];
                $_SESSION['pending_login_name']  = $row['full_name'];
                // Don't spam the inbox: if a code went out moments ago, just show the code box.
                $wait = login_otp_wait_seconds($conn, (int)$row['user_id']);
                if ($wait > 0) {
                    echo json_encode(['success' => true, 'step' => 'verify', 'email' => $row['email'], 'cooldown' => $wait]);
                    exit;
                }
                login_deliver_and_reply($conn, (int)$row['user_id'], $row['email'], $row['full_name']);
            }
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

      <form id="loginVerifyForm" novalidate hidden>
        <p class="muted" id="verifyIntro">We sent a 6-digit code to your email.</p>
        <p class="muted">We now verify every email address. This is a one-time step.</p>
        <div class="field">
          <label for="otp">Verification code</label>
          <input id="otp" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="123456" style="letter-spacing:6px;font-size:20px;text-align:center">
          <p class="field-hint" id="otpHint">The code is valid for 10 minutes. Check your spam folder if you don't see it.</p>
          <p class="field-err" id="err-code" role="alert"></p>
        </div>
        <button class="btn btn-red btn-lg btn-block" id="verifyBtn">Verify and log in</button>
        <button type="button" class="btn btn-line btn-block" id="resendBtn" style="margin-top:10px" disabled>Send a new code</button>
        <p class="field-err form-err" id="err-form" role="alert"></p>
        <button type="button" class="btn btn-block" id="backToLoginBtn" style="margin-top:6px;background:none;color:var(--mute)">Back to log in</button>
      </form>

      <div class="auth-foot">
        <p>New to Densco? <a href="register.php">Create an account</a></p>
      </div>
    </div>

  </main>

  <footer class="ftr ftr-compact" id="siteFooter"></footer>

  <script src="api/settings.php?script=1"></script>
  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
</body>
</html>