<?php
// api/stems/download-all.php - Download all stems as ZIP archive

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$projectId = (int)($_GET['project_id'] ?? $_GET['id'] ?? 0);

if (!$projectId) {
    http_response_code(400);
    die('Project ID is required.');
}

$project = getProject($projectId, $userId);
if (!$project) {
    http_response_code(404);
    die('Project not found.');
}

$stems = getProjectStems($projectId);
if (empty($stems)) {
    http_response_code(404);
    die('No stems found for this project.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('ZipArchive extension is not enabled in PHP.');
}

$sanitizedTitle = sanitizeFilename($project['title']);
$zipFileName = "StemFlow_{$sanitizedTitle}_Stems.zip";
$tempZipPath = TEMP_PATH . '/' . uniqid('zip_', true) . '.zip';

$zip = new ZipArchive();
if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Failed to create ZIP archive.');
}

// Add each stem to zip
foreach ($stems as $type => $stem) {
    if (file_exists($stem['file_path'])) {
        $zip->addFile($stem['file_path'], "{$type}.wav");
    }
}

// Also add original audio if available
if (!empty($project['original_file_path']) && file_exists($project['original_file_path'])) {
    $zip->addFile($project['original_file_path'], "original_" . $project['original_filename']);
}

$zip->close();

if (!file_exists($tempZipPath)) {
    http_response_code(500);
    die('ZIP archive could not be generated.');
}

$zipSize = filesize($tempZipPath);

header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $zipSize);

readfile($tempZipPath);
@unlink($tempZipPath);
exit;
