<?php
// api/projects/delete.php - Delete project and clean up audio files

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$userId = requireAuthApi();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$projectId = (int)($input['project_id'] ?? $input['id'] ?? 0);

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    $db = getDB();

    // 1. Delete associated physical files
    deleteProjectFiles($projectId, $project['original_file_path']);

    // 2. Delete database record (cascading deletes stems, settings, jobs, exports)
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);

    app_log("Project $projectId deleted by user $userId", 'INFO');

    jsonResponse([
        'success' => true,
        'message' => 'Project and all associated stems deleted successfully.'
    ]);
} catch (Exception $e) {
    app_log("Delete project error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
