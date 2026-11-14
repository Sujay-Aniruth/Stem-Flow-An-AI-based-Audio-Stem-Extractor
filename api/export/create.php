<?php
// api/export/create.php - Trigger adjusted stem rendering & export

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

$projectId = (int)($input['project_id'] ?? 0);

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    if ($project['status'] !== 'completed') {
        jsonResponse(['success' => false, 'error' => 'Stems must be extracted before exporting.'], 400);
    }

    $stemsDir = STEMS_PATH . '/' . $projectId;
    if (!is_dir($stemsDir)) {
        jsonResponse(['success' => false, 'error' => 'Stems directory not found.'], 404);
    }

    $exportDir = EXPORTS_PATH . '/' . $projectId;
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    // Retrieve saved audio settings
    $settings = getOrCreateAudioSettings($projectId, $userId);

    // If request contains updated settings, merge them
    if (!empty($input['settings']) && is_array($input['settings'])) {
        $settings = array_merge($settings, $input['settings']);
    }

    $db = getDB();

    $stmtExport = $db->prepare("
        INSERT INTO exports (project_id, user_id, export_type, file_path, status)
        VALUES (?, ?, 'adjusted_stems', '', 'processing')
    ");
    $stmtExport->execute([$projectId, $userId]);
    $exportId = (int)$db->lastInsertId();

    $settingsJsonFile = $exportDir . '/settings_' . $exportId . '.json';
    file_put_contents($settingsJsonFile, json_encode($settings, JSON_PRETTY_PRINT));

    $statusFile = $exportDir . '/export_status_' . $exportId . '.json';
    file_put_contents($statusFile, json_encode([
        'status' => 'processing',
        'export_id' => $exportId,
        'project_id' => $projectId,
        'started_at' => date('Y-m-d H:i:s')
    ]));

    // Spawn Python export processing in background
    $python = PYTHON_BIN;
    $script = AI_ENGINE_PATH . '/app.py';
    $logOutput = LOGS_PATH . '/export_project_' . $projectId . '_' . $exportId . '.log';

    $cmd = sprintf(
        '%s %s export %d %s %s %s %s > %s 2>&1 &',
        escapeshellcmd($python),
        escapeshellarg($script),
        $projectId,
        escapeshellarg($stemsDir),
        escapeshellarg($exportDir),
        escapeshellarg($settingsJsonFile),
        escapeshellarg($statusFile),
        escapeshellarg($logOutput)
    );

    pclose(popen($cmd, 'r'));

    app_log("Started adjusted stem export for Project $projectId (Export $exportId)", 'INFO');

    jsonResponse([
        'success' => true,
        'message' => 'Export job started in background.',
        'export_id' => $exportId,
        'project_id' => $projectId,
        'status' => 'processing'
    ]);

} catch (Exception $e) {
    app_log("Export creation error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
