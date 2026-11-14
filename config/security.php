<?php
// config/security.php - Security, Session, and CSRF Utilities

require_once __DIR__ . '/app.php';

// Safe session startup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Generate CSRF Token
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF Token
function verifyCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is authenticated
function isAuthenticated(): bool {
    return !empty($_SESSION['user_id']);
}

// Get authenticated user ID
function getAuthUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

// Get authenticated user session data
function getAuthUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

// Enforce auth for web pages (redirects to login)
function requireAuth(): void {
    if (!isAuthenticated()) {
        $loginUrl = BASE_URL . '/login.php';
        header("Location: $loginUrl");
        exit;
    }
}

// Enforce auth for API requests (returns JSON error)
function requireAuthApi(): int {
    if (!isAuthenticated()) {
        jsonResponse(['success' => false, 'error' => 'Authentication required.'], 401);
    }
    return (int)$_SESSION['user_id'];
}

// Sanitize string output for HTML
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Sanitize filename to prevent directory traversal
function sanitizeFilename(string $filename): string {
    $filename = basename($filename);
    // Remove null bytes and dangerous chars
    return preg_replace('/[^a-zA-Z0-9_\-\. ]/', '', $filename);
}

// Consistent JSON API responder
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
