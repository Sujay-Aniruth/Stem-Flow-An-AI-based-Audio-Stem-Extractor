<?php
// api/auth/login.php - User Login API

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonResponse(['success' => false, 'error' => 'Email and password are required.'], 422);
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['success' => false, 'error' => 'Invalid email or password.'], 401);
    }

    // Login successful
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    app_log("User logged in: {$user['email']} (ID: {$user['id']})", 'INFO');

    jsonResponse([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);

} catch (Exception $e) {
    app_log("Login error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
