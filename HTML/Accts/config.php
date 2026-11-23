<?php
// ==============================================================
// FILE: config.php
// Shared bootstrap: settings, session, error handling, database, security
// helpers and validation. Every page starts with require_once of this file.
// Search tags used below: SECTION:, FUNC:, CONST:
// ==============================================================
declare(strict_types=1);

// ==============================================================
// SECTION: SETTINGS
// Edit these values for your server. Nothing else in this file needs changing.
// ==============================================================

// --- CONST: database connection ---
const DB_HOST   = 'localhost';
const DB_NAME   = 'eventhub';
const DB_USER   = 'root';
const DB_PASS   = '';

// --- CONST: site address and email (used in password-reset emails) ---
const SITE_URL  = '';            // e.g. 'https://example.com/eventhub' (used in reset emails). Blank = auto-detect.
const MAIL_FROM = 'no-reply@eventhub.local';

// --- CONST: developer mode - shows the reset link on screen and detailed errors. Set to false in production! ---
const DEV_MODE  = true;          // true: show the reset-confirmation link on screen. SET TO false IN PRODUCTION.

// --- CONST: where profile pictures are saved (disk path) and how they are linked (URL path) ---
const UPLOAD_DIR = __DIR__ . '/uploads/profiles/';
const UPLOAD_URL = 'uploads/profiles/';

// --- CONST: allowed values for Users.NotificationType (must match the schema ENUM) ---
const NOTIFICATION_OPTIONS = ['Email', 'Disabled', 'Order Updates', 'Promotional Updates', 'All Updates'];

// ==============================================================
// SECTION: BOOTSTRAP
// Runs on every page load: session cookie settings, session start, global error handler
// ==============================================================

// --- Session cookie: not readable by JavaScript, sent on same-site requests, HTTPS-only when available ---
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

// --- Global error handler: logs the error, shows a friendly message (JSON for AJAX, HTML otherwise) ---
set_exception_handler(function (Throwable $t) {
    error_log((string)$t);
    http_response_code(500);
    $detail = DEV_MODE ? $t->getMessage() : '';
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Something went wrong. Please try again.' . ($detail ? " ($detail)" : '')]);
    } else {
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Error</title><p style="font:16px sans-serif;padding:2rem">Something went wrong. Please try again in a moment.'
           . ($detail ? '<br><br><code>' . htmlspecialchars($detail) . '</code>' : '') . '</p>';
    }
    exit;
});

// ==============================================================
// FUNC: db()
// Returns the shared PDO database connection (created on first call)
// ==============================================================
function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ==============================================================
// FUNC: e()
// Escapes text for safe output in HTML (prevents XSS). Use on every printed value.
// ==============================================================
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ==============================================================
// FUNC: redirect()
// Sends the browser to another page and stops the script
// ==============================================================
function redirect(string $url): never { header('Location: ' . $url); exit; }

// ==============================================================
// SECTION: CSRF PROTECTION
// Token placed in every form; checked on every POST
// ==============================================================

// --- FUNC: csrf_token() — Returns this session's CSRF token (creates one if needed) ---
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

// --- FUNC: csrf_ok() — True if the submitted token matches the session token ---
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

// ==============================================================
// SECTION: FLASH MESSAGES
// One-time messages shown on the next page (e.g. "password updated")
// ==============================================================
function flash_set(string $msg): void { $_SESSION['flash'] = $msg; }
function flash_get(): string { $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $m; }

// ==============================================================
// SECTION: USERS & AUTHENTICATION
// Look up, require and sign in users
// ==============================================================

// --- FUNC: current_user() — Returns the signed-in, active user row (or null for guests). Cached per request. ---
function current_user(): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT UserID, Username, Email, Role, ProfileImg FROM Users WHERE UserID = ? AND IsActive = 1');
        $st->execute([$_SESSION['uid']]);
        $cache = $st->fetch() ?: null;
        if (!$cache) unset($_SESSION['uid']);
    }
    return $cache;
}

// --- FUNC: require_login() — Use at the top of private pages: redirects guests to the sign-in page ---
function require_login(): array {
    $u = current_user();
    if (!$u) redirect('eventhub_signIn.php');
    return $u;
}

// --- FUNC: find_user_by_identity() — Finds a user by email OR username (used by sign-in and password reset) ---
function find_user_by_identity(string $identity): ?array {
    $st = db()->prepare('SELECT * FROM Users WHERE Email = ? OR Username = ? LIMIT 1');
    $st->execute([$identity, $identity]);
    return $st->fetch() ?: null;
}

// --- FUNC: log_in() — Starts a session for the user (new session ID prevents fixation) then goes to the home page ---
function log_in(array $user): never {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['UserID'];
    redirect('eventhub_landing.php');
}

// ==============================================================
// SECTION: VALIDATION
// Shared input checks (used by sign-up, profile and password reset).
// FUNC: valid_username() - returns an error message or null; 3-50 letters, numbers, dots, dashes or underscores
// FUNC: valid_email()    - true/false; valid format, max 255 characters
// FUNC: valid_password() - returns an error message or null; 8-72 characters (72 is bcrypt's limit)
// ==============================================================
function valid_username(string $u): ?string {
    if ($u === '') return 'Enter a username.';
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $u)) return 'Use 3–50 letters, numbers, dots, dashes or underscores.';
    return null;
}
function valid_email(string $e): bool { return strlen($e) <= 255 && (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
function valid_password(string $p): ?string {
    if ($p === '') return 'Enter a password.';
    if (strlen($p) < 8) return 'Use at least 8 characters.';
    if (strlen($p) > 72) return 'Use 72 characters or fewer.';
    return null;
}

// ==============================================================
// FUNC: site_url()
// Base URL of the site (SITE_URL setting, or auto-detected). Used to build emailed links.
// ==============================================================
function site_url(): string {
    if (SITE_URL !== '') return rtrim(SITE_URL, '/');
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
}
