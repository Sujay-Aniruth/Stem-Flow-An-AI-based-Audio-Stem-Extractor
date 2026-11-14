<?php
// api/stems/list.php - List all stems for a project

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

    $stems = getProjectStems($projectId);

    foreach ($stems as &$stem) {
        $stem['file_size_formatted'] = formatBytes((int)$stem['file_size']);
        $stem['duration_formatted'] = formatDuration((float)$stem['duration']);
        $stem['stream_url'] = BASE_URL . '/api/stems/stream.php?type=stem&project_id=' . $projectId . '&stem_type=' . $stem['stem_type'];
        $stem['download_url'] = BASE_URL . '/api/stems/download.php?project_id=' . $projectId . '&stem_type=' . $stem['stem_type'];
    }

    jsonResponse([
        'success' => true,
        'project_id' => $projectId,
        'count' => count($stems),
        'stems' => $stems,
        'original_stream_url' => BASE_URL . '/api/stems/stream.php?type=original&project_id=' . $projectId
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
