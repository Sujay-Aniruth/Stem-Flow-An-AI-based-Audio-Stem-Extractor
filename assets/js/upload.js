/**
 * upload.js - Audio Upload and Demucs Processing Controller
 */

document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('audioFileInput');
    const uploadForm = document.getElementById('uploadForm');
    const projectTitleInput = document.getElementById('projectTitle');

    const filePreviewSection = document.getElementById('filePreviewSection');
    const previewFilename = document.getElementById('previewFilename');
    const previewFilesize = document.getElementById('previewFilesize');
    const previewAudioPlayer = document.getElementById('previewAudioPlayer');

    const uploadProgressSection = document.getElementById('uploadProgressSection');
    const uploadProgressBar = document.getElementById('uploadProgressBar');
    const uploadProgressText = document.getElementById('uploadProgressText');

    const extractionStatusSection = document.getElementById('extractionStatusSection');
    const extractionStatusText = document.getElementById('extractionStatusText');
    const btnOpenWorkspace = document.getElementById('btnOpenWorkspace');

    const btnSubmitUpload = document.getElementById('btnSubmitUpload');
    const uploadAlert = document.getElementById('uploadAlert');

    let selectedFile = null;

    if (!dropZone || !fileInput) return;

    function showAlert(msg, type = 'danger') {
        if (!uploadAlert) return;
        uploadAlert.className = `alert alert-${type}`;
        uploadAlert.innerHTML = msg;
        uploadAlert.style.display = 'block';
    }

    function hideAlert() {
        if (uploadAlert) uploadAlert.style.display = 'none';
    }

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Drag and Drop listeners
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });

    dropZone.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });

    function handleFileSelect(file) {
        hideAlert();
        const allowedExts = ['mp3', 'wav', 'flac', 'm4a', 'ogg'];
        const ext = file.name.split('.').pop().toLowerCase();

        if (!allowedExts.includes(ext)) {
            showAlert(`Unsupported file format (.${ext}). Please upload MP3, WAV, FLAC, M4A, or OGG.`);
            return;
        }

        const maxBytes = 100 * 1024 * 1024; // 100 MB
        if (file.size > maxBytes) {
            showAlert(`File exceeds maximum size of 100MB (${formatBytes(file.size)}).`);
            return;
        }

        selectedFile = file;

        // Auto-fill title if empty
        if (projectTitleInput && !projectTitleInput.value.trim()) {
            const rawTitle = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
            projectTitleInput.value = rawTitle;
        }

        // Preview section
        if (previewFilename) previewFilename.textContent = file.name;
        if (previewFilesize) previewFilesize.textContent = formatBytes(file.size);
        if (previewAudioPlayer) {
            previewAudioPlayer.src = URL.createObjectURL(file);
        }
        if (filePreviewSection) filePreviewSection.style.display = 'block';
        if (btnSubmitUpload) btnSubmitUpload.disabled = false;
    }

    // Upload & Extract Form Submit
    if (uploadForm) {
        uploadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!selectedFile) {
                showAlert('Please choose an audio file first.');
                return;
            }

            hideAlert();
            btnSubmitUpload.disabled = true;
            if (uploadProgressSection) uploadProgressSection.style.display = 'block';

            const formData = new FormData();
            formData.append('audio_file', selectedFile);
            formData.append('title', projectTitleInput ? projectTitleInput.value.trim() : '');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/audio/upload.php', true);

            xhr.upload.onprogress = (evt) => {
                if (evt.lengthComputable) {
                    const percent = Math.round((evt.loaded / evt.total) * 100);
                    if (uploadProgressBar) uploadProgressBar.style.width = percent + '%';
                    if (uploadProgressText) uploadProgressText.textContent = `Uploading audio: ${percent}%`;
                }
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success && res.project) {
                            if (uploadProgressText) uploadProgressText.textContent = 'Upload complete! Starting Demucs AI extraction...';
                            startExtraction(res.project.id);
                        } else {
                            showAlert(res.error || 'Upload failed.');
                            btnSubmitUpload.disabled = false;
                        }
                    } catch (err) {
                        showAlert('Invalid server response during upload.');
                        btnSubmitUpload.disabled = false;
                    }
                } else {
                    let errMsg = 'Upload failed.';
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.error) errMsg = res.error;
                    } catch (e) {}
                    showAlert(errMsg);
                    btnSubmitUpload.disabled = false;
                }
            };

            xhr.onerror = () => {
                showAlert('Network error occurred during audio upload.');
                btnSubmitUpload.disabled = false;
            };

            xhr.send(formData);
        });
    }

    // Kick off Demucs separation
    async function startExtraction(projectId) {
        if (uploadProgressSection) uploadProgressSection.style.display = 'none';
        if (extractionStatusSection) extractionStatusSection.style.display = 'block';

        try {
            const res = await fetch('api/processing/start.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: projectId })
            });
            const data = await res.json();
            if (data.success) {
                if (data.status === 'queued' && extractionStatusText) {
                    extractionStatusText.innerHTML = `
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <h5>Waiting for the AI worker...</h5>
                        <p class="text-muted">Run <code>.venv/bin/python3 ai_engine/worker.py</code> from the project folder in Terminal. Keep it running while stems are being extracted.</p>
                    `;
                }
                pollExtractionStatus(projectId);
            } else {
                showAlert(data.error || 'Failed to start AI separation.');
            }
        } catch (e) {
            showAlert('Could not contact extraction service: ' + e.message);
        }
    }

    function pollExtractionStatus(projectId) {
        const interval = setInterval(async () => {
            try {
                const res = await fetch(`api/processing/status.php?project_id=${projectId}`);
                const data = await res.json();

                if (data.success) {
                    if (data.status === 'completed') {
                        clearInterval(interval);
                        if (extractionStatusText) {
                            extractionStatusText.innerHTML = `
                                <div class="text-success mb-3">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                    <h5 class="mt-2">Stems Successfully Extracted!</h5>
                                    <p class="text-muted">4 stems (Vocals, Drums, Bass, Other) generated with Demucs AI.</p>
                                </div>
                            `;
                        }
                        if (btnOpenWorkspace) {
                            btnOpenWorkspace.href = `workspace.php?id=${projectId}`;
                            btnOpenWorkspace.style.display = 'inline-block';
                        }
                    } else if (data.status === 'failed') {
                        clearInterval(interval);
                        showAlert(data.error || 'Demucs separation failed.');
                    } else {
                        if (extractionStatusText) {
                            const queued = data.status === 'queued';
                            extractionStatusText.innerHTML = queued ? `
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <h5>Waiting for the AI worker...</h5>
                                <p class="text-muted">Run <code>.venv/bin/python3 ai_engine/worker.py</code> from the project folder in Terminal and keep it running.</p>
                            ` : `
                                <div class="spinner-border text-primary mb-3" role="status"></div>
                                <h5>Demucs AI is separating stems...</h5>
                                <p class="text-muted">Extracting Vocals, Drums, Bass, and Other Instruments. Processing time depends on audio length and hardware.</p>
                            `;
                        }
                    }
                }
            } catch (err) {
                console.error('Polling extraction status error:', err);
            }
        }, 2000);
    }
});
