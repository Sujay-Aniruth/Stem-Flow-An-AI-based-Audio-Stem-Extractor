<?php
// api/projects/get.php - Fetch single project with stems and settings

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuthApi();
$projectId = (int)($_GET['id'] ?? $_GET['project_id'] ?? 0);

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    $project['file_size_formatted'] = formatBytes((int)$project['file_size']);
    $project['duration_formatted'] = formatDuration((float)$project['audio_duration']);
    $project['created_at_formatted'] = date('M j, Y g:i A', strtotime($project['created_at']));

    $stems = getProjectStems($projectId);
    $settings = getOrCreateAudioSettings($projectId, $userId);

    // Fetch latest processing job status if any
    $db = getDB();
    $stmtJob = $db->prepare("SELECT * FROM processing_jobs WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    $stmtJob->execute([$projectId]);
    $job = $stmtJob->fetch();

    jsonResponse([
        'success' => true,
        'project' => $project,
        'stems' => $stems,
        'settings' => $settings,
        'job' => $job
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
