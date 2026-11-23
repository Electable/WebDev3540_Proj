<?php
// ==============================================================
// FILE: eventhub_signIn.php
// Sign-in page: shows the form (GET) and checks credentials (POST).
// Layout: PHP logic at the top, then HTML, CSS and markup.
// Search tags used below: SECTION:, PHP:, CSS:, HTML:
// ==============================================================
require_once __DIR__ . '/config.php';

// --- PHP: already signed in? Skip this page and go home ---
if (current_user()) redirect('eventhub_landing.php');

// ==============================================================
// SECTION: PAGE STATE
// $flash  = one-time message from another page (e.g. "password updated")
// $errors = per-field error messages shown under the inputs
// $old    = identity text re-filled after a failed attempt
// ==============================================================
$flash  = flash_get();
$errors = ['identity' => '', 'password' => ''];
$old    = '';

// ==============================================================
// SECTION: HANDLE SIGN-IN FORM (POST)
// Order of checks: CSRF -> required fields -> account exists -> password -> active -> log in
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = trim((string)($_POST['identity'] ?? ''));
    $pw  = (string)($_POST['password'] ?? '');

    // --- PHP: security check - reject forged / expired forms ---
    if (!csrf_ok()) {
        $errors['identity'] = 'Your session expired. Please try again.';
    } else {
        // --- PHP: required-field validation ---
        if ($old === '') $errors['identity'] = 'Enter your email or username.';
        if ($pw === '')  $errors['password'] = 'Enter your password.';

        if (!$errors['identity'] && !$errors['password']) {
            // --- PHP: look up the account and verify the password ---
            $u = find_user_by_identity($old);
            if (!$u) {
                $errors['identity'] = 'No account found with that email or username.';
            } elseif (!password_verify($pw, $u['PasswordHash'])) {
                $errors['password'] = 'Incorrect password. Try again or reset it.';
            } elseif (!$u['IsActive']) {
                $errors['identity'] = 'This account has been deactivated.';
            } else {
                // --- PHP: success - upgrade the stored hash if the algorithm changed, then sign in ---
                if (password_needs_rehash($u['PasswordHash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE Users SET PasswordHash = ? WHERE UserID = ?')
                        ->execute([password_hash($pw, PASSWORD_DEFAULT), $u['UserID']]);
                }
                log_in($u);
            }
        }
    }
}
?>
<!DOCTYPE html>
<!-- ==============================================================
     HTML: PAGE
     Everything below is the browser-side page (head, styles, markup)
     ============================================================== -->
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light">
<title>EventHub – Sign in</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&display=swap">
<style>

/* ==============================================================
   CSS: DESIGN TOKENS & MOBILE SAFE AREA
   Colours plus padding for phone notches (env safe-area insets)
   ============================================================== */
:root{--bg:#fff;--fg:#000;--line:#bdbdbd;
  box-sizing:border-box;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}

/* --- CSS: BASE / RESET --- */
*{box-sizing:border-box}
html{scroll-padding-top:env(safe-area-inset-top,0px)}
body{margin:0;background:var(--bg);color:var(--fg);font-family:"Playfair Display",Georgia,"Times New Roman",serif;min-height:100vh}

/* ==============================================================
   CSS: PAGE LAYOUT
   Two columns: form on the left, artwork on the right
   ============================================================== */
.page{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center;max-width:1100px;margin:0 auto;padding:48px 40px;min-height:100vh}

/* --- CSS: FORM COLUMN (logo, heading, wrapper) --- */
.left{display:flex;flex-direction:column;align-items:center}
.logo{font-size:15px;font-weight:700;margin-bottom:56px}
.wrap{width:100%;max-width:340px}
h1{font-size:34px;line-height:1.05;margin:0;font-weight:700;letter-spacing:-.5px}
.sub{margin:6px 0 34px;font-size:13px;font-weight:500}

