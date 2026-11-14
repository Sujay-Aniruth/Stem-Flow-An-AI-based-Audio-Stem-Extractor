<?php
// api/settings/save.php - Save user audio adjustments (volume, pitch, mute, solo)

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

    $db = getDB();

    // Volume ranges: 0 - 200
    $vocalsVolume = max(0.0, min(200.0, (float)($input['vocals_volume'] ?? 100.0)));
    $drumsVolume  = max(0.0, min(200.0, (float)($input['drums_volume'] ?? 100.0)));
    $bassVolume   = max(0.0, min(200.0, (float)($input['bass_volume'] ?? 100.0)));
    $otherVolume  = max(0.0, min(200.0, (float)($input['other_volume'] ?? 100.0)));
    $masterVolume = max(0.0, min(200.0, (float)($input['master_volume'] ?? 100.0)));

    // Pitch ranges: -12 - +12 semitones
    $vocalsPitch = max(-12, min(12, (int)($input['vocals_pitch'] ?? 0)));
    $drumsPitch  = max(-12, min(12, (int)($input['drums_pitch'] ?? 0)));
    $bassPitch   = max(-12, min(12, (int)($input['bass_pitch'] ?? 0)));
    $otherPitch  = max(-12, min(12, (int)($input['other_pitch'] ?? 0)));

    // Upsert main audio settings
    $stmt = $db->prepare("
        INSERT INTO audio_settings 
        (project_id, user_id, vocals_volume, drums_volume, bass_volume, other_volume, vocals_pitch, drums_pitch, bass_pitch, other_pitch, master_volume)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            vocals_volume = VALUES(vocals_volume),
            drums_volume = VALUES(drums_volume),
            bass_volume = VALUES(bass_volume),
            other_volume = VALUES(other_volume),
            vocals_pitch = VALUES(vocals_pitch),
            drums_pitch = VALUES(drums_pitch),
            bass_pitch = VALUES(bass_pitch),
            other_pitch = VALUES(other_pitch),
            master_volume = VALUES(master_volume)
    ");

    $stmt->execute([
        $projectId, $userId,
        $vocalsVolume, $drumsVolume, $bassVolume, $otherVolume,
        $vocalsPitch, $drumsPitch, $bassPitch, $otherPitch,
        $masterVolume
    ]);

    // Upsert stem mute & solo settings
    if (!empty($input['stems']) && is_array($input['stems'])) {
        foreach (['vocals', 'drums', 'bass', 'other'] as $stemType) {
            if (isset($input['stems'][$stemType])) {
                $isMuted = !empty($input['stems'][$stemType]['is_muted']) ? 1 : 0;
                $isSolo  = !empty($input['stems'][$stemType]['is_solo']) ? 1 : 0;

                $stmtStem = $db->prepare("
                    INSERT INTO stem_settings (project_id, stem_type, is_muted, is_solo)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_muted = VALUES(is_muted),
                        is_solo = VALUES(is_solo)
                ");
                $stmtStem->execute([$projectId, $stemType, $isMuted, $isSolo]);
            }
        }
    }

    jsonResponse([
        'success' => true,
        'message' => 'Audio settings saved successfully.',
        'saved_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
