<?php
// api/settings/reset.php - Reset audio settings to default

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
$stemType = trim($input['stem_type'] ?? ''); // Optional: reset only single stem

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    $db = getDB();

    if (!empty($stemType) && in_array($stemType, ['vocals', 'drums', 'bass', 'other'])) {
        // Reset single stem
        $volCol = "{$stemType}_volume";
        $pitchCol = "{$stemType}_pitch";
        $stmt = $db->prepare("UPDATE audio_settings SET $volCol = 100.00, $pitchCol = 0 WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);

        $stmtStem = $db->prepare("UPDATE stem_settings SET is_muted = 0, is_solo = 0 WHERE project_id = ? AND stem_type = ?");
        $stmtStem->execute([$projectId, $stemType]);

        $message = ucfirst($stemType) . " track reset to default.";
    } else {
        // Reset entire project settings
        $stmt = $db->prepare("
            UPDATE audio_settings 
            SET vocals_volume = 100.00, drums_volume = 100.00, bass_volume = 100.00, other_volume = 100.00,
                vocals_pitch = 0, drums_pitch = 0, bass_pitch = 0, other_pitch = 0,
                master_volume = 100.00
            WHERE project_id = ? AND user_id = ?
        ");
        $stmt->execute([$projectId, $userId]);

        $stmtStem = $db->prepare("UPDATE stem_settings SET is_muted = 0, is_solo = 0 WHERE project_id = ?");
        $stmtStem->execute([$projectId]);

        $message = "All track adjustments reset to default.";
    }

    $updatedSettings = getOrCreateAudioSettings($projectId, $userId);

    jsonResponse([
        'success' => true,
        'message' => $message,
        'settings' => $updatedSettings
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
