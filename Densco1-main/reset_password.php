<?php
require_once 'config.php';
require_once 'password_reset_lib.php';
session_start();

// Returns the reset row for a token if it exists and has not expired, else null.
function find_reset(mysqli $conn, $token) {
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    ensure_reset_table($conn);
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT reset_id, user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = is_array($input) ? ($input['token'] ?? '') : '';
        $pw    = is_array($input) ? (string)($input['pw'] ?? '') : '';

        if ($pw === '')        { echo json_encode(['success' => false, 'field' => 'pw', 'error' => 'Enter a new password.']); exit; }
        if (strlen($pw) < 6)   { echo json_encode(['success' => false, 'field' => 'pw', 'error' => 'Password must be at least 6 characters.']); exit; }
        if (strlen($pw) > 72)  { echo json_encode(['success' => false, 'field' => 'pw', 'error' => 'Password must be 72 characters or fewer.']); exit; }

        $reset = find_reset($conn, $token);
        if (!$reset) {
            echo json_encode(['success' => false, 'field' => 'form', 'error' => 'This reset link is invalid or has expired. Please request a new one.']);
            exit;
        }

        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $uid  = (int)$reset['user_id'];
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upd->bind_param("si", $hash, $uid);
        $upd->execute();
        $upd->close();

        // A link works once; remove every pending link for this account.
        $del = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $del->bind_param("i", $uid);
        $del->execute();
        $del->close();

        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        error_log('reset_password.php failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'field' => 'form', 'error' => 'We could not reset your password right now. Please try again in a moment.']);
    }
    exit;
}

$token = $_GET['token'] ?? '';
$valid = false;
try { $valid = find_reset($conn, $token) !== null; } catch (Throwable $e) { error_log('reset_password.php: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>Reset password — Densco Health Products</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="hdr" id="siteHeader"></header>
  <main class="page wrap">
    <div class="auth-box">
<?php if ($valid): ?>
      <h2>Choose a new password</h2>
      <p class="muted">Enter your new password below.</p>

      <form id="resetForm" novalidate>
        <div class="field">
          <label for="p">New password</label>
          <input id="p" name="pw" type="password" autocomplete="new-password" maxlength="72" aria-describedby="pwHint">
          <p class="field-hint" id="pwHint">Use at least 6 characters.</p>
          <p class="field-err" id="err-pw" role="alert"></p>
        </div>
        <div class="field">
          <label for="p2">Confirm new password</label>
          <input id="p2" name="pw2" type="password" autocomplete="new-password" maxlength="72">
          <p class="field-err" id="err-pw2" role="alert"></p>
        </div>
        <button class="btn btn-red btn-lg btn-block" id="submitBtn">Save new password</button>
        <p class="field-err form-err" id="formErr" role="alert"></p>
        <p class="field-ok" id="okMsg" role="status"></p>
      </form>
<?php else: ?>
      <h2>Link not valid</h2>
      <p class="muted">This reset link is invalid or has expired. Please request a new one.</p>
      <a class="btn btn-red btn-lg btn-block" href="forgot_password.php">Request a new link</a>
<?php endif; ?>
      <div class="auth-foot"><p><a href="login.php">Back to log in</a></p></div>
    </div>
  </main>
  <footer class="ftr" id="siteFooter"></footer>

  <script src="js/store.js"></script>
  <script src="js/auth.js"></script>
<?php if ($valid): ?>
  <script>
    (function () {
      var F = DHP.form, f = document.getElementById("resetForm"), btn = document.getElementById("submitBtn");
      var token = <?php echo json_encode($token); ?>;
      f.addEventListener("submit", function (e) {
        e.preventDefault();
        F.clearErrors(f);
        var pw = f.elements.pw.value, pw2 = f.elements.pw2.value, bad = false;
        if (!pw) { F.setError(f, "pw", "Enter a new password."); bad = true; }
        else if (pw.length < 6) { F.setError(f, "pw", "Password must be at least 6 characters."); bad = true; }
        if (!pw2) { F.setError(f, "pw2", "Confirm your password by typing it again."); bad = true; }
        else if (pw && pw !== pw2) { F.setError(f, "pw2", "The two passwords don't match."); bad = true; }
        if (bad) return F.focusFirstError(f);
        F.setLoading(true, btn, "Saving...");
        F.postJSON("reset_password.php", { token: token, pw: pw }).then(function (d) {
          F.setLoading(false, btn);
          if (!d.success) { F.setError(f, f.querySelector("#err-" + d.field) ? d.field : "form", d.error); return; }
          btn.disabled = true;
          document.getElementById("okMsg").textContent = "Your password has been changed. Taking you to log in...";
          setTimeout(function () { location.href = "login.php"; }, 1800);
        });
      });
    })();
  </script>
<?php endif; ?>
</body>
</html>
