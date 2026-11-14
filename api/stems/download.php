<?php
// api/stems/download.php - Secure audio file download

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$projectId = (int)($_GET['project_id'] ?? 0);
$stemType = $_GET['stem_type'] ?? '';
$stemId = (int)($_GET['stem_id'] ?? 0);
$type = $_GET['type'] ?? 'stem';

$db = getDB();

$filePath = null;
$downloadFilename = 'stem.wav';

if ($type === 'original' && $projectId > 0) {
    $project = getProject($projectId, $userId);
    if (!$project) {
        http_response_code(404);
        die('Project not found');
    }
    $filePath = $project['original_file_path'];
    $downloadFilename = $project['original_filename'];
} else {
    if ($stemId > 0) {
        $stmt = $db->prepare("
            SELECT s.*, p.title 
            FROM stems s 
            JOIN projects p ON s.project_id = p.id 
            WHERE s.id = ? AND p.user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$stemId, $userId]);
        $stem = $stmt->fetch();
    } elseif ($projectId > 0 && in_array($stemType, ['vocals', 'drums', 'bass', 'other'])) {
        $stmt = $db->prepare("
            SELECT s.*, p.title 
            FROM stems s 
            JOIN projects p ON s.project_id = p.id 
            WHERE s.project_id = ? AND s.stem_type = ? AND p.user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$projectId, $stemType, $userId]);
        $stem = $stmt->fetch();
    } else {
        http_response_code(400);
        die('Invalid parameters');
    }

    if (!$stem) {
        http_response_code(404);
        die('Stem not found');
    }

    $filePath = $stem['file_path'];
    $sanitizedTitle = sanitizeFilename($stem['title']);
    $downloadFilename = "{$sanitizedTitle}_{$stem['stem_type']}.wav";
}

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    die('File not found on server');
}

$fileSize = filesize($filePath);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

readfile($filePath);
exit;
