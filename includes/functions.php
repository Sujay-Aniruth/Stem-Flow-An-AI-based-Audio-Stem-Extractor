<?php
// includes/functions.php - Core helper functions for StemFlow AI

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

/**
 * Format bytes into human-readable size
 */
function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format seconds into mm:ss or hh:mm:ss
 */
function formatDuration(float $seconds): string {
    $seconds = max(0, (int)round($seconds));
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    if ($m >= 60) {
        $h = floor($m / 60);
        $m = $m % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
    return sprintf('%02d:%02d', $m, $s);
}

/**
 * Fetch project verifying user ownership
 */
function getProject(int $projectId, int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch();
    return $project ?: null;
}

/**
 * Fetch stems for a project
 */
function getProjectStems(int $projectId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM stems WHERE project_id = ? ORDER BY FIELD(stem_type, 'vocals', 'drums', 'bass', 'other')");
    $stmt->execute([$projectId]);
    $stems = $stmt->fetchAll();
    
    $indexed = [];
    foreach ($stems as $stem) {
        $indexed[$stem['stem_type']] = $stem;
    }
    return $indexed;
}

/**
 * Fetch or initialize audio settings for a project
 */
function getOrCreateAudioSettings(int $projectId, int $userId): array {
    $db = getDB();
    
    // Check main audio_settings
    $stmt = $db->prepare("SELECT * FROM audio_settings WHERE project_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$projectId, $userId]);
    $settings = $stmt->fetch();

    if (!$settings) {
        $stmtInsert = $db->prepare("
            INSERT INTO audio_settings 
            (project_id, user_id, vocals_volume, drums_volume, bass_volume, other_volume, vocals_pitch, drums_pitch, bass_pitch, other_pitch, master_volume)
            VALUES (?, ?, 100.00, 100.00, 100.00, 100.00, 0, 0, 0, 0, 100.00)
        ");
        $stmtInsert->execute([$projectId, $userId]);

        $settings = [
            'project_id' => $projectId,
            'user_id' => $userId,
            'vocals_volume' => 100.00,
            'drums_volume' => 100.00,
            'bass_volume' => 100.00,
            'other_volume' => 100.00,
            'vocals_pitch' => 0,
            'drums_pitch' => 0,
            'bass_pitch' => 0,
            'other_pitch' => 0,
            'master_volume' => 100.00
        ];
    }

    // Stem settings (mute / solo)
    $stemTypes = ['vocals', 'drums', 'bass', 'other'];
    $stmtStem = $db->prepare("SELECT * FROM stem_settings WHERE project_id = ?");
    $stmtStem->execute([$projectId]);
    $stemRows = $stmtStem->fetchAll();
    $stemSettings = [];
    foreach ($stemRows as $row) {
        $stemSettings[$row['stem_type']] = [
            'is_muted' => (bool)$row['is_muted'],
            'is_solo' => (bool)$row['is_solo']
        ];
    }

    // Fill missing stem defaults
    foreach ($stemTypes as $type) {
        if (!isset($stemSettings[$type])) {
            $stmtInsertStem = $db->prepare("INSERT IGNORE INTO stem_settings (project_id, stem_type, is_muted, is_solo) VALUES (?, ?, 0, 0)");
            $stmtInsertStem->execute([$projectId, $type]);
            $stemSettings[$type] = ['is_muted' => false, 'is_solo' => false];
        }
    }

    $settings['stems'] = $stemSettings;
    return $settings;
}

/**
 * Remove project files and directories safely
 */
function deleteProjectFiles(int $projectId, ?string $originalFilePath): void {
    // Delete original file
    if ($originalFilePath && file_exists($originalFilePath)) {
        @unlink($originalFilePath);
    }

    // Delete stems directory
    $stemDir = STEMS_PATH . '/' . $projectId;
    if (is_dir($stemDir)) {
        $files = glob($stemDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($stemDir);
    }

    // Delete exports directory
    $exportDir = EXPORTS_PATH . '/' . $projectId;
    if (is_dir($exportDir)) {
        $files = glob($exportDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) @unlink($file);
        }
        @rmdir($exportDir);
    }
}

/**
 * Inspect audio metadata via Python audio inspection script
 */
function inspectAudioFile(string $filePath): array {
    $python = PYTHON_BIN;
    $script = AI_ENGINE_PATH . '/audio_validation.py';
    $cmd = escapeshellcmd("$python $script inspect " . escapeshellarg($filePath));
    
    $output = shell_exec($cmd);
    if ($output) {
        $data = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($data['valid'])) {
            return $data;
        }
    }

    // Fallback if python inspection fails or returns invalid
    return [
        'valid' => true,
        'duration' => 0.0,
        'sample_rate' => 44100,
        'channels' => 2,
        'format' => pathinfo($filePath, PATHINFO_EXTENSION)
    ];
}
