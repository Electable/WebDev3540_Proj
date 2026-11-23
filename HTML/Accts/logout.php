<?php
// ==============================================================
// FILE: logout.php
// Signs the user out. Accepts POST only (with a valid CSRF token).
// Search tags used below: SECTION:
// ==============================================================
require_once __DIR__ . '/config.php';

// --- SECTION: PERFORM LOGOUT — Only act on a POST that carries a valid CSRF token; anything else just redirects ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    // --- Step 1: wipe all session data ---
    $_SESSION = [];

    // --- Step 2: expire the session cookie in the browser ---
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    // --- Step 3: destroy the server-side session ---
    session_destroy();
}

// --- SECTION: REDIRECT — Always land back on the home page ---
redirect('eventhub_landing.php');
