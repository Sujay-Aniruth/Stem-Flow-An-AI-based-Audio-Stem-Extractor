<?php
// config/app.php - StemFlow AI Application Configuration

define('APP_NAME', 'StemFlow AI');
define('APP_VERSION', '1.0.0');

// Base Paths
define('ROOT_PATH', realpath(__DIR__ . '/..'));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('ORIGINALS_PATH', STORAGE_PATH . '/originals');
define('STEMS_PATH', STORAGE_PATH . '/stems');
define('EXPORTS_PATH', STORAGE_PATH . '/exports');
define('TEMP_PATH', STORAGE_PATH . '/temp');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('AI_ENGINE_PATH', ROOT_PATH . '/ai_engine');

// Python virtual environment or system Python
$venvPython = ROOT_PATH . '/.venv/bin/python3';
if (file_exists($venvPython)) {
    define('PYTHON_BIN', $venvPython);
} else {
    define('PYTHON_BIN', 'python3');
}

// Upload & Audio Limits
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100 MB
define('MAX_AUDIO_DURATION', 900); // 15 minutes (in seconds)
define('ALLOWED_AUDIO_EXTENSIONS', ['mp3', 'wav', 'flac', 'm4a', 'ogg']);
define('ALLOWED_AUDIO_MIMES', [
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/x-wav',
    'audio/flac',
    'audio/x-flac',
    'audio/mp4',
    'audio/x-m4a',
    'audio/m4a',
    'audio/ogg',
    'application/octet-stream' // Fallback for some browsers on wav/flac
]);

// Base URL calculation
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($scriptName, '/');
// If script is in /api/..., resolve back to root
if (preg_match('#/api(/|$)#', $baseUrl)) {
    $baseUrl = preg_replace('#/api(/.*)?$#', '', $baseUrl);
}
define('BASE_URL', $baseUrl);

// Error logging helper
function app_log($message, $level = 'INFO') {
    $logFile = LOGS_PATH . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}
