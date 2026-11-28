<?php
require_once 'config.php';
require_once 'password_reset_lib.php';
require_once 'mail_lib.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = is_array($input) ? trim($input['email'] ?? '') : '';

        if ($email === '') {
            echo json_encode(['success' => false, 'field' => 'email', 'error' => 'Enter your email address.']);
            exit;
        }
        if (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'field' => 'email', 'error' => 'Enter a valid email address, for example name@example.com.']);
            exit;
        }

        // Same answer whether or not the email has an account, so this page can't be
        // used to find out who is registered.
        $reply = ['success' => true, 'message' => 'If an account exists for ' . $email . ', we have sent a link to reset the password. It is valid for ' . RESET_TOKEN_MINUTES . ' minutes. Please also check your spam folder.'];

        ensure_reset_table($conn);
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $uid = (int)$user['user_id'];
            $recent = $conn->prepare("SELECT 1 FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL " . RESET_COOLDOWN_SECONDS . " SECOND) LIMIT 1");
            $recent->bind_param("i", $uid);
            $recent->execute();
            $tooSoon = $recent->get_result()->num_rows > 0;
            $recent->close();

            if (!$tooSoon) {
                $origin = site_base_url();
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);
                $mins  = RESET_TOKEN_MINUTES;
                $ins = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL $mins MINUTE)");
                $ins->bind_param("is", $uid, $hash);
                $ins->execute();
                $ins->close();

                $link = $origin . '/reset_password.php?token=' . $token;
                $body = "Hello " . $user['full_name'] . ",\r\n\r\n"
                      . "We received a request to reset your Densco Health Products password.\r\n"
                      . "Open this link to choose a new one (valid for $mins minutes):\r\n\r\n$link\r\n\r\n"
                      . "If you did not ask for this, you can ignore this email; your password stays the same.\r\n";
                $html = '<p>Hello ' . htmlspecialchars($user['full_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
                      . '<p>We received a request to reset your Densco Health Products password.</p>'
                      . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                      . '">Reset your password</a> (valid for ' . $mins . ' minutes).</p>'
                      . '<p>If you did not ask for this, you can ignore this email. Your password stays the same.</p>';
                $sent = send_email($email, $user['full_name'], 'Reset your Densco password', $html, $body);
                if (!$sent && getenv('APP_ENV') === 'local' && is_local_host() && !getenv('RENDER')) {
                    $reply['dev_link'] = $link;
                }
            }
        }
        echo json_encode($reply);
    } catch (Throwable $e) {
        error_log('forgot_password.php failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'field' => 'form', 'error' => 'We could not process your request right now. Please try again in a moment.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Forgot password — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>
  <main class="page wrap">
    <div class="auth-box">
      <h2>Forgot your password?</h2>
      <p class="muted">Enter the email you registered with and we'll send you a link to choose a new password.</p>

      <form id="forgotForm" novalidate>
        <div class="field">
          <label for="e">Email address</label>
          <input id="e" name="email" type="email" autocomplete="email" maxlength="100" placeholder="name@example.com">
          <p class="field-err" id="err-email" role="alert"></p>
        </div>
        <button class="btn btn-red btn-lg btn-block" id="submitBtn">Send reset link</button>
        <p class="field-err form-err" id="formErr" role="alert"></p>
        <p class="field-ok" id="okMsg" role="status"></p>
      </form>

      <div class="auth-foot">
        <p><a href="login.php">Back to log in</a></p>
      </div>
    </div>
  </main>
  <footer class="ftr" id="siteFooter"></footer>

  <script src="api/settings.php?script=1"></script>
  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
  <script>
    (function () {
      var F = DHP.form, f = document.getElementById("forgotForm"), btn = document.getElementById("submitBtn");
      f.addEventListener("submit", function (e) {
        e.preventDefault();
        F.clearErrors(f);
        document.getElementById("okMsg").textContent = "";
        var email = f.elements.email.value.trim(), msg = F.emailProblem(email);
        if (msg) { F.setError(f, "email", msg); f.elements.email.focus(); return; }
        F.setLoading(true, btn, "Sending...");
        F.postJSON("forgot_password.php", { email: email }).then(function (d) {
          F.setLoading(false, btn);
          if (!d.success) { F.setError(f, d.field === "email" ? "email" : "form", d.error); return; }
          var ok = document.getElementById("okMsg");
          ok.textContent = d.message;
          if (d.dev_link) {
            var a = document.createElement("a");
            a.href = d.dev_link; a.textContent = "Local test link: open the reset page";
            ok.appendChild(document.createElement("br")); ok.appendChild(a);
          }
        });
      });
    })();
  </script>
</body>
</html>
