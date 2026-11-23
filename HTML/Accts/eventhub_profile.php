<?php
// ==============================================================
// FILE: eventhub_profile.php
// Profile page: view and edit your account.
// GET shows the page; POST (via AJAX from the page script) validates and saves, returning JSON.
// Customers also edit billing details; Organizers edit organisation details.
// Search tags used below: SECTION:, FUNC:, PHP:, CSS:, HTML:, JS:
// ==============================================================
require_once __DIR__ . '/config.php';

// --- PHP: guests are redirected to sign-in; $uid / $pdo used throughout ---
$me  = require_login();
$uid = (int)$me['UserID'];
$pdo = db();

// ==============================================================
// FUNC: load_profile()
// Loads the user plus their Customers / EventOrganizers row in one query
// ==============================================================
function load_profile(PDO $pdo, int $uid): array {
    $st = $pdo->prepare(
        'SELECT u.UserID, u.Username, u.Email, u.Phone, u.City, u.ProfileImg, u.NotificationType, u.Role, u.RecordDateTime,
                c.BillingAddress, c.PostalCode,
                o.OrganizationName, o.BusinessEmail, o.BusinessPhone, o.Description, o.VerificationStatus
         FROM Users u
         LEFT JOIN Customers c ON c.UserID = u.UserID
         LEFT JOIN EventOrganizers o ON o.UserID = u.UserID
         WHERE u.UserID = ?');
    $st->execute([$uid]);
    return $st->fetch();
}

// ==============================================================
// FUNC: profile_json()
// Converts a database row into the array sent to the page script (also builds "Organizer (Verified)" labels)
// ==============================================================
function profile_json(array $r): array {
    $acct = $r['Role'];
    if ($r['Role'] === 'Organizer' && $r['VerificationStatus']) $acct .= ' (' . $r['VerificationStatus'] . ')';
    return [
        'role'            => $r['Role'],
        'acctType'        => $acct,
        'startYear'       => date('Y', strtotime($r['RecordDateTime'])),
        'photo'           => $r['ProfileImg'] ?: '',
        'username'        => $r['Username'],
        'email'           => $r['Email'],
        'phone'           => $r['Phone'] ?? '',
        'city'            => $r['City'] ?? '',
        'notif'           => $r['NotificationType'],
        'billingAddress'  => $r['BillingAddress'] ?? '',
        'postalCode'      => $r['PostalCode'] ?? '',
        'organizationName'=> $r['OrganizationName'] ?? '',
        'businessEmail'   => $r['BusinessEmail'] ?? '',
        'businessPhone'   => $r['BusinessPhone'] ?? '',
        'description'     => $r['Description'] ?? '',
    ];
}

// ==============================================================
// SECTION: SAVE HANDLER (POST, returns JSON)
// Order: CSRF -> read input -> validate -> uniqueness -> photo -> save in a transaction -> respond
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- PHP: JSON response and the $fail() helper that sends errors back and stops ---
    header('Content-Type: application/json');
    $fail = fn(array $errs, string $msg = 'Fix the highlighted fields to save.') =>
        exit(json_encode(['ok' => false, 'errors' => $errs, 'message' => $msg]));

    // --- PHP: security check - reject forged / expired requests ---
    if (!csrf_ok()) { http_response_code(403); $fail([], 'Your session expired. Reload the page and try again.'); }

    // --- PHP: current data, the user's role, and small input helpers ($in trims, $nul turns "" into NULL) ---
    $row  = load_profile($pdo, $uid);
    $role = $row['Role'];
    $in   = fn(string $k) => trim((string)($_POST[$k] ?? ''));
    $nul  = fn(string $v) => $v === '' ? null : $v;
    $phoneRe = '/^\d{3}-\d{3}-\d{4}$/';
    $errs = [];

    // --- PHP: shared field validation (all roles) ---
    $username = $in('username'); $email = $in('email'); $phone = $in('phone'); $city = $in('city');
    $notif = $in('notif');

    if ($m = valid_username($username)) $errs['username'] = $m;
    if (!valid_email($email))           $errs['email'] = 'Enter an email like name@example.com.';
    if ($phone !== '' && !preg_match($phoneRe, $phone)) $errs['phone'] = 'Enter a phone number as xxx-xxx-xxxx.';
    if ($city !== '' && (mb_strlen($city) > 100 || !preg_match('/^[\p{L}][\p{L} .\'\-]*$/u', $city)))
        $errs['city'] = "City can only include letters, spaces, and . ' -";
    if (!in_array($notif, NOTIFICATION_OPTIONS, true)) $notif = $row['NotificationType'];

    // --- PHP: role-specific fields (Customer: billing, Organizer: organisation) ---
    $billing = $postal = $org = $bEmail = $bPhone = $desc = '';
    if ($role === 'Customer') {
        $billing = $in('billingAddress'); $postal = $in('postalCode');
        if (mb_strlen($billing) > 255) $errs['billingAddress'] = 'Keep the address under 255 characters.';
        if ($postal !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{1,18}$/', $postal)) $errs['postalCode'] = 'Enter a valid postal code.';
    } elseif ($role === 'Organizer') {
        $org = $in('organizationName'); $bEmail = $in('businessEmail'); $bPhone = $in('businessPhone'); $desc = $in('description');
        if (mb_strlen($org) > 150) $errs['organizationName'] = 'Keep the name under 150 characters.';
        if ($bEmail !== '' && !valid_email($bEmail)) $errs['businessEmail'] = 'Enter an email like name@example.com.';
        if ($bPhone !== '' && !preg_match($phoneRe, $bPhone)) $errs['businessPhone'] = 'Enter a phone number as xxx-xxx-xxxx.';
        if (mb_strlen($desc) > 2000) $errs['description'] = 'Keep the description under 2000 characters.';
    }

    // --- PHP: uniqueness - username and email must not belong to someone else ---
    if (!isset($errs['username']) || !isset($errs['email'])) {
        $st = $pdo->prepare('SELECT Username, Email FROM Users WHERE (Username = ? OR Email = ?) AND UserID <> ?');
        $st->execute([$username, $email, $uid]);
        foreach ($st->fetchAll() as $o) {
            if (strcasecmp($o['Username'], $username) === 0) $errs['username'] = 'That username is taken.';
            if (strcasecmp($o['Email'], $email) === 0)       $errs['email'] = 'That email is already used by another account.';
        }
    }

    // --- PHP: profile photo upload - checks size and real image type, saves under a random name ---
    $newImg = $row['ProfileImg']; $savedFile = null;
    if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['photo'];
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) $errs['photo'] = 'Choose an image under 5 MB.';
        elseif (!isset($types[$mime]) || !@getimagesize($f['tmp_name'])) $errs['photo'] = 'Choose a JPG, PNG, GIF or WebP image.';
        elseif (!$errs) {
            $name = $uid . '_' . bin2hex(random_bytes(8)) . '.' . $types[$mime];
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) { $newImg = UPLOAD_URL . $name; $savedFile = UPLOAD_DIR . $name; }
            else $errs['photo'] = 'Could not save the image. Try again.';
        }
    }

    // --- PHP: any errors? undo the uploaded file and send the errors back ---
    if ($errs) { if ($savedFile) @unlink($savedFile); $fail($errs); }

    // --- PHP: save everything in one transaction (update Users, then upsert Customers / EventOrganizers) ---
    try {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE Users SET Username=?, Email=?, Phone=?, City=?, NotificationType=?, ProfileImg=? WHERE UserID=?')
            ->execute([$username, $email, $nul($phone), $nul($city), $notif, $newImg, $uid]);

        if ($role === 'Customer') {
            $pdo->prepare('INSERT INTO Customers (UserID, BillingAddress, PostalCode, RecordDateTime, CreatedBy) VALUES (?, ?, ?, NOW(), ?)
                           ON DUPLICATE KEY UPDATE BillingAddress = VALUES(BillingAddress), PostalCode = VALUES(PostalCode)')
                ->execute([$uid, $nul($billing), $nul($postal), $uid]);
        } elseif ($role === 'Organizer') {
            $pdo->prepare('INSERT INTO EventOrganizers (UserID, OrganizationName, BusinessEmail, BusinessPhone, Description, RecordDateTime, CreatedBy) VALUES (?, ?, ?, ?, ?, NOW(), ?)
                           ON DUPLICATE KEY UPDATE OrganizationName = VALUES(OrganizationName), BusinessEmail = VALUES(BusinessEmail),
                                                   BusinessPhone = VALUES(BusinessPhone), Description = VALUES(Description)')
                ->execute([$uid, $nul($org), $nul($bEmail), $nul($bPhone), $nul($desc), $uid]);
        }
        $pdo->commit();
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($savedFile) @unlink($savedFile);
        if ($ex->getCode() === '23000') $fail(['username' => 'That username or email is already taken.']);
        throw $ex;
    }

    // --- PHP: tidy up - delete the previous photo file now that the new one is saved ---
    if ($savedFile && $row['ProfileImg'] && str_starts_with($row['ProfileImg'], UPLOAD_URL)) @unlink(__DIR__ . '/' . $row['ProfileImg']);

    // --- PHP: success - return the refreshed profile ---
    echo json_encode(['ok' => true, 'profile' => profile_json(load_profile($pdo, $uid))]);
    exit;
}

// ==============================================================
// SECTION: PAGE RENDER (GET)
// Data for the page; $isCustomer / $isOrganizer pick which extra form section is shown
// ==============================================================
$profile = profile_json(load_profile($pdo, $uid));
$isCustomer  = $profile['role'] === 'Customer';
$isOrganizer = $profile['role'] === 'Organizer';
?>
<!DOCTYPE html>
<!-- ==============================================================
     HTML: PAGE
     Head (meta, fonts, styles), profile card markup, then the page script
     ============================================================== -->
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>

  /* --- CSS: DESIGN TOKENS --- */
  :root {
    --card: #d9d9d9;
    --ink: #111;
    --muted: #333;
    --error: #a1221c;
    --sans: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
    --serif: "DM Serif Display", Georgia, "Times New Roman", serif;
  }

  /* --- CSS: BASE / RESET --- */
  * { box-sizing: border-box; }
  html, body { height: 100%; margin: 0; }

  /* --- CSS: PAGE BACKGROUND (copper stripes) & POSITIONING --- */
  body {
    font-family: var(--sans);
    color: var(--ink);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 4vw 8vw;
    /* corrugated copper backdrop */
    background:
      repeating-linear-gradient(90deg,
        rgba(255,255,255,.18) 0, rgba(255,255,255,0) 14px,
        rgba(120,50,25,.16) 28px, rgba(255,255,255,0) 42px,
        rgba(255,255,255,.18) 56px),
      linear-gradient(180deg, #e9b99f 0%, #e0a88b 55%, #eaa688 100%);
    background-color: #e2aa8d;
  }

  /* --- CSS: PROFILE CARD (base size - enlarged further down) --- */
  .card {
    width: 100%;
    max-width: 262px;
    min-width: 280px;
    background: var(--card);
    border-radius: 10px;
    padding: 18px 15px 15px;
    box-shadow: 0 1px 2px rgba(0,0,0,.08);
  }

  /* --- CSS: BACK-TO-HOME LINK --- */
  .home-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: var(--ink);
    text-decoration: none;
    margin-bottom: 12px;
    padding: 4px 0;
  }
  .home-link svg { width: 14px; height: 14px; flex: none; }
  .home-link:hover span { text-decoration: underline; }
  .home-link:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; border-radius: 4px; }

  /* --- CSS: CARD HEADER - avatar, name, account type --- */
  .head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
  .avatar {
    position: relative;
    flex: none;
    width: 42px; height: 42px;
    border-radius: 50%;
    background: #fff;
    display: grid; place-items: center;
    font-size: 22px; font-weight: 400;
    overflow: hidden;
    border: 0; padding: 0;
    color: var(--ink);
    font-family: var(--sans);
    cursor: default;
  }
  .avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .avatar .overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,.55);
    color: #fff; font-size: 9px; line-height: 1.1;
    display: none; place-items: center; text-align: center;
  }
  .editing .avatar { cursor: pointer; }
  .editing .avatar .overlay { display: grid; }
  .avatar:focus-visible, .btn:focus-visible, select:focus-visible, input:focus-visible {
    outline: 2px solid var(--ink); outline-offset: 2px;
  }
  .name { font-size: 16px; font-weight: 500; line-height: 1.2; }
  .meta { font-size: 10px; font-style: italic; color: var(--muted); margin-top: 2px; }

  /* --- CSS: FORM FIELDS - read-only by default, boxed while editing --- */
  .field { margin-bottom: 8px; }
  label {
    display: block;
    font-size: 10px; font-style: italic;
    color: var(--ink);
    margin-bottom: 2px;
  }
  input, select {
    width: 100%;
    font: inherit; font-size: 12px;
    color: var(--ink);
    background: transparent;
    border: 0;
    border-bottom: 1px solid var(--ink);
    border-radius: 0;
    padding: 6px 0 5px;
    appearance: none;
    -webkit-appearance: none;
  }
  input::placeholder { color: #777; }
  input:read-only, select:disabled { cursor: default; opacity: 1; color: var(--ink); }
  .editing input:not(:read-only), .editing select:not(:disabled) {
    background: rgba(255,255,255,.55);
    padding-left: 6px; padding-right: 6px;
    border-bottom-width: 2px;
  }
  .editing select:not(:disabled) {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%23111' stroke-width='1.5'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 6px center;
    padding-right: 22px;
  }
  select:disabled { background-image: none; }
  .invalid { border-bottom-color: var(--error) !important; }
  .err { min-height: 12px; font-size: 9px; color: var(--error); margin-top: 2px; }

  /* --- CSS: BUTTONS & STATUS LINE --- */
  .actions { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; gap: 10px; }
  .btn {
    font-family: var(--serif);
    font-size: 9px;
    height: 26px;
    border-radius: 999px;
    cursor: pointer;
    border: 1.5px solid var(--ink);
    padding: 0 14px;
    transition: transform .08s ease;
  }
  .btn:active { transform: scale(.97); }
  .btn-primary { background: var(--ink); color: #fff; flex: 1; max-width: 104px; }
  .btn-secondary { background: #fff; color: var(--ink); min-width: 56px; }
  .status { font-size: 9px; font-style: italic; color: var(--muted); min-height: 12px; margin-top: 8px; }

  /* --- CSS: RESPONSIVE (base styles) --- */
  @media (max-width: 640px) {
    body { justify-content: center; padding: 24px 16px; }
    .card { max-width: 340px; }
  }
  @media (prefers-reduced-motion: reduce) { .btn { transition: none; } }

/* ==============================================================
   CSS: LARGER CARD OVERRIDES
   These rules come later, so they override the base sizes above
   ============================================================== */
html, body { height: auto; }
body { justify-content: center; min-height: 100vh; min-height: 100dvh; padding: 48px max(24px, 6vw); }

.card { max-width: 560px; min-width: 0; padding: 40px 44px 32px; border-radius: 16px; }

.home-link { font-size: 14px; margin-bottom: 20px; }
.home-link svg { width: 16px; height: 16px; }

.head { gap: 20px; margin-bottom: 32px; }
.avatar { width: 84px; height: 84px; font-size: 40px; }
.avatar .overlay { font-size: 12px; }
.name { font-size: 28px; }
.meta { font-size: 14px; margin-top: 4px; }

.field { margin-bottom: 18px; }
label { font-size: 14px; margin-bottom: 4px; }
input, select { font-size: 17px; padding: 10px 0 8px; }
.editing input:not(:read-only), .editing select:not(:disabled) { padding-left: 12px; padding-right: 12px; }
.editing select:not(:disabled) { background-position: right 12px center; padding-right: 36px; }
.err { font-size: 12px; min-height: 16px; }

.actions { margin-top: 28px; gap: 16px; }
.btn { font-size: 14px; height: 44px; padding: 0 28px; }
.btn-primary { max-width: 180px; }
.btn-secondary { min-width: 100px; }
.status { font-size: 13px; min-height: 16px; margin-top: 12px; }

/* --- CSS: RESPONSIVE (larger card) --- */
@media (max-width: 640px) {
  body { padding: 32px 24px; }
  .card { padding: 28px 22px 24px; }
  .home-link { margin-bottom: 16px; }
  .avatar { width: 64px; height: 64px; font-size: 30px; }
  .name { font-size: 22px; }
  input, select { font-size: 16px; } /* 16px stops iOS zooming into fields */
}

/* ==============================================================
   CSS: TEXTAREA (organisation description) & SECTION TITLES
   ============================================================== */
textarea{width:100%;font:inherit;font-size:17px;color:var(--ink);background:transparent;border:0;border-bottom:1px solid var(--ink);border-radius:0;padding:10px 0 8px;resize:vertical;min-height:84px}
textarea:read-only{cursor:default;resize:none}
.editing textarea:not(:read-only){background:rgba(255,255,255,.55);padding-left:12px;padding-right:12px;border-bottom-width:2px}
textarea:focus-visible{outline:2px solid var(--ink);outline-offset:2px}
textarea::placeholder{color:#777}
.section-title{font-size:12px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);margin:26px 0 12px;padding-top:16px;border-top:1px solid rgba(0,0,0,.18)}
</style>
</head>
<body>

  <!-- ==============================================================
       HTML: PROFILE CARD
       Back link, header (avatar + name), editable form, hidden logout form
       ============================================================== -->
  <main class="card" id="card">
    <a class="home-link" href="eventhub_landing.php">
      <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3 5 8l5 5"/></svg>
      <span>Back to home</span>
    </a>

    <!-- --- HTML: header - avatar button (click to change photo while editing) and account info --- -->
    <div class="head">
      <button class="avatar" id="avatar" type="button" aria-label="Profile picture">
        <span id="initial">U</span>
        <span class="overlay" aria-hidden="true">Change<br>photo</span>
      </button>
      <input type="file" id="photo" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
      <div>
        <div class="name" id="username">Username</div>
        <div class="meta"><span id="acctType">Acct Type</span> • Member Since <span id="startYear">StartYear</span></div>
      </div>
    </div>

    <!-- --- HTML: PROFILE FIELDS — Read-only until "Edit Information" is clicked. Fields are tied to the script via data-field. --- -->
    <form id="form" novalidate>
      <div class="field">
        <label for="usernameInput">Username</label>
        <input id="usernameInput" data-field="username" type="text" autocomplete="username" placeholder="Username" maxlength="50" readonly>
        <div class="err" id="usernameErr" role="alert"></div>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input id="email" data-field="email" type="email" inputmode="email" autocomplete="email" placeholder="name@example.com" readonly>
        <div class="err" id="emailErr" role="alert"></div>
      </div>

      <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" data-field="phone" type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="xxx-xxx-xxxx" maxlength="12" readonly>
        <div class="err" id="phoneErr" role="alert"></div>
      </div>

      <div class="field">
        <label for="city">City</label>
        <input id="city" data-field="city" type="text" autocomplete="address-level2" placeholder="City" maxlength="100" readonly>
        <div class="err" id="cityErr" role="alert"></div>
      </div>

      <div class="field">
        <label for="notif">Notifications</label>
        <select id="notif" disabled>
          <?php foreach (NOTIFICATION_OPTIONS as $opt): ?>
            <option><?= e($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

<?php /* --- PHP: CUSTOMER only - billing section --- */ ?>
<?php if ($isCustomer): ?>
      <div class="section-title">Billing</div>
      <div class="field">
        <label for="billingAddress">Billing address</label>
        <input id="billingAddress" data-field="billingAddress" type="text" autocomplete="street-address" placeholder="Street address" maxlength="255" readonly>
        <div class="err" id="billingAddressErr" role="alert"></div>
      </div>
      <div class="field">
        <label for="postalCode">Postal code</label>
        <input id="postalCode" data-field="postalCode" type="text" autocomplete="postal-code" placeholder="Postal code" maxlength="20" readonly>
        <div class="err" id="postalCodeErr" role="alert"></div>
      </div>

<?php /* --- PHP: ORGANIZER only - organisation section --- */ ?>
<?php elseif ($isOrganizer): ?>
      <div class="section-title">Organisation</div>
      <div class="field">
        <label for="organizationName">Organisation name</label>
        <input id="organizationName" data-field="organizationName" type="text" autocomplete="organization" placeholder="Company or organisation" maxlength="150" readonly>
        <div class="err" id="organizationNameErr" role="alert"></div>
      </div>
      <div class="field">
        <label for="businessEmail">Business email</label>
        <input id="businessEmail" data-field="businessEmail" type="email" inputmode="email" placeholder="tickets@example.com" readonly>
        <div class="err" id="businessEmailErr" role="alert"></div>
      </div>
      <div class="field">
        <label for="businessPhone">Business phone</label>
        <input id="businessPhone" data-field="businessPhone" type="tel" inputmode="numeric" placeholder="xxx-xxx-xxxx" maxlength="12" readonly>
        <div class="err" id="businessPhoneErr" role="alert"></div>
      </div>
      <div class="field">
        <label for="description">About your organisation</label>
        <textarea id="description" data-field="description" maxlength="2000" placeholder="Tell attendees who you are" readonly></textarea>
        <div class="err" id="descriptionErr" role="alert"></div>
      </div>
<?php endif; ?>

      <!-- --- HTML: action buttons and status message --- -->
      <div class="actions">
        <button class="btn btn-primary" id="editBtn" type="button">Edit Information</button>
        <button class="btn btn-secondary" id="signOut" type="button">Sign Out</button>
      </div>
      <div class="status" id="status" role="status" aria-live="polite"></div>
    </form>

    <!-- --- HTML: hidden form used by the Sign Out button (POST + CSRF) --- -->
    <form id="logoutForm" method="post" action="logout.php" hidden>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    </form>
  </main>

<!-- ==============================================================
     JS: PAGE SCRIPT
     Sections: state, rendering, formatting & validation, edit/save, photo, sign out
     ============================================================== -->
<script>

  // ==============================================================
  // JS: STATE & ELEMENT REFERENCES
  // profile = data from PHP; CSRF = token for saving; fields = every [data-field] input
  // ==============================================================
  let profile = <?= json_encode($profile, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  const CSRF = <?= json_encode(csrf_token()) ?>;

  // --- FUNC $(): shortcut for document.getElementById ---
  const $ = id => document.getElementById(id);
  const card = $("card"), editBtn = $("editBtn"), status = $("status");
  const fields = {};
  document.querySelectorAll("[data-field]").forEach(el => fields[el.dataset.field] = el);
  const notif = $("notif"), avatar = $("avatar"), photoInput = $("photo");
  let editing = false;
  let pendingPhoto = profile.photo;   // what the avatar shows
  let pendingFile = null;             // newly chosen file to upload

  // ==============================================================
  // JS: RENDERING
  // ==============================================================

  // --- FUNC renderAvatar(): shows the photo, or the first letter of the username when there is none ---
  function renderAvatar(src) {
    const existing = avatar.querySelector("img");
    if (existing) existing.remove();
    $("initial").style.display = src ? "none" : "";
    if (src) {
      const img = document.createElement("img");
      img.src = src; img.alt = "";
      avatar.insertBefore(img, avatar.firstChild);
    }
  }

  // --- FUNC load(): copies the profile data into the page (name, fields, avatar) ---
  function load() {
    $("username").textContent = profile.username;
    $("acctType").textContent = profile.acctType;
    $("startYear").textContent = profile.startYear;
    $("initial").textContent = (profile.username[0] || "U").toUpperCase();
    Object.keys(fields).forEach(k => fields[k].value = profile[k] ?? "");
    notif.value = profile.notif;
    pendingPhoto = profile.photo; pendingFile = null;
    renderAvatar(profile.photo);
  }

  // ==============================================================
  // JS: FORMATTING & VALIDATION
  // Instant feedback only; the server re-checks everything
  // ==============================================================

  // --- FUNC formatPhone(): turns digits into xxx-xxx-xxxx as the user types ---
  function formatPhone(v) {
    const d = v.replace(/\D/g, "").slice(0, 10);
    if (d.length > 6) return d.slice(0,3) + "-" + d.slice(3,6) + "-" + d.slice(6);
    if (d.length > 3) return d.slice(0,3) + "-" + d.slice(3);
    return d;
  }

  // --- JS: apply phone formatting to both phone inputs ---
  ["phone", "businessPhone"].forEach(k => fields[k] && fields[k].addEventListener("input", e => { e.target.value = formatPhone(e.target.value); }));

  // --- JS: VALIDATION RULES - one rule per field, each returns an error message or "" ---
  const emailOk = v => v === "" || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
  const phoneOk = v => v === "" || /^\d{3}-\d{3}-\d{4}$/.test(v) ? "" : "Enter a phone number as xxx-xxx-xxxx.";
  const rules = {
    username: v => /^[A-Za-z0-9_.\-]{3,50}$/.test(v) ? "" : "Use 3–50 letters, numbers, dots, dashes or underscores.",
    email: v => v !== "" && emailOk(v) ? "" : "Enter an email like name@example.com.",
    phone: phoneOk,
    city: v => v === "" || /^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ .'\-]*$/.test(v) ? "" : "City can only include letters, spaces, and . ' -",
    billingAddress: v => v.length <= 255 ? "" : "Keep the address under 255 characters.",
    postalCode: v => v === "" || /^[A-Za-z0-9][A-Za-z0-9 \-]{1,18}$/.test(v) ? "" : "Enter a valid postal code.",
    organizationName: v => v.length <= 150 ? "" : "Keep the name under 150 characters.",
    businessEmail: v => emailOk(v) ? "" : "Enter an email like name@example.com.",
    businessPhone: phoneOk,
    description: v => v.length <= 2000 ? "" : "Keep the description under 2000 characters."
  };

  // --- FUNC showError(): writes / clears the message under a field and marks it invalid ---
  function showError(key, msg) {
    const input = fields[key]; if (!input) return;
    $(key === "username" ? "usernameErr" : key + "Err").textContent = msg;
    input.classList.toggle("invalid", !!msg);
    input.setAttribute("aria-invalid", msg ? "true" : "false");
  }

  // --- FUNC validate(): runs the rule for one field, returns true if OK ---
  function validate(key) {
    const msg = rules[key](fields[key].value.trim());
    showError(key, msg);
    return !msg;
  }

  // --- JS: validate a field when the user leaves it (editing only) ---
  Object.keys(fields).forEach(k => fields[k].addEventListener("blur", () => editing && validate(k)));

  // ==============================================================
  // JS: EDIT / SAVE
  // ==============================================================

  // --- FUNC setEditing(): switches between read-only and edit mode ---
  function setEditing(on) {
    editing = on;
    card.classList.toggle("editing", on);
    Object.values(fields).forEach(i => i.readOnly = !on);
    notif.disabled = !on;
    editBtn.textContent = on ? "Save Changes" : "Edit Information";
    avatar.style.cursor = on ? "pointer" : "default";
    if (on) { status.textContent = ""; fields.username.focus(); }
  }

  // --- JS: Edit/Save button - first click enters edit mode; second click validates, sends the form to the server and shows the result ---
  editBtn.addEventListener("click", async () => {
    if (!editing) return setEditing(true);
    const ok = Object.keys(fields).map(validate).every(Boolean);
    if (!ok) { status.textContent = "Fix the highlighted fields to save."; return; }

    const fd = new FormData();
    fd.append("csrf", CSRF);
    Object.keys(fields).forEach(k => fd.append(k, fields[k].value.trim()));
    fd.append("notif", notif.value);
    if (pendingFile) fd.append("photo", pendingFile);

    editBtn.disabled = true; status.textContent = "Saving…";
    try {
      const res = await fetch("eventhub_profile.php", { method: "POST", body: fd, headers: { "Accept": "application/json" } });
      const data = await res.json();
      if (!data.ok) {
        Object.keys(fields).forEach(k => showError(k, (data.errors && data.errors[k]) || ""));
        status.textContent = (data.errors && data.errors.photo) || data.message || "Couldn't save changes.";
        return;
      }
      profile = data.profile;
      load();
      setEditing(false);
      status.textContent = "Changes saved.";
    } catch (err) {
      status.textContent = "Couldn't reach the server. Try again.";
    } finally {
      editBtn.disabled = false;
    }
  });

  // ==============================================================
  // JS: PROFILE PICTURE
  // Only while editing; the file uploads together with Save
  // ==============================================================
  avatar.addEventListener("click", () => { if (editing) photoInput.click(); });
  photoInput.addEventListener("change", () => {
    const file = photoInput.files[0];
    if (!file) return;
    if (!file.type.startsWith("image/")) { status.textContent = "Choose an image file (JPG, PNG, GIF or WebP)."; return; }
    if (file.size > 5 * 1024 * 1024) { status.textContent = "Choose an image under 5 MB."; return; }
    const reader = new FileReader();
    reader.onload = () => { pendingFile = file; renderAvatar(reader.result); status.textContent = "Photo will be saved with your changes."; };
    reader.readAsDataURL(file);
    photoInput.value = "";
  });

  // ==============================================================
  // JS: SIGN OUT
  // ==============================================================
  $("signOut").addEventListener("click", () => $("logoutForm").submit());

  // --- JS: INITIAL RENDER - fill the page when it opens ---
  load();
</script>
</body>
</html>