/* --- CSS: FORM FIELDS (labels, inputs, focus ring) --- */
label{display:block;font-size:11px;font-weight:700;margin:0 0 5px}
.field{margin-bottom:16px}
input{width:100%;height:36px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--fg);padding:0 12px;font:500 14px/1 Georgia,serif;outline:none;transition:border-color .15s,box-shadow .15s}
input:focus{border-color:#000;box-shadow:0 0 0 3px rgba(0,0,0,.1)}

/* --- CSS: BUTTONS (primary = Sign In, secondary = Google) --- */
.btn{width:100%;height:38px;border-radius:999px;font:700 12.5px "Playfair Display",serif;text-decoration:none;color:inherit;display:flex;align-items:center;justify-content:center;transition:transform .1s,border-color .15s}
.btn:active{transform:scale(.985)}
.primary{background:#000;color:#fff;border:1px solid #000;margin-top:34px}
.secondary{background:#fff;color:#000;border:1px solid var(--line);margin-top:10px;border-radius:14px}
.secondary:hover{border-color:#000}
.btn:focus-visible,.link:focus-visible{outline:2px solid #000;outline-offset:2px}

/* --- CSS: LINKS UNDER THE FORM (Forgot Password / Create Account) --- */
.links{display:flex;justify-content:center;gap:26px;margin-top:12px}
.link{color:#000;font:700 9.5px "Playfair Display",serif;text-decoration:underline;text-underline-offset:2px;padding:2px}

/* --- CSS: ARTWORK PANEL (right column) --- */
.art{background:#111;border-radius:12px;overflow:hidden;aspect-ratio:3/4;width:100%;max-width:480px;justify-self:end}
.art canvas{display:block;width:100%;height:100%}

/* --- CSS: RESPONSIVE - stack columns on tablets / phones --- */
@media (max-width:820px){
  .page{grid-template-columns:1fr;gap:32px;padding:32px 24px}
  .art{order:-1;aspect-ratio:16/9;justify-self:stretch;max-width:none}
  .logo{margin-bottom:32px}
}

/* --- CSS: ACCESSIBILITY - turn off animation for users who ask for it --- */
@media (prefers-reduced-motion:reduce){*{transition:none!important}}

/* --- CSS: VALIDATION MESSAGES & FLASH BANNER --- */
.fe{margin:5px 0 0;font:500 11px Georgia,serif;color:#b3261e}
.fe:empty{display:none}
.msg{margin:-20px 0 22px;padding:9px 12px;border-radius:9px;font:500 12px Georgia,serif;background:#eaf5ee;color:#1d6b3a}
input[aria-invalid="true"]{border-color:#b3261e}
button.btn{cursor:pointer;font-family:"Playfair Display",serif}
</style>
</head>
<body>

<!-- ==============================================================
     HTML: PAGE CONTENT
     Left: sign-in form. Right: decorative panel.
     ============================================================== -->
<main class="page">
  <section class="left">
    <a class="logo" href="eventhub_landing.php" style="color:inherit;text-decoration:none">EventHub</a>

    <!-- --- HTML: SIGN-IN FORM — Posts to this same file. The hidden csrf field is required. --- -->
    <form class="wrap" method="post" action="eventhub_signIn.php" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <h1>Welcome Back</h1>
      <p class="sub">Sign in to manage your tickets</p>

      <!-- --- HTML: flash message (only shown when set) --- -->
      <?php if ($flash): ?><p class="msg" role="status"><?= e($flash) ?></p><?php endif; ?>

      <div class="field">
        <label for="email">Email / Username</label>
        <input id="email" name="identity" type="text" autocomplete="username" value="<?= e($old) ?>"<?= $errors['identity'] ? ' aria-invalid="true"' : '' ?>>
        <p class="fe" role="alert"><?= e($errors['identity']) ?></p>
      </div>
      <div class="field">
        <label for="pw">Password</label>
        <input id="pw" name="password" type="password" autocomplete="current-password"<?= $errors['password'] ? ' aria-invalid="true"' : '' ?>>
        <p class="fe" role="alert"><?= e($errors['password']) ?></p>
      </div>

      <!-- --- HTML: submit + alternative actions --- -->
      <button class="btn primary" type="submit">Sign In</button>
      <a class="btn secondary" href="#" title="Google sign-in isn't connected yet">Continue with Google</a>
      <div class="links">
        <a class="link" href="eventhub_passReset.php">Forgot Password?</a>
        <a class="link" id="MovetoCreateBtn" href="eventhub_signup.php">Create Account</a>
      </div>
    </form>
  </section>

  <!-- --- HTML: ARTWORK PANEL (decorative, hidden from screen readers) --- -->
  <aside class="art" aria-hidden="true"><canvas id="art"></canvas></aside>
</main>
</body>
</html>
