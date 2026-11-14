<?php
// upload.php - Audio Upload and Demucs Extraction

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle = 'Upload Audio — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <header class="top-navbar">
        <div>
            <h5 class="mb-0 fw-bold">Upload & Separate Audio</h5>
            <span class="text-muted small">Upload an audio song to extract Vocals, Drums, Bass, and Other Instruments</span>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </header>

    <div class="content-body">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div id="uploadAlert" style="display: none;"></div>

                <div class="daw-card p-4">
                    <form id="uploadForm">
                        <!-- Drag & Drop Zone -->
                        <div id="dropZone" class="text-center p-5 mb-4" style="border: 2px dashed var(--border-color); border-radius: 12px; background: rgba(20, 20, 28, 0.5); cursor: pointer; transition: all 0.2s;">
                            <input type="file" id="audioFileInput" name="audio_file" accept=".mp3,.wav,.flac,.m4a,.ogg,audio/*" style="display: none;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(139, 92, 246, 0.15); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 16px;">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h5 class="fw-bold mb-1">Drag and drop audio file here</h5>
                            <p class="text-secondary small mb-3">or <span style="color: var(--secondary); font-weight: 600;">browse from your computer</span></p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap text-muted" style="font-size: 0.75rem;">
                                <span class="badge bg-dark border border-secondary border-opacity-25">MP3</span>
                                <span class="badge bg-dark border border-secondary border-opacity-25">WAV</span>
                                <span class="badge bg-dark border border-secondary border-opacity-25">FLAC</span>
                                <span class="badge bg-dark border border-secondary border-opacity-25">M4A</span>
                                <span class="badge bg-dark border border-secondary border-opacity-25">OGG</span>
                                <span class="badge bg-dark border border-secondary border-opacity-25">Up to 100MB</span>
                            </div>
                        </div>

                        <!-- Project Title Input -->
                        <div class="mb-4">
                            <label for="projectTitle" class="form-label text-secondary small fw-bold">Project Title</label>
                            <input type="text" class="form-control" id="projectTitle" name="title" placeholder="My Track Title (auto-filled from file name)">
                        </div>

                        <!-- Audio Preview Section (revealed after file selection) -->
                        <div id="filePreviewSection" class="p-3 mb-4 rounded" style="display: none; background: #14141C; border: 1px solid var(--border-color);">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2 text-truncate">
                                    <i class="fas fa-file-audio text-info"></i>
                                    <span id="previewFilename" class="fw-bold small text-truncate"></span>
                                </div>
                                <span id="previewFilesize" class="badge bg-secondary"></span>
                            </div>
                            <audio id="previewAudioPlayer" controls class="w-100 mt-2" style="height: 36px;"></audio>
                        </div>

                        <!-- Upload Progress Section -->
                        <div id="uploadProgressSection" class="mb-4" style="display: none;">
                            <div class="d-flex justify-content-between small text-secondary mb-1">
                                <span id="uploadProgressText">Uploading audio...</span>
                            </div>
                            <div class="progress" style="height: 8px; background-color: #272738;">
                                <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
                            </div>
                        </div>

                        <!-- Demucs AI Extraction In-Progress Section -->
                        <div id="extractionStatusSection" class="text-center p-4 mb-4 rounded" style="display: none; background: #12121A; border: 1px solid var(--border-color);">
                            <div id="extractionStatusText">
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <h5>Demucs AI is separating stems...</h5>
                                <p class="text-muted small">Loading pretrained model and generating Vocals, Drums, Bass, and Other Instruments.</p>
                            </div>
                            <a id="btnOpenWorkspace" href="#" class="btn btn-primary mt-2" style="display: none;">
                                <i class="fas fa-sliders-h me-1"></i> Open in Audio Workspace
                            </a>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php" class="btn btn-secondary px-3">Cancel</a>
                            <button type="submit" id="btnSubmitUpload" class="btn btn-primary px-4" disabled>
                                <i class="fas fa-magic me-1"></i> Start Demucs Extraction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/upload.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
