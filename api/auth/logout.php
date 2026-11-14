<?php
// api/auth/logout.php - User Logout API

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

jsonResponse([
    'success' => true,
    'message' => 'Logged out successfully.'
]);
