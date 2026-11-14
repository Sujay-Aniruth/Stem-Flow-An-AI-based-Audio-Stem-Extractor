<?php
// api/audio/upload.php - Audio Upload Handler

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$userId = requireAuthApi();

// Verify file existence in $_FILES
if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonResponse(['success' => false, 'error' => 'No audio file uploaded.'], 400);
}

$file = $_FILES['audio_file'];

// Check upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Uploaded file exceeds PHP upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE  => 'Uploaded file exceeds form MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL    => 'Uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
    ];
    $errMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error.';
    jsonResponse(['success' => false, 'error' => $errMsg], 400);
}

// Check file size
if ($file['size'] > MAX_UPLOAD_SIZE) {
    jsonResponse(['success' => false, 'error' => 'File size exceeds limit of ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.'], 422);
}

// Check extension
$originalName = sanitizeFilename($file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($extension, ALLOWED_AUDIO_EXTENSIONS, true)) {
    jsonResponse(['success' => false, 'error' => 'Invalid file extension. Allowed formats: ' . implode(', ', ALLOWED_AUDIO_EXTENSIONS)], 422);
}

// Generate randomized storage filename to prevent collisions & traversal
$randomName = 'orig_' . bin2hex(random_bytes(16)) . '.' . $extension;
$targetPath = ORIGINALS_PATH . '/' . $randomName;

if (!is_dir(ORIGINALS_PATH) || !is_writable(ORIGINALS_PATH)) {
    error_log('Audio upload storage directory is missing or not writable: ' . ORIGINALS_PATH);
    jsonResponse(['success' => false, 'error' => 'Audio storage is not writable by the web server. Check the storage/originals folder permissions.'], 500);
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    error_log('Failed to move uploaded audio file into storage: ' . ORIGINALS_PATH);
    jsonResponse(['success' => false, 'error' => 'Failed to save uploaded audio file.'], 500);
}

// Inspect and validate media content via Python AI inspection
$meta = inspectAudioFile($targetPath);
if (empty($meta['valid'])) {
    @unlink($targetPath);
    jsonResponse(['success' => false, 'error' => 'Audio validation failed: ' . ($meta['error'] ?? 'Corrupt audio file.')], 422);
}

// Project title from form or fallback to file stem
$title = trim($_POST['title'] ?? '');
if (empty($title)) {
    $title = pathinfo($originalName, PATHINFO_FILENAME);
}

try {
    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO projects 
        (user_id, title, original_filename, original_file_path, file_size, audio_duration, sample_rate, channels, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'uploaded')
    ");

    $stmt->execute([
        $userId,
        $title,
        $originalName,
        $targetPath,
        (int)$file['size'],
        (float)($meta['duration'] ?? 0.0),
        (int)($meta['sample_rate'] ?? 44100),
        (int)($meta['channels'] ?? 2)
    ]);

    $projectId = (int)$db->lastInsertId();

    // Initialize default audio settings for this project
    getOrCreateAudioSettings($projectId, $userId);

    app_log("Audio uploaded successfully for Project $projectId ($originalName, {$meta['duration']}s)", 'INFO');

    jsonResponse([
        'success' => true,
        'message' => 'Audio uploaded and validated successfully.',
        'project' => [
            'id' => $projectId,
            'title' => $title,
            'original_filename' => $originalName,
            'file_size' => (int)$file['size'],
            'file_size_formatted' => formatBytes((int)$file['size']),
            'duration' => (float)($meta['duration'] ?? 0.0),
            'duration_formatted' => formatDuration((float)($meta['duration'] ?? 0.0)),
            'sample_rate' => (int)($meta['sample_rate'] ?? 44100),
            'channels' => (int)($meta['channels'] ?? 2),
            'status' => 'uploaded'
        ]
    ], 201);

} catch (Exception $e) {
    @unlink($targetPath);
    app_log("Upload database error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
