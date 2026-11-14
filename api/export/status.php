<?php
// api/export/status.php - Check export status and download exported zip

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$exportId = (int)($_GET['export_id'] ?? 0);
$projectId = (int)($_GET['project_id'] ?? 0);
$isDownload = !empty($_GET['download']);

$db = getDB();

if ($exportId > 0) {
    $stmt = $db->prepare("SELECT * FROM exports WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$exportId, $userId]);
    $export = $stmt->fetch();
} elseif ($projectId > 0) {
    $stmt = $db->prepare("SELECT * FROM exports WHERE project_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$projectId, $userId]);
    $export = $stmt->fetch();
} else {
    jsonResponse(['success' => false, 'error' => 'Export ID or Project ID is required.'], 400);
}

if (!$export) {
    jsonResponse(['success' => false, 'error' => 'Export record not found.'], 404);
}

$exportId = (int)$export['id'];
$projectId = (int)$export['project_id'];

$exportDir = EXPORTS_PATH . '/' . $projectId;
$statusFile = $exportDir . '/export_status_' . $exportId . '.json';

// Inspect JSON status file
if (file_exists($statusFile)) {
    $statusData = json_decode(@file_get_contents($statusFile), true);
    if ($statusData && isset($statusData['status'])) {
        if ($statusData['status'] === 'completed' && $export['status'] !== 'completed') {
            $zipPath = $statusData['zip_path'] ?? '';
            $stmtUp = $db->prepare("UPDATE exports SET status = 'completed', file_path = ? WHERE id = ?");
            $stmtUp->execute([$zipPath, $exportId]);
            $export['status'] = 'completed';
            $export['file_path'] = $zipPath;
        } elseif ($statusData['status'] === 'failed' && $export['status'] !== 'failed') {
            $stmtUp = $db->prepare("UPDATE exports SET status = 'failed' WHERE id = ?");
            $stmtUp->execute([$exportId]);
            $export['status'] = 'failed';
        }
    }
}

// If direct download requested
if ($isDownload && $export['status'] === 'completed') {
    $filePath = $export['file_path'];
    if (!$filePath || !file_exists($filePath)) {
        http_response_code(404);
        die('Export file not found on disk.');
    }

    $project = getProject($projectId, $userId);
    $title = $project ? sanitizeFilename($project['title']) : "Project_$projectId";
    $downloadName = "StemFlow_{$title}_Adjusted_Stems.zip";
    $fileSize = filesize($filePath);

    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);

    readfile($filePath);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
jsonResponse([
    'success' => true,
    'export' => [
        'id' => $exportId,
        'project_id' => $projectId,
        'status' => $export['status'],
        'created_at' => $export['created_at'],
        'download_url' => $export['status'] === 'completed' 
            ? BASE_URL . "/api/export/status.php?export_id={$exportId}&download=1" 
            : null
    ]
]);
