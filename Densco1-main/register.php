<?php
require_once 'config.php';
require_once 'password_reset_lib.php';   // is_local_host()
require_once 'email_otp_lib.php';
session_start();

function reg_fail($field, $msg, $code = 200, $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'field' => $field, 'error' => $msg], $extra));
    exit;
}

// Sends a code and builds the reply for the "enter your code" step.
function otp_deliver_and_reply($email, $name, $code) {
    $reply = ['success' => true, 'step' => 'verify', 'email' => $email, 'cooldown' => OTP_COOLDOWN_SECS];
    $sent = otp_send_email($email, $name, $code);
    if (!$sent) {
        if (is_local_host()) {
            // Local testing only (no Brevo key yet): show the code on screen.
            $reply['dev_otp'] = $code;
        } else {
            reg_fail('form', 'We could not send the verification email right now. Please try again in a moment.', 502);
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
            reg_fail('form', 'We could not read your details. Please reload the page and try again.', 400);
        }
        $action = (string)($input['action'] ?? 'start');
        ensure_otp_table($conn);

        // ------------------------------------------------------------------
        // Step 2: the person typed the 6-digit code from their email.
        // ------------------------------------------------------------------
        if ($action === 'verify') {
            $email = $_SESSION['pending_reg_email'] ?? '';
            $code  = preg_replace('/\D/', '', (string)($input['code'] ?? ''));
            if ($email === '') {
                reg_fail('form', 'Your sign-up session expired. Please fill in the form again.', 200, ['restart' => true]);
            }
            if (strlen($code) !== 6) reg_fail('code', 'Enter the 6-digit code from your email.');

            $stmt = $conn->prepare("SELECT full_name, password_hash, otp_hash, attempts, (expires_at < NOW()) AS expired FROM email_otps WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                reg_fail('form', 'We could not find your sign-up. Please fill in the form again.', 200, ['restart' => true]);
            }
            if ((int)$row['expired'] === 1) {
                reg_fail('code', 'This code has expired. Tap "Send a new code".');
            }
            if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
                reg_fail('code', 'Too many wrong attempts. Tap "Send a new code".');
            }

            if (!hash_equals($row['otp_hash'], otp_hash_for($email, $code))) {
                $up = $conn->prepare("UPDATE email_otps SET attempts = attempts + 1 WHERE email = ?");
                $up->bind_param("s", $email);
                $up->execute();
                $up->close();
                $left = OTP_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
                reg_fail('code', $left > 0
                    ? "That code isn't right. $left attempt" . ($left === 1 ? '' : 's') . ' left.'
                    : 'Too many wrong attempts. Tap "Send a new code".');
            }

            // Code is correct. Make sure nobody registered this email meanwhile.
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $chk->bind_param("s", $email);
            $chk->execute();
            $taken = $chk->get_result()->num_rows > 0;
            $chk->close();
            if ($taken) {
                reg_fail('form', 'That email is already registered. Please log in.');
            }

            $name = $row['full_name'];
            // The code was just confirmed, so this account is verified from day one.
            ensure_verified_column($conn);
            $ins = $conn->prepare("INSERT INTO users (full_name, email, password, email_verified_at) VALUES (?, ?, ?, NOW())");
            $ins->bind_param("sss", $name, $email, $row['password_hash']);
            $ins->execute();
            $newId = $conn->insert_id;
            $ins->close();

            $del = $conn->prepare("DELETE FROM email_otps WHERE email = ?");
            $del->bind_param("s", $email);
            $del->execute();
            $del->close();

            session_regenerate_id(true);
            unset($_SESSION['pending_reg_email']);
            $_SESSION['user_id']   = $newId;
            $_SESSION['user_name'] = $name;

            echo json_encode(['success' => true, 'name' => $name]);
            exit;
        }

        // ------------------------------------------------------------------
        // "Send a new code" button.
        // ------------------------------------------------------------------
        if ($action === 'resend') {
            $email = $_SESSION['pending_reg_email'] ?? '';
            if ($email === '') {
                reg_fail('form', 'Your sign-up session expired. Please fill in the form again.', 200, ['restart' => true]);
            }
            $wait = otp_wait_seconds($conn, $email);
            if ($wait > 0) reg_fail('code', "Please wait $wait seconds before asking for another code.", 200, ['wait' => $wait]);

            $stmt = $conn->prepare("SELECT full_name, password_hash FROM email_otps WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                reg_fail('form', 'We could not find your sign-up. Please fill in the form again.', 200, ['restart' => true]);
            }
            $code = otp_store($conn, $email, $row['full_name'], $row['password_hash']);
            otp_deliver_and_reply($email, $row['full_name'], $code);
        }

        // ------------------------------------------------------------------
        // Step 1: validate the form, then email a code. The account is only
        // created once the code has been entered (action "verify").
        // ------------------------------------------------------------------
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

        // Don't let the same address be spammed with codes.
        $wait = otp_wait_seconds($conn, $email);
        if ($wait > 0) {
            reg_fail('email', "A code was just sent to this email. Please wait $wait seconds and try again.");
        }

        // The password is stored only as a salted one-way hash (bcrypt via
        // password_hash) - never as plain text - even while waiting for the code.
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $code = otp_store($conn, $email, $name, $hash);
        $_SESSION['pending_reg_email'] = $email;
        otp_deliver_and_reply($email, $name, $code);
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

      <form id="verifyForm" novalidate hidden>
        <p class="muted" id="verifyIntro">We sent a 6-digit code to your email.</p>
        <div class="field">
          <label for="otp">Verification code</label>
          <input id="otp" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="123456" style="letter-spacing:6px;font-size:20px;text-align:center">
          <p class="field-hint" id="otpHint">The code is valid for 10 minutes. Check your spam folder if you don't see it.</p>
          <p class="field-err" id="err-code" role="alert"></p>
        </div>
        <button class="btn btn-red btn-lg btn-block" id="verifyBtn">Verify and create account</button>
        <button type="button" class="btn btn-line btn-block" id="resendBtn" style="margin-top:10px" disabled>Send a new code</button>
        <button type="button" class="btn btn-block" id="changeEmailBtn" style="margin-top:6px;background:none;color:var(--mute)">Use a different email</button>
      </form>

      <div class="auth-foot">
        <p>Already registered? <a href="login.php">Log in</a></p>
      </div>
    </div>

  </main>

  <footer class="ftr" id="siteFooter"></footer>

  <script src="api/settings.php?script=1"></script>
  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
</body>
</html>