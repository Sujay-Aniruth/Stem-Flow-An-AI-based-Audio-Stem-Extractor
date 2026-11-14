<?php
// api/auth/register.php - User Registration API

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

// Support both form-urlencoded and JSON payloads
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$name = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validation
if (empty($name) || strlen($name) < 2) {
    jsonResponse(['success' => false, 'error' => 'Full name must be at least 2 characters long.'], 422);
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'error' => 'Please provide a valid email address.'], 422);
}

if (empty($password) || strlen($password) < 6) {
    jsonResponse(['success' => false, 'error' => 'Password must be at least 6 characters long.'], 422);
}

if ($password !== $confirmPassword) {
    jsonResponse(['success' => false, 'error' => 'Passwords do not match.'], 422);
}

try {
    $db = getDB();

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'error' => 'An account with this email already exists.'], 409);
    }

    // Hash password securely
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmtInsert = $db->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
    $stmtInsert->execute([$name, $email, $passwordHash]);
    $userId = (int)$db->lastInsertId();

    // Set authenticated session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    app_log("User registered: $email (ID: $userId)", 'INFO');

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful.',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email
        ]
    ], 201);

} catch (Exception $e) {
    app_log("Registration error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
