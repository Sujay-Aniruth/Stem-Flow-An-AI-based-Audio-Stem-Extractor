<?php
// api/processing/status.php - Check extraction job status & sync database

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

    $db = getDB();

    // Check latest job
    $stmtJob = $db->prepare("SELECT * FROM processing_jobs WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    $stmtJob->execute([$projectId]);
    $job = $stmtJob->fetch();
    $jobId = $job ? (int)$job['id'] : null;

    $outputDir = STEMS_PATH . '/' . $projectId;
    $statusFile = $outputDir . '/status.json';
    $statusData = null;

    // If status file exists on disk, inspect status
    if (file_exists($statusFile)) {
        $statusJson = @file_get_contents($statusFile);
        $statusData = json_decode($statusJson, true);

        if ($statusData && isset($statusData['status'])) {
            if ($statusData['status'] === 'completed' && $project['status'] !== 'completed') {
                // Synchronize completed stems into MySQL
                $stemsInfo = $statusData['stems'] ?? [];
                
                $db->beginTransaction();
                try {
                    foreach (['vocals', 'drums', 'bass', 'other'] as $stemType) {
                        if (isset($stemsInfo[$stemType])) {
                            $stemMeta = $stemsInfo[$stemType];
                            $filePath = $stemMeta['file_path'];
                            $fileSize = (int)$stemMeta['file_size'];
                            $duration = (float)($stemMeta['duration'] ?? $statusData['duration'] ?? 0.0);
                            $sampleRate = (int)($stemMeta['sample_rate'] ?? 44100);

                            $stmtStem = $db->prepare("
                                INSERT INTO stems 
                                (project_id, processing_job_id, stem_type, file_path, file_size, duration, sample_rate)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                    file_path = VALUES(file_path),
                                    file_size = VALUES(file_size),
                                    duration = VALUES(duration),
                                    sample_rate = VALUES(sample_rate)
                            ");
                            $stmtStem->execute([
                                $projectId,
                                $jobId,
                                $stemType,
                                $filePath,
                                $fileSize,
                                $duration,
                                $sampleRate
                            ]);
                        }
                    }

                    // Update project duration and status
                    $newDuration = (float)($statusData['duration'] ?? $project['audio_duration']);
                    $stmtProj = $db->prepare("UPDATE projects SET status = 'completed', audio_duration = ? WHERE id = ?");
                    $stmtProj->execute([$newDuration, $projectId]);

                    // Update job status
                    if ($jobId) {
                        $stmtJobUp = $db->prepare("UPDATE processing_jobs SET status = 'completed', completed_at = NOW() WHERE id = ?");
                        $stmtJobUp->execute([$jobId]);
                    }

                    $db->commit();
                    $project['status'] = 'completed';
                    app_log("Project $projectId stems synchronized into database successfully.", 'INFO');

                } catch (Exception $e) {
                    $db->rollBack();
                    app_log("Error saving stems to DB for project $projectId: " . $e->getMessage(), 'ERROR');
                }
            } elseif ($statusData['status'] === 'failed' && $project['status'] !== 'failed') {
                $errMsg = $statusData['error'] ?? 'AI processing failed.';
                $stmtProj = $db->prepare("UPDATE projects SET status = 'failed' WHERE id = ?");
                $stmtProj->execute([$projectId]);
                if ($jobId) {
                    $stmtJobUp = $db->prepare("UPDATE processing_jobs SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?");
                    $stmtJobUp->execute([$errMsg, $jobId]);
                }
                $project['status'] = 'failed';
            }
        }
    }

    // Refresh stems from DB
    $stems = getProjectStems($projectId);

    jsonResponse([
        'success' => true,
        'project_id' => $projectId,
        'status' => $statusData['status'] ?? $project['status'],
        'message' => $statusData['message'] ?? null,
        'error' => $statusData['error'] ?? null,
        'has_stems' => count($stems) === 4,
        'stems_count' => count($stems),
        'stems' => $stems,
        'job' => $job
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
