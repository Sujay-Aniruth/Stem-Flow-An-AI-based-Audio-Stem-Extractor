<?php
// api/settings/get.php - Get saved audio settings for a project

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

    $settings = getOrCreateAudioSettings($projectId, $userId);

    jsonResponse([
        'success' => true,
        'project_id' => $projectId,
        'settings' => $settings
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
