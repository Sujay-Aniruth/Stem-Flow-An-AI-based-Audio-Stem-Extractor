<?php
// api/stems/stream.php - Secure audio streaming with HTTP Range support

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check authentication
if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$projectId = (int)($_GET['project_id'] ?? 0);
$type = $_GET['type'] ?? 'stem'; // 'original' or 'stem'
$stemType = $_GET['stem_type'] ?? '';
$stemId = (int)($_GET['stem_id'] ?? 0);

$db = getDB();

$filePath = null;
$mimeType = 'audio/wav';

if ($type === 'original' && $projectId > 0) {
    $project = getProject($projectId, $userId);
    if (!$project) {
        http_response_code(404);
        die('Project not found');
    }
    $filePath = $project['original_file_path'];
    $ext = strtolower(pathinfo($project['original_filename'], PATHINFO_EXTENSION));
    $mimes = [
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'm4a' => 'audio/mp4',
        'ogg' => 'audio/ogg'
    ];
    $mimeType = $mimes[$ext] ?? 'audio/mpeg';

} else {
    // Stem streaming
    if ($stemId > 0) {
        $stmt = $db->prepare("
            SELECT s.*, p.user_id 
            FROM stems s 
            JOIN projects p ON s.project_id = p.id 
            WHERE s.id = ? AND p.user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$stemId, $userId]);
        $stem = $stmt->fetch();
    } elseif ($projectId > 0 && in_array($stemType, ['vocals', 'drums', 'bass', 'other'])) {
        $stmt = $db->prepare("
            SELECT s.*, p.user_id 
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
    $mimeType = 'audio/wav';
}

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    die('Audio file not found on disk');
}

$fileSize = filesize($filePath);
$start = 0;
$end = $fileSize - 1;

// Process HTTP Range Header if present
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
        $start = intval($matches[1]);
        if (!empty($matches[2])) {
            $end = intval($matches[2]);
        }
    }

    if ($start > $end || $start >= $fileSize) {
        header("HTTP/1.1 416 Requested Range Not Satisfiable");
        header("Content-Range: bytes */$fileSize");
        exit;
    }

    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$fileSize");
} else {
    header("HTTP/1.1 200 OK");
}

$length = ($end - $start) + 1;

header("Content-Type: $mimeType");
header("Accept-Ranges: bytes");
header("Content-Length: $length");
header("Cache-Control: public, max-age=86400");
header("X-Content-Type-Options: nosniff");

// Stream binary content
$fp = @fopen($filePath, 'rb');
if ($fp) {
    fseek($fp, $start);
    $bytesRemaining = $length;
    $chunkSize = 1024 * 64; // 64 KB chunks

    while (!feof($fp) && $bytesRemaining > 0 && connection_status() === CONNECTION_NORMAL) {
        $readSize = min($chunkSize, $bytesRemaining);
        $buffer = fread($fp, $readSize);
        echo $buffer;
        flush();
        $bytesRemaining -= strlen($buffer);
    }
    fclose($fp);
}
exit;
