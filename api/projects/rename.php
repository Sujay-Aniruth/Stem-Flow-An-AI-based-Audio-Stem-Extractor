<?php
// api/projects/rename.php - Rename an existing project

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
$title = trim($input['title'] ?? '');

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

if (empty($title) || strlen($title) < 1) {
    jsonResponse(['success' => false, 'error' => 'Project title cannot be empty.'], 422);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE projects SET title = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $projectId, $userId]);

    jsonResponse([
        'success' => true,
        'message' => 'Project renamed successfully.',
        'title' => $title
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
