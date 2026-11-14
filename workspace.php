<?php
// workspace.php - StemFlow Audio Workspace (Multitrack DAW)

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int)$_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? $_GET['project_id'] ?? 0);

if (!$projectId) {
    header('Location: dashboard.php');
    exit;
}

$project = getProject($projectId, $userId);
if (!$project) {
    header('Location: dashboard.php');
    exit;
}

$stems = getProjectStems($projectId);
$settings = getOrCreateAudioSettings($projectId, $userId);

$includeWorkspaceCss = true;
$pageTitle = e($project['title']) . ' — StemFlow Studio';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <!-- Top Navbar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="btn btn-secondary btn-sm px-2" title="Dashboard">
                <i class="fas fa-chevron-left"></i>
            </a>
            <div>
                <h5 class="mb-0 fw-bold d-flex align-items-center gap-2">
                    <span id="projectDisplayTitle"><?= e($project['title']) ?></span>
                    <button class="btn btn-sm btn-link text-muted p-0" title="Rename Project" onclick="StemFlow.renameProject(<?= $project['id'] ?>, '<?= addslashes(e($project['title'])) ?>', (t) => document.getElementById('projectDisplayTitle').textContent = t)">
                        <i class="fas fa-pencil-alt small"></i>
                    </button>
                </h5>
                <span class="text-muted small">
                    <?= e($project['original_filename']) ?> • <?= formatDuration((float)$project['audio_duration']) ?> • <?= formatBytes((int)$project['file_size']) ?>
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="api/stems/download-all.php?project_id=<?= $project['id'] ?>" class="btn btn-secondary btn-sm" title="Download original stems in a ZIP archive">
                <i class="fas fa-file-archive me-1"></i> Download All Stems (ZIP)
            </a>
            <button id="btnOpenExportModal" class="btn btn-primary btn-sm">
                <i class="fas fa-file-export me-1"></i> Export Adjusted Stems
            </button>
        </div>
    </header>

    <div class="content-body">
        <?php if ($project['status'] === 'processing' || $project['status'] === 'queued'): ?>
        <!-- Extraction in progress state -->
        <div class="daw-card text-center p-5 my-5">
            <div class="spinner-border text-primary mb-3" style="width: 3.5rem; height: 3.5rem;" role="status"></div>
            <h4 class="fw-bold mb-2">Demucs AI Stem Separation In Progress</h4>
            <p class="text-secondary small mb-4" style="max-width: 500px; margin: 0 auto;">
                Demucs deep neural network is processing your audio file and extracting Vocals, Drums, Bass, and Other Instruments.
                This page will automatically open your studio workspace once extraction completes.
            </p>
            <div class="progress mb-4" style="height: 6px; max-width: 400px; margin: 0 auto; background-color: #272738;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 100%;"></div>
            </div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
        </div>
        <script>
        setInterval(async () => {
            try {
                const res = await fetch('api/processing/status.php?project_id=<?= $projectId ?>');
                const data = await res.json();
                if (data.success && data.status === 'completed') {
                    location.reload();
                }
            } catch (e) {}
        }, 2500);
        </script>

        <?php elseif ($project['status'] === 'failed'): ?>
        <!-- Failed extraction state -->
        <div class="daw-card text-center p-5 my-5">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(239, 68, 68, 0.15); color: #EF4444; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 16px;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h4 class="fw-bold mb-2">Separation Failed</h4>
            <p class="text-secondary small mb-4">
                An error occurred during Demucs AI audio processing. You can retry extracting or upload another track.
            </p>
            <a href="upload.php" class="btn btn-primary">Try New Upload</a>
        </div>

        <?php else: ?>
        <!-- Active Multitrack DAW Workspace -->
        <div class="workspace-container">
            <!-- Loading Audio Buffers Banner -->
            <div id="loadingBanner" class="p-3 mb-2 rounded" style="background: rgba(34, 211, 238, 0.1); border: 1px solid rgba(34, 211, 238, 0.25);">
                <div class="d-flex align-items-center justify-content-between small text-info mb-1">
                    <span><i class="fas fa-spinner fa-spin me-2"></i> Initializing synchronized multitrack audio engine...</span>
                </div>
                <div class="progress" style="height: 4px; background: #202030;">
                    <div id="loadingProgress" class="progress-bar bg-info" style="width: 0%;"></div>
                </div>
            </div>

            <!-- Master Transport Bar -->
            <div class="transport-bar">
                <div class="transport-controls">
                    <button id="btnStop" class="btn-transport" title="Stop & Rewind to Start">
                        <i class="fas fa-backward-step"></i>
                    </button>
                    <button id="btnPlay" class="btn-transport btn-transport-play" title="Play / Pause (Spacebar)">
                        <i class="fas fa-play"></i>
                    </button>
                </div>

                <!-- Digital Timecode Display -->
                <div class="timecode-display">
                    <span id="timecodeCurrent">00:00</span>
                    <span id="timecodeTotal" class="timecode-total">/ <?= formatDuration((float)$project['audio_duration']) ?></span>
                </div>

                <!-- Timeline Scrubber -->
                <div class="timeline-scrubber-container">
                    <div class="timeline-ruler">
                        <span>0:00</span>
                        <span><?= formatDuration((float)$project['audio_duration'] * 0.25) ?></span>
                        <span><?= formatDuration((float)$project['audio_duration'] * 0.5) ?></span>
                        <span><?= formatDuration((float)$project['audio_duration'] * 0.75) ?></span>
                        <span><?= formatDuration((float)$project['audio_duration']) ?></span>
                    </div>
                    <input type="range" id="timelineScrubber" class="timeline-slider" min="0" max="<?= (float)$project['audio_duration'] ?>" step="0.1" value="0">
                </div>

                <!-- Transport Actions (Master Volume, Clear Solo, Save Status) -->
                <div class="transport-actions">
                    <div class="master-vol-container" title="Master Output Volume">
                        <i class="fas fa-volume-up text-secondary small"></i>
                        <input type="range" id="masterVolume" class="master-vol-slider" min="0" max="200" step="1" value="<?= (float)($settings['master_volume'] ?? 100) ?>">
                        <span id="masterVolumeVal" class="small fw-bold text-white" style="min-width: 38px; font-family: monospace;">100%</span>
                    </div>

                    <button id="btnClearSolo" class="btn btn-secondary btn-sm" title="Clear all track solos">
                        Solo Reset
                    </button>

                    <button id="btnResetAll" class="btn btn-outline-secondary btn-sm" title="Reset all track volume & pitch adjustments">
                        <i class="fas fa-undo me-1"></i> Reset All
                    </button>

                    <div id="saveStatus" class="save-status saved">
                        <i class="fas fa-check-circle"></i> Saved
                    </div>
                </div>
            </div>

            <!-- 4 Demucs AI Stem Tracks -->
            <?php
            $stemConfigs = [
                'vocals' => ['name' => 'Vocals', 'icon' => 'fa-microphone', 'class' => 'track-vocals'],
                'drums'  => ['name' => 'Drums',  'icon' => 'fa-drum',       'class' => 'track-drums'],
                'bass'   => ['name' => 'Bass',   'icon' => 'fa-guitar',     'class' => 'track-bass'],
                'other'  => ['name' => 'Other',  'icon' => 'fa-shapes',     'class' => 'track-other']
            ];

            foreach ($stemConfigs as $type => $info):
                $stemData = $stems[$type] ?? null;
                $vol = (float)($settings["{$type}_volume"] ?? 100);
                $pitch = (int)($settings["{$type}_pitch"] ?? 0);
            ?>
            <div class="track-channel <?= $info['class'] ?>">
                <!-- Track Left Control Panel -->
                <div class="track-panel">
                    <div class="track-info-header">
                        <div class="track-title-wrapper">
                            <div class="track-icon-badge">
                                <i class="fas <?= $info['icon'] ?>"></i>
                            </div>
                            <div>
                                <h6 class="track-name"><?= $info['name'] ?></h6>
                                <span class="text-muted" style="font-size: 0.72rem;">Stem 4-Way</span>
                            </div>
                        </div>

                        <!-- Solo / Mute Buttons -->
                        <div class="track-state-buttons">
                            <button id="solo_<?= $type ?>" class="btn-daw-toggle btn-solo" title="Solo <?= $info['name'] ?>">S</button>
                            <button id="mute_<?= $type ?>" class="btn-daw-toggle btn-mute" title="Mute <?= $info['name'] ?>">M</button>
                        </div>
                    </div>

                    <!-- Volume Slider -->
                    <div class="control-row">
                        <div class="control-label-row">
                            <span><i class="fas fa-volume-up me-1"></i> Volume</span>
                            <div>
                                <span id="volVal_<?= $type ?>" class="control-value"><?= round($vol) ?>%</span>
                                <button id="resetVol_<?= $type ?>" class="btn-reset-param ms-1" title="Reset Volume to 100%"><i class="fas fa-undo"></i></button>
                            </div>
                        </div>
                        <input type="range" id="vol_<?= $type ?>" class="daw-slider" min="0" max="200" step="1" value="<?= $vol ?>">
                    </div>

                    <!-- Pitch Slider & Steppers -->
                    <div class="control-row">
                        <div class="control-label-row">
                            <span><i class="fas fa-wave-square me-1"></i> Pitch</span>
                            <div>
                                <span id="pitchVal_<?= $type ?>" class="control-value"><?= $pitch >= 0 ? "+$pitch" : $pitch ?> st</span>
                                <button id="resetPitch_<?= $type ?>" class="btn-reset-param ms-1" title="Reset Pitch to 0 st"><i class="fas fa-undo"></i></button>
                            </div>
                        </div>
                        <div class="pitch-stepper">
                            <button id="pitchDec_<?= $type ?>" class="btn-pitch-step" title="Semitone Down">-</button>
                            <input type="range" id="pitch_<?= $type ?>" class="daw-slider flex-grow-1" min="-12" max="12" step="1" value="<?= $pitch ?>">
                            <button id="pitchInc_<?= $type ?>" class="btn-pitch-step" title="Semitone Up">+</button>
                        </div>
                    </div>

                    <!-- Track Bottom Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-1" style="font-size: 0.75rem;">
                        <button id="resetTrack_<?= $type ?>" class="btn-reset-param">
                            <i class="fas fa-redo-alt me-1"></i> Reset
                        </button>
                        <a href="api/stems/download.php?project_id=<?= $project['id'] ?>&stem_type=<?= $type ?>" class="text-secondary text-decoration-none" title="Download <?= $info['name'] ?> WAV">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                    </div>
                </div>

                <!-- Track Right Waveform Pane -->
                <div class="track-waveform-pane">
                    <div id="waveform_<?= $type ?>" class="waveform-container"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="exportModalLabel">
                    <i class="fas fa-file-export text-primary me-2"></i> Export Adjusted Stems
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p class="text-secondary small mb-3">
                    This will use the Python AI audio processing engine to render new high-definition WAV audio files with your exact pitch shifts and volume levels applied.
                </p>

                <div id="exportStatusDiv" style="display: none;" class="my-3">
                    <div class="spinner-border text-info mb-2" role="status"></div>
                    <p class="small text-muted">Rendering adjusted stems with phase vocoder pitch shifting...</p>
                </div>

                <div id="exportResultDiv" style="display: none;" class="my-3">
                    <div class="text-success mb-3">
                        <i class="fas fa-check-circle fa-3x"></i>
                        <h5 class="mt-2">Export Complete!</h5>
                        <p class="text-muted small">Your adjusted stems and master mix have been packaged into a ZIP archive.</p>
                    </div>
                    <a id="btnDownloadExportZip" href="#" class="btn btn-primary px-4 py-2">
                        <i class="fas fa-download me-1"></i> Download Exported ZIP
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" id="btnStartExport" class="btn btn-primary btn-sm">
                    <i class="fas fa-magic me-1"></i> Start Render & Export
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Pass project settings to JavaScript -->
<script>
window.STEMFLOW_WORKSPACE_CONFIG = {
    projectId: <?= (int)$project['id'] ?>,
    title: <?= json_encode($project['title']) ?>,
    settings: <?= json_encode($settings) ?>,
    urls: {
        stemStream: (type) => `api/stems/stream.php?project_id=<?= (int)$project['id'] ?>&stem_type=${type}`,
        settingsSave: 'api/settings/save.php',
        settingsReset: 'api/settings/reset.php',
        exportCreate: 'api/export/create.php',
        exportStatus: 'api/export/status.php'
    }
};
</script>
<script src="assets/js/pitch-shifter.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/pitch-shifter.js') ?>"></script>
<script src="assets/js/player.js"></script>
<script src="assets/js/workspace.js?v=<?= (int)filemtime(__DIR__ . '/assets/js/workspace.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
