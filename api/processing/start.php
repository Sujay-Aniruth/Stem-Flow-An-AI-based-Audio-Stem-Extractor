<?php
// api/processing/start.php - Start AI stem extraction

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
$jobId = null;
$statusFile = null;

if (!$projectId) {
    jsonResponse(['success' => false, 'error' => 'Project ID is required.'], 400);
}

try {
    $project = getProject($projectId, $userId);
    if (!$project) {
        jsonResponse(['success' => false, 'error' => 'Project not found.'], 404);
    }

    $audioPath = $project['original_file_path'];
    if (!file_exists($audioPath)) {
        jsonResponse(['success' => false, 'error' => 'Original audio file missing on server.'], 404);
    }

    $db = getDB();

    // Prevent duplicate processing jobs
    if ($project['status'] === 'processing') {
        jsonResponse(['success' => true, 'message' => 'Extraction is already running for this project.', 'status' => 'processing']);
    }

    // Prepare output directory
    $outputDir = STEMS_PATH . '/' . $projectId;
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $statusFile = $outputDir . '/status.json';
    if (file_put_contents($statusFile, json_encode([
        'status' => 'queued',
        'project_id' => $projectId,
        'message' => 'Waiting for the AI worker to start processing this job.',
        'started_at' => date('Y-m-d H:i:s')
    ])) === false) {
        throw new RuntimeException('Unable to write the processing status file.');
    }

    // Record job in database
    $stmtJob = $db->prepare("
        INSERT INTO processing_jobs (project_id, user_id, model_name, status, started_at)
        VALUES (?, ?, 'htdemucs', 'processing', NOW())
    ");
    $stmtJob->execute([$projectId, $userId]);
    $jobId = (int)$db->lastInsertId();

    // Update project status
    $stmtProj = $db->prepare("UPDATE projects SET status = 'processing' WHERE id = ?");
    $stmtProj->execute([$projectId]);

    // Queue jobs for worker.py, which runs in the native Apple Silicon environment.
    $jobsDir = TEMP_PATH . '/jobs';
    if (!is_dir($jobsDir) && !mkdir($jobsDir, 0755, true) && !is_dir($jobsDir)) {
        throw new RuntimeException('Unable to create the AI job queue directory.');
    }

    $jobQueueFile = $jobsDir . '/job_' . $projectId . '_' . $jobId . '.json';
    if (file_put_contents($jobQueueFile, json_encode([
        'type' => 'separate',
        'job_id' => $jobId,
        'project_id' => $projectId,
        'user_id' => $userId,
        'input_file' => $audioPath,
        'output_dir' => $outputDir,
        'status_file' => $statusFile
    ])) === false) {
        throw new RuntimeException('Unable to add the project to the AI job queue.');
    }

    app_log("Queued AI stem extraction for Project $projectId (Job $jobId)", 'INFO');

    jsonResponse([
        'success' => true,
        'message' => 'Extraction queued. The AI worker must be running to process it.',
        'job_id' => $jobId,
        'project_id' => $projectId,
        'status' => 'queued'
    ]);

} catch (Exception $e) {
    if ($jobId !== null) {
        try {
            $db->prepare("UPDATE processing_jobs SET status = 'failed', error_message = ?, completed_at = NOW() WHERE id = ?")
                ->execute([$e->getMessage(), $jobId]);
            $db->prepare("UPDATE projects SET status = 'uploaded' WHERE id = ? AND status = 'processing'")
                ->execute([$projectId]);
        } catch (Exception $recoveryError) {
            app_log("Failed to restore project $projectId after queue error: " . $recoveryError->getMessage(), 'ERROR');
        }
    }
    if ($statusFile !== null) {
        file_put_contents($statusFile, json_encode([
            'status' => 'failed',
            'project_id' => $projectId,
            'error' => $e->getMessage()
        ]));
    }
    app_log("Processing start error: " . $e->getMessage(), 'ERROR');
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
