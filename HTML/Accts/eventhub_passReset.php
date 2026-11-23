<?php
require_once __DIR__ . '/config.php';

$errors  = ['identity' => '', 'password' => '', 'confirm' => ''];
$status  = '';
$bad     = false;
$devLink = '';
$old     = '';

/* Step 2: the person clicked the emailed link -> apply the new password */
if (isset($_GET['token'])) {
    $hash = hash('sha256', (string)$_GET['token']);
    $st = db()->prepare('SELECT ResetID, UserID, NewPasswordHash FROM PasswordResets WHERE TokenHash = ? AND UsedAt IS NULL AND ExpiresAt > NOW()');
    $st->execute([$hash]);
    if ($row = $st->fetch()) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE Users SET PasswordHash = ? WHERE UserID = ?')->execute([$row['NewPasswordHash'], $row['UserID']]);
        $pdo->prepare('UPDATE PasswordResets SET UsedAt = NOW() WHERE UserID = ? AND UsedAt IS NULL')->execute([$row['UserID']]);
        $pdo->commit();
        flash_set('Your password has been updated. Sign in with your new password.');
        redirect('eventhub_signIn.php');
    }
    $status = 'That confirmation link is invalid or has expired. Request a new one below.';
    $bad = true;
}

/* Step 1: the form */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = trim((string)($_POST['identity'] ?? ''));
    $pw  = (string)($_POST['password'] ?? '');
    $cf  = (string)($_POST['confirm'] ?? '');

    if (!csrf_ok()) {
        $status = 'Your session expired. Please try again.'; $bad = true;
    } else {
        if ($old === '') $errors['identity'] = 'Enter your email or username.';
        if ($m = valid_password($pw)) $errors['password'] = $m === 'Enter a password.' ? 'Enter a new password.' : $m;
        if ($cf === '') $errors['confirm'] = 'Confirm your new password.';
        elseif ($cf !== $pw) $errors['confirm'] = "Passwords don't match.";

        if (!array_filter($errors)) {
            $u = find_user_by_identity($old);
            if ($u && $u['IsActive']) {
                $token = bin2hex(random_bytes(32));
                $pdo = db();
                $pdo->prepare('DELETE FROM PasswordResets WHERE UserID = ? AND UsedAt IS NULL')->execute([$u['UserID']]);
                $pdo->prepare('INSERT INTO PasswordResets (UserID, TokenHash, NewPasswordHash, ExpiresAt, RecordDateTime) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())')
                    ->execute([$u['UserID'], hash('sha256', $token), password_hash($pw, PASSWORD_DEFAULT)]);

                $link = site_url() . '/eventhub_passReset.php?token=' . $token;
                $sent = @mail($u['Email'], 'Confirm your EventHub password change',
                    "Hi {$u['Username']},\n\nWe got a request to change your EventHub password. Confirm it here (valid for 1 hour):\n\n$link\n\nIf this wasn't you, ignore this email and your password stays the same.",
                    'From: ' . MAIL_FROM);
                if (!$sent) error_log("EventHub: could not send reset email to {$u['Email']}");
                if (DEV_MODE) $devLink = $link;
            }
            // Same message whether or not the account exists, so this page can't be used to probe for accounts.
            $status = 'If an account matches, we\'ve emailed a link. Your new password takes effect once you confirm it.';
            $old = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EventHub – Reset Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@300;400&display=swap" rel="stylesheet">
<style>
  :root {
    --page-bg: #ffffff;
    --panel-bg: #f2f2f2;
    --ink: #000000;
    --field-border: #8a8a8a;
    --focus: #e0409a;
    --error: #c0262d;
    --serif: "Playfair Display", "Times New Roman", serif;
    --sans: "Inter", Arial, sans-serif;
  }

  * { box-sizing: border-box; }

  html, body { margin: 0; min-height: 100%; background: var(--page-bg); }

  body {
    font-family: var(--serif);
    color: var(--ink);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 32px;
  }

  .frame {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 100%;
    max-width: 1100px;
    min-height: 620px;
  }

  /* ---------- Left image panel ---------- */
  .visual {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    background: #f25aae;
  }
  .visual::before {
    content: "";
    position: absolute;
    inset: -20px;
    background:
      repeating-linear-gradient(90deg,
        #e0409a 0px, #f56bb8 14px, #fba4d3 26px, #f56bb8 38px, #e0409a 52px);
    filter: blur(5px);
  }
  .visual::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(255,255,255,.12), rgba(0,0,0,.08));
  }
  .visual p {
    position: absolute;
    left: 28px;
    bottom: 22px;
    margin: 0;
    z-index: 1;
    max-width: 14ch;
    font-family: var(--sans);
    font-weight: 300;
    font-size: clamp(26px, 3.2vw, 38px);
    line-height: 1.1;
    letter-spacing: -0.01em;
    color: rgba(255,255,255,.88);
  }

  /* ---------- Right form panel ---------- */
  .panel {
    background: var(--panel-bg);
    border-radius: 14px;
    padding: 40px 56px;
    display: flex;
    flex-direction: column;
  }

  .brand {
    align-self: center;
    font-size: 17px;
    font-weight: 500;
    color: var(--ink);
    text-decoration: none;
    letter-spacing: -0.01em;
  }

  .form-wrap {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    max-width: 420px;
    width: 100%;
    margin: 0 auto;
  }

  h1 {
    margin: 0 0 40px;
    font-size: 36px;
    font-weight: 500;
    letter-spacing: -0.02em;
    line-height: 1.1;
  }

  .field { margin-bottom: 22px; }

  label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
  }

  .input-wrap { position: relative; }

  input {
    width: 100%;
    height: 40px;
    padding: 0 14px;
    font: 400 15px var(--sans);
    color: var(--ink);
    background: #fff;
    border: 1px solid var(--field-border);
    border-radius: 8px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
  }
  input:hover { border-color: #555; }
  input:focus {
    border-color: var(--focus);
    box-shadow: 0 0 0 3px rgba(224, 64, 154, .2);
  }
  input[aria-invalid="true"] { border-color: var(--error); }
  input[aria-invalid="true"]:focus { box-shadow: 0 0 0 3px rgba(192, 38, 45, .18); }

  .has-toggle input { padding-right: 56px; }
  .toggle {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    padding: 6px 8px;
    border: 0;
    background: none;
    font: 400 12px var(--sans);
    color: #444;
    cursor: pointer;
    border-radius: 6px;
  }
  .toggle:hover { color: #000; text-decoration: underline; }
  .toggle:focus-visible { outline: 2px solid var(--focus); }

  .error {
    min-height: 0;
    margin: 6px 0 0;
    font: 400 12px var(--sans);
    color: var(--error);
  }
  .error:empty { display: none; }

  .btn {
    display: block;
    width: 100%;
    height: 44px;
    margin-top: 14px;
    border: 0;
    border-radius: 999px;
    background: #000;
    color: #fff;
    font: 700 12px var(--serif);
    cursor: pointer;
    transition: background .15s, transform .1s;
  }
  .btn:hover { background: #2a2a2a; }
  .btn:active { transform: scale(.985); }
  .btn:focus-visible { outline: 3px solid var(--focus); outline-offset: 2px; }
  .btn:disabled { opacity: .6; cursor: default; }

  .status {
    margin: 14px 0 0;
    text-align: center;
    font: 400 13px var(--sans);
    color: #1d6b3a;
  }
  .status:empty { display: none; }

  .back {
    display: block;
    margin: 18px auto 0;
    width: max-content;
    font-size: 9px;
    font-weight: 500;
    color: var(--ink);
    text-decoration: underline;
  }
  .back:hover { text-decoration-thickness: 2px; }
  .back:focus-visible { outline: 2px solid var(--focus); outline-offset: 3px; }

  /* ---------- Responsive ---------- */
  @media (max-width: 820px) {
    body { padding: 16px; align-items: flex-start; }
    .frame { grid-template-columns: 1fr; min-height: 0; }
    .visual { min-height: 200px; }
    .panel { padding: 32px 24px 40px; }
    .form-wrap { padding-top: 32px; }
  }

  @media (prefers-reduced-motion: reduce) {
    * { transition: none !important; }
  }
.status.bad{color:var(--error)}
.devlink{margin:14px 0 0;padding:10px 12px;border-radius:8px;background:#fff7e0;border:1px solid #e6cf85;font:400 11px var(--sans);word-break:break-all}
</style>
</head>
<body>

<main class="frame">
  <aside class="visual" aria-hidden="true">
    <p>Tickets for every kind of night.</p>
  </aside>

  <section class="panel">
    <a class="brand" id="link-home" href="eventhub_landing.php">EventHub</a>

    <div class="form-wrap">
      <h1>Reset Password</h1>

      <form id="reset-form" method="post" action="eventhub_passReset.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="field">
          <label for="identity">Email / Username</label>
          <input id="identity" name="identity" type="text" autocomplete="username" required value="<?= e($old) ?>"<?= $errors['identity'] ? ' aria-invalid="true"' : '' ?>>
          <p class="error" id="identity-error" role="alert"><?= e($errors['identity']) ?></p>
        </div>

        <div class="field">
          <label for="password">New Password</label>
          <div class="input-wrap has-toggle">
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required<?= $errors['password'] ? ' aria-invalid="true"' : '' ?>>
            <button type="button" class="toggle" data-target="password" aria-label="Show new password">Show</button>
          </div>
          <p class="error" id="password-error" role="alert"><?= e($errors['password']) ?></p>
        </div>

        <div class="field">
          <label for="confirm">Password Confirmation</label>
          <div class="input-wrap has-toggle">
            <input id="confirm" name="confirm" type="password" autocomplete="new-password" required<?= $errors['confirm'] ? ' aria-invalid="true"' : '' ?>>
            <button type="button" class="toggle" data-target="confirm" aria-label="Show password confirmation">Show</button>
          </div>
          <p class="error" id="confirm-error" role="alert"><?= e($errors['confirm']) ?></p>
        </div>

        <button class="btn" id="submit-btn" type="submit">Reset Password</button>
        <p class="status<?= $bad ? ' bad' : '' ?>" id="status" role="status"><?= e($status) ?></p>
        <?php if ($devLink): ?>
          <p class="devlink"><strong>Dev mode:</strong> <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a></p>
        <?php endif; ?>
      </form>

      <a class="back" id="link-signin" href="eventhub_signIn.php">Return to Sign In</a>
    </div>
  </section>
</main>

<script>
  /* Show / hide password */
  document.querySelectorAll(".toggle").forEach(btn => {
    btn.addEventListener("click", () => {
      const input = document.getElementById(btn.dataset.target);
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.textContent = show ? "Hide" : "Show";
      btn.setAttribute("aria-label",
        (show ? "Hide " : "Show ") + (btn.dataset.target === "password" ? "new password" : "password confirmation"));
    });
  });

  /* Quick client-side check for instant feedback; the server re-checks everything. */
  const form = document.getElementById("reset-form");
  const identity = document.getElementById("identity");
  const password = document.getElementById("password");
  const confirmField = document.getElementById("confirm");
  const statusEl = document.getElementById("status");
  const submitBtn = document.getElementById("submit-btn");

  function setError(input, message) {
    document.getElementById(input.id + "-error").textContent = message;
    input.setAttribute("aria-invalid", message ? "true" : "false");
  }

  function validate() {
    let firstBad = null;
    const id = identity.value.trim(), pw = password.value;
    let msg = "";
    if (!id) msg = "Enter your email or username.";
    else if (id.includes("@") && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(id)) msg = "Enter a valid email address.";
    setError(identity, msg); if (msg) firstBad = firstBad || identity;

    msg = "";
    if (!pw) msg = "Enter a new password.";
    else if (pw.length < 8) msg = "Use at least 8 characters.";
    setError(password, msg); if (msg) firstBad = firstBad || password;

    msg = "";
    if (!confirmField.value) msg = "Confirm your new password.";
    else if (confirmField.value !== pw) msg = "Passwords don't match.";
    setError(confirmField, msg); if (msg) firstBad = firstBad || confirmField;
    return firstBad;
  }

  [identity, password, confirmField].forEach(input =>
    input.addEventListener("input", () => {
      if (input.getAttribute("aria-invalid") === "true") validate();
    })
  );

  form.addEventListener("submit", e => {
    const bad = validate();
    if (bad) { e.preventDefault(); bad.focus(); return; }
    submitBtn.disabled = true;
    submitBtn.textContent = "Sending…";
  });
</script>
</body>
</html>
