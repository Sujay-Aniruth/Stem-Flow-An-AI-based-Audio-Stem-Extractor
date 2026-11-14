<?php
// api/audio/metadata.php - Get audio metadata for a project

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuthApi();
$projectId = (int)($_GET['project_id'] ?? $_GET['id'] ?? 0);

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    $filePath = $project['original_file_path'];
    if (!file_exists($filePath)) {
        jsonResponse(['success' => false, 'error' => 'Original audio file missing on server.'], 404);
    }

    $meta = inspectAudioFile($filePath);

    jsonResponse([
        'success' => true,
        'metadata' => array_merge($meta, [
            'original_filename' => $project['original_filename'],
            'title' => $project['title'],
            'status' => $project['status']
        ])
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
