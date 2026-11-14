/**
 * workspace.js - UI Controller for StemFlow Audio Workspace
 */

document.addEventListener('DOMContentLoaded', () => {
    const config = window.STEMFLOW_WORKSPACE_CONFIG;
    if (!config || !config.projectId) {
        console.error('Workspace config missing');
        return;
    }

    const player = new MultitrackPlayer();
    window.player = player;

    let autoSaveTimeout = null;
    const saveStatusEl = document.getElementById('saveStatus');

    function formatTime(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    // 1. Initialize synchronized stem tracks and waveform canvases
    const stemColors = {
        vocals: '#A855F7',
        drums: '#F97316',
        bass: '#06B6D4',
        other: '#10B981'
    };
    const waveformViews = {};

    function initWaveform(containerId, color) {
        const container = document.getElementById(containerId);
        if (!container) return null;

        container.replaceChildren();
        const baseCanvas = document.createElement('canvas');
        const progressCanvas = document.createElement('canvas');
        baseCanvas.className = 'waveform-canvas-layer';
        baseCanvas.setAttribute('aria-hidden', 'true');
        progressCanvas.className = 'waveform-canvas-layer waveform-progress';
        progressCanvas.setAttribute('aria-hidden', 'true');
        container.append(baseCanvas, progressCanvas);

        let peaks = null;
        const draw = () => {
            if (!peaks) return;

            const width = container.clientWidth;
            const height = container.clientHeight;
            if (!width || !height) return;

            const pixelRatio = window.devicePixelRatio || 1;
            for (const canvas of [baseCanvas, progressCanvas]) {
                canvas.width = Math.round(width * pixelRatio);
                canvas.height = Math.round(height * pixelRatio);
                const context = canvas.getContext('2d');
                context.scale(pixelRatio, pixelRatio);
                context.clearRect(0, 0, width, height);
                context.fillStyle = canvas === baseCanvas ? 'rgba(148, 163, 184, 0.38)' : color;

                const barWidth = 2;
                const gap = 2;
                const barCount = Math.max(1, Math.floor(width / (barWidth + gap)));
                const centerY = height / 2;
                const peakStep = peaks.length / barCount;

                for (let i = 0; i < barCount; i++) {
                    const peak = peaks[Math.min(peaks.length - 1, Math.floor(i * peakStep))];
                    const barHeight = Math.max(2, peak * height * 0.9);
                    context.fillRect(
                        i * (barWidth + gap),
                        centerY - barHeight / 2,
                        barWidth,
                        barHeight
                    );
                }
            }
        };

        const seekFromPointer = (event) => {
            if (player.duration <= 0) return;
            const bounds = container.getBoundingClientRect();
            const progress = Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width));
            player.seek(progress * player.duration);
        };
        container.addEventListener('pointerdown', (event) => {
            container.setPointerCapture(event.pointerId);
            seekFromPointer(event);
        });
        container.addEventListener('pointermove', (event) => {
            if (event.buttons !== 0) seekFromPointer(event);
        });

        const resizeObserver = new ResizeObserver(draw);
        resizeObserver.observe(container);

        return {
            setAudioBuffer(audioBuffer) {
                if (!audioBuffer) return;
                const channelData = Array.from(
                    { length: audioBuffer.numberOfChannels },
                    (_, channel) => audioBuffer.getChannelData(channel)
                );
                const barCount = Math.max(1, Math.ceil(container.clientWidth / 2));
                const samplesPerBar = Math.ceil(audioBuffer.length / barCount);
                peaks = new Float32Array(barCount);
                let maxPeak = 0;

                for (let bar = 0; bar < barCount; bar++) {
                    const start = bar * samplesPerBar;
                    const end = Math.min(start + samplesPerBar, audioBuffer.length);
                    let peak = 0;
                    for (const samples of channelData) {
                        for (let sample = start; sample < end; sample++) {
                            peak = Math.max(peak, Math.abs(samples[sample]));
                        }
                    }
                    peaks[bar] = peak;
                    maxPeak = Math.max(maxPeak, peak);
                }

                if (maxPeak > 0) {
                    for (let bar = 0; bar < peaks.length; bar++) {
                        peaks[bar] /= maxPeak;
                    }
                }
                draw();
            },
            setProgress(progress) {
                progressCanvas.style.clipPath = `inset(0 ${100 - Math.max(0, Math.min(1, progress)) * 100}% 0 0)`;
            }
        };
    }

    // Add 4 Stems
    const stemTypes = ['vocals', 'drums', 'bass', 'other'];
    stemTypes.forEach(type => {
        const url = config.urls.stemStream(type);
        const containerId = `waveform_${type}`;
        waveformViews[type] = initWaveform(containerId, stemColors[type]);
        player.addTrack(type, url);
    });

    // 2. Transport UI elements
    const btnPlay = document.getElementById('btnPlay');
    const btnStop = document.getElementById('btnStop');
    const timecodeCurrent = document.getElementById('timecodeCurrent');
    const timecodeTotal = document.getElementById('timecodeTotal');
    const timelineScrubber = document.getElementById('timelineScrubber');
    const masterVolumeSlider = document.getElementById('masterVolume');
    const masterVolumeVal = document.getElementById('masterVolumeVal');
    const btnClearSolo = document.getElementById('btnClearSolo');

    // Load initial saved settings
    if (config.settings) {
        const s = config.settings;
        if (s.master_volume !== undefined) {
            player.setMasterVolume(parseFloat(s.master_volume));
            masterVolumeSlider.value = s.master_volume;
            masterVolumeVal.textContent = `${Math.round(s.master_volume)}%`;
        }

        stemTypes.forEach(type => {
            const vol = parseFloat(s[`${type}_volume`] ?? 100);
            const pitch = parseInt(s[`${type}_pitch`] ?? 0);
            const stemMeta = (s.stems && s.stems[type]) ? s.stems[type] : {};
            const isMuted = !!stemMeta.is_muted;
            const isSolo = !!stemMeta.is_solo;

            player.setTrackVolume(type, vol);
            player.setTrackPitch(type, pitch);
            player.setTrackMute(type, isMuted);
            player.setTrackSolo(type, isSolo);

            // Update UI widgets
            const volSlider = document.getElementById(`vol_${type}`);
            const volLabel = document.getElementById(`volVal_${type}`);
            if (volSlider) volSlider.value = vol;
            if (volLabel) volLabel.textContent = `${Math.round(vol)}%`;

            const pitchSlider = document.getElementById(`pitch_${type}`);
            const pitchLabel = document.getElementById(`pitchVal_${type}`);
            if (pitchSlider) pitchSlider.value = pitch;
            if (pitchLabel) pitchLabel.textContent = `${pitch >= 0 ? '+' : ''}${pitch} st`;

            const btnM = document.getElementById(`mute_${type}`);
            const btnS = document.getElementById(`solo_${type}`);
            if (btnM && isMuted) btnM.classList.add('active');
            if (btnS && isSolo) btnS.classList.add('active');
        });
    }

    // Load audio buffers into Web Audio API in parallel
    const loadingBanner = document.getElementById('loadingBanner');
    const loadingProgress = document.getElementById('loadingProgress');

    player.loadAllTracks((loaded, total) => {
        const pct = Math.round((loaded / total) * 100);
        if (loadingProgress) loadingProgress.style.width = `${pct}%`;
        if (loaded === total) {
            setTimeout(() => {
                if (loadingBanner) loadingBanner.style.display = 'none';
            }, 400);
            timecodeTotal.textContent = `/ ${formatTime(player.duration)}`;
            timelineScrubber.max = player.duration;
        }
    }).then(() => {
        stemTypes.forEach(type => {
            waveformViews[type]?.setAudioBuffer(player.tracks[type]?.audioBuffer);
        });
    });

    // Player events
    player.onPlayStateChange = (isPlaying) => {
        if (btnPlay) {
            btnPlay.innerHTML = isPlaying ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
        }
    };

    player.onTimeUpdate = (currentTime, duration) => {
        if (timecodeCurrent) timecodeCurrent.textContent = formatTime(currentTime);
        if (timelineScrubber && !timelineScrubber.isDragging) {
            timelineScrubber.value = currentTime;
        }
        stemTypes.forEach(type => {
            waveformViews[type]?.setProgress(duration > 0 ? currentTime / duration : 0);
        });
    };

    // Transport buttons
    btnPlay.addEventListener('click', () => player.togglePlay());
    btnStop.addEventListener('click', () => player.stop());

    timelineScrubber.addEventListener('mousedown', () => { timelineScrubber.isDragging = true; });
    timelineScrubber.addEventListener('input', (e) => {
        const targetTime = parseFloat(e.target.value);
        if (timecodeCurrent) timecodeCurrent.textContent = formatTime(targetTime);
    });
    timelineScrubber.addEventListener('change', (e) => {
        timelineScrubber.isDragging = false;
        player.seek(parseFloat(e.target.value));
    });

    masterVolumeSlider.addEventListener('input', (e) => {
        const val = parseFloat(e.target.value);
        player.setMasterVolume(val);
        masterVolumeVal.textContent = `${Math.round(val)}%`;
        queueAutoSave();
    });

    if (btnClearSolo) {
        btnClearSolo.addEventListener('click', () => {
            player.clearAllSolos();
            document.querySelectorAll('.btn-solo').forEach(btn => btn.classList.remove('active'));
            queueAutoSave();
        });
    }

    // Keyboard Spacebar play/pause
    window.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            player.togglePlay();
        }
    });

    // 3. Track Control Bindings
    stemTypes.forEach(type => {
        const volSlider = document.getElementById(`vol_${type}`);
        const volLabel = document.getElementById(`volVal_${type}`);
        const btnResetVol = document.getElementById(`resetVol_${type}`);

        if (volSlider) {
            volSlider.addEventListener('input', (e) => {
                const val = parseFloat(e.target.value);
                player.setTrackVolume(type, val);
                if (volLabel) volLabel.textContent = `${Math.round(val)}%`;
                queueAutoSave();
            });
        }

        if (btnResetVol) {
            btnResetVol.addEventListener('click', () => {
                if (volSlider) volSlider.value = 100;
                player.setTrackVolume(type, 100);
                if (volLabel) volLabel.textContent = '100%';
                queueAutoSave();
            });
        }

        // Pitch slider and steppers
        const pitchSlider = document.getElementById(`pitch_${type}`);
        const pitchLabel = document.getElementById(`pitchVal_${type}`);
        const btnPitchDec = document.getElementById(`pitchDec_${type}`);
        const btnPitchInc = document.getElementById(`pitchInc_${type}`);
        const btnResetPitch = document.getElementById(`resetPitch_${type}`);

        function updatePitch(newPitch) {
            newPitch = Math.max(-12, Math.min(12, newPitch));
            player.setTrackPitch(type, newPitch);
            if (pitchSlider) pitchSlider.value = newPitch;
            if (pitchLabel) pitchLabel.textContent = `${newPitch >= 0 ? '+' : ''}${newPitch} st`;
            queueAutoSave();
        }

        if (pitchSlider) {
            pitchSlider.addEventListener('input', (e) => {
                updatePitch(parseInt(e.target.value));
            });
        }

        if (btnPitchDec) {
            btnPitchDec.addEventListener('click', () => {
                const current = parseInt(pitchSlider ? pitchSlider.value : 0);
                updatePitch(current - 1);
            });
        }

        if (btnPitchInc) {
            btnPitchInc.addEventListener('click', () => {
                const current = parseInt(pitchSlider ? pitchSlider.value : 0);
                updatePitch(current + 1);
            });
        }

        if (btnResetPitch) {
            btnResetPitch.addEventListener('click', () => {
                updatePitch(0);
            });
        }

        // Solo & Mute
        const btnMute = document.getElementById(`mute_${type}`);
        const btnSolo = document.getElementById(`solo_${type}`);

        if (btnMute) {
            btnMute.addEventListener('click', () => {
                const isMuted = !btnMute.classList.contains('active');
                btnMute.classList.toggle('active', isMuted);
                player.setTrackMute(type, isMuted);
                queueAutoSave();
            });
        }

        if (btnSolo) {
            btnSolo.addEventListener('click', () => {
                const isSolo = !btnSolo.classList.contains('active');
                btnSolo.classList.toggle('active', isSolo);
                player.setTrackSolo(type, isSolo);
                queueAutoSave();
            });
        }

        // Reset track button
        const btnResetTrack = document.getElementById(`resetTrack_${type}`);
        if (btnResetTrack) {
            btnResetTrack.addEventListener('click', () => {
                if (volSlider) volSlider.value = 100;
                if (volLabel) volLabel.textContent = '100%';
                if (pitchSlider) pitchSlider.value = 0;
                if (pitchLabel) pitchLabel.textContent = '0 st';
                if (btnMute) btnMute.classList.remove('active');
                if (btnSolo) btnSolo.classList.remove('active');

                player.setTrackVolume(type, 100);
                player.setTrackPitch(type, 0);
                player.setTrackMute(type, false);
                player.setTrackSolo(type, false);

                fetch(`${config.urls.settingsReset}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: config.projectId, stem_type: type })
                });
                triggerSaveBadge('Track reset');
            });
        }
    });

    // 4. Reset All Adjustments button
    const btnResetAll = document.getElementById('btnResetAll');
    if (btnResetAll) {
        btnResetAll.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to reset all audio adjustments for this project?')) return;
            try {
                const res = await fetch(config.urls.settingsReset, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: config.projectId })
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                }
            } catch (err) {
                console.error(err);
            }
        });
    }

    // 5. Debounced Settings Auto-Save
    function queueAutoSave() {
        if (saveStatusEl) {
            saveStatusEl.className = 'save-status saving';
            saveStatusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving changes...';
        }

        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(saveSettingsToServer, 750);
    }

    async function saveSettingsToServer() {
        const payload = {
            project_id: config.projectId,
            master_volume: player.masterVolume,
            vocals_volume: player.tracks.vocals ? player.tracks.vocals.volume : 100,
            drums_volume: player.tracks.drums ? player.tracks.drums.volume : 100,
            bass_volume: player.tracks.bass ? player.tracks.bass.volume : 100,
            other_volume: player.tracks.other ? player.tracks.other.volume : 100,
            vocals_pitch: player.tracks.vocals ? player.tracks.vocals.pitch : 0,
            drums_pitch: player.tracks.drums ? player.tracks.drums.pitch : 0,
            bass_pitch: player.tracks.bass ? player.tracks.bass.pitch : 0,
            other_pitch: player.tracks.other ? player.tracks.other.pitch : 0,
            stems: {}
        };

        stemTypes.forEach(t => {
            if (player.tracks[t]) {
                payload.stems[t] = {
                    is_muted: player.tracks[t].isMuted,
                    is_solo: player.tracks[t].isSolo
                };
            }
        });

        try {
            const res = await fetch(config.urls.settingsSave, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                triggerSaveBadge('All adjustments saved');
            }
        } catch (e) {
            console.error('Settings save error:', e);
            if (saveStatusEl) {
                saveStatusEl.className = 'save-status text-danger';
                saveStatusEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Save failed';
            }
        }
    }

    function triggerSaveBadge(msg) {
        if (saveStatusEl) {
            saveStatusEl.className = 'save-status saved';
            saveStatusEl.innerHTML = `<i class="fas fa-check-circle"></i> ${msg}`;
        }
    }

    // 6. Export Adjusted Stems
    const btnOpenExportModal = document.getElementById('btnOpenExportModal');
    const btnStartExport = document.getElementById('btnStartExport');
    const exportStatusDiv = document.getElementById('exportStatusDiv');
    const exportResultDiv = document.getElementById('exportResultDiv');
    const btnDownloadExportZip = document.getElementById('btnDownloadExportZip');
    const exportModalEl = document.getElementById('exportModal');
    const exportModal = exportModalEl ? new bootstrap.Modal(exportModalEl) : null;

    if (btnOpenExportModal && exportModal) {
        btnOpenExportModal.addEventListener('click', () => {
            if (exportStatusDiv) exportStatusDiv.style.display = 'none';
            if (exportResultDiv) exportResultDiv.style.display = 'none';
            if (btnStartExport) btnStartExport.style.display = 'inline-block';
            exportModal.show();
        });
    }

    if (btnStartExport) {
        btnStartExport.addEventListener('click', async () => {
            btnStartExport.disabled = true;
            if (exportStatusDiv) {
                exportStatusDiv.style.display = 'block';
                exportStatusDiv.innerHTML = '<div class="spinner-border text-info mb-2" role="status"></div><p>Applying pitch shifts and volume gains with Python AI engine...</p>';
            }

            try {
                const res = await fetch(config.urls.exportCreate, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ project_id: config.projectId })
                });
                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to start export');
                }

                const exportId = data.export_id;
                pollExportStatus(exportId);

            } catch (err) {
                if (exportStatusDiv) {
                    exportStatusDiv.innerHTML = `<div class="alert alert-danger">${err.message}</div>`;
                }
                btnStartExport.disabled = false;
            }
        });
    }

    function pollExportStatus(exportId) {
        const interval = setInterval(async () => {
            try {
                const res = await fetch(`${config.urls.exportStatus}?export_id=${exportId}`);
                const data = await res.json();

                if (data.success && data.export) {
                    if (data.export.status === 'completed') {
                        clearInterval(interval);
                        if (exportStatusDiv) exportStatusDiv.style.display = 'none';
                        if (exportResultDiv) {
                            exportResultDiv.style.display = 'block';
                            if (btnDownloadExportZip) {
                                btnDownloadExportZip.href = data.export.download_url;
                            }
                        }
                        if (btnStartExport) btnStartExport.style.display = 'none';
                    } else if (data.export.status === 'failed') {
                        clearInterval(interval);
                        if (exportStatusDiv) {
                            exportStatusDiv.innerHTML = '<div class="alert alert-danger">Audio export failed. Please check logs.</div>';
                        }
                        if (btnStartExport) btnStartExport.disabled = false;
                    }
                }
            } catch (e) {
                console.error('Polling error:', e);
            }
        }, 1500);
    }
});
