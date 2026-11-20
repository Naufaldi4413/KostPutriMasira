<?php
/**
 * Logout Script
 * Sistem Pengelolaan Kost
 */

require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

session_start();
setFlash('info', 'Anda telah berhasil keluar (logout).');

redirect(base_url('login.php'));
