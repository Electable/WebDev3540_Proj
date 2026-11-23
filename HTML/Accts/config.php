<?php
declare(strict_types=1);

/* ---------- Settings: edit these ---------- */
const DB_HOST   = 'localhost';
const DB_NAME   = 'eventhub';
const DB_USER   = 'root';
const DB_PASS   = '';
const SITE_URL  = '';            // e.g. 'https://example.com/eventhub' (used in reset emails). Blank = auto-detect.
const MAIL_FROM = 'no-reply@eventhub.local';
const DEV_MODE  = true;          // true: show the reset-confirmation link on screen. SET TO false IN PRODUCTION.

const UPLOAD_DIR = __DIR__ . '/uploads/profiles/';
const UPLOAD_URL = 'uploads/profiles/';

const NOTIFICATION_OPTIONS = ['Email', 'Disabled', 'Order Updates', 'Promotional Updates', 'All Updates'];

/* ---------- Bootstrap ---------- */
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

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

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function redirect(string $url): never { header('Location: ' . $url); exit; }

/* CSRF */
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* One-time messages shown on the next page */
function flash_set(string $msg): void { $_SESSION['flash'] = $msg; }
function flash_get(): string { $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $m; }

/* Users */
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

function require_login(): array {
    $u = current_user();
    if (!$u) redirect('eventhub_signIn.php');
    return $u;
}

function find_user_by_identity(string $identity): ?array {
    $st = db()->prepare('SELECT * FROM Users WHERE Email = ? OR Username = ? LIMIT 1');
    $st->execute([$identity, $identity]);
    return $st->fetch() ?: null;
}

function log_in(array $user): never {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$user['UserID'];
    redirect('eventhub_landing.php');
}

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

function site_url(): string {
    if (SITE_URL !== '') return rtrim(SITE_URL, '/');
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
}
