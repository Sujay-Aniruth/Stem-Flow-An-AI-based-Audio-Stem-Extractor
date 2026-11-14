/**
 * player.js - StemFlow AI Synchronized Multitrack Web Audio Engine
 */

class MultitrackPlayer {
    constructor() {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        this.ctx = new AudioCtx();

        this.masterGainNode = this.ctx.createGain();
        this.masterGainNode.connect(this.ctx.destination);

        this.masterVolume = 100.0;
        this.currentTime = 0.0;
        this.duration = 0.0;
        this.isPlaying = false;
        this.playbackStartTime = 0.0;
        this.startOffset = 0.0;

        // Tracks registry
        this.tracks = {};
        this.stemTypes = ['vocals', 'drums', 'bass', 'other'];

        // Animation frame for timeline syncing
        this.animFrameId = null;

        // Event callbacks
        this.onTimeUpdate = null;
        this.onPlayStateChange = null;
        this.onLoadingProgress = null;
    }

    async init() {
        if (this.ctx.state === 'suspended') {
            await this.ctx.resume();
        }
    }

    addTrack(name, streamUrl, wavesurferInstance = null) {
        const gainNode = this.ctx.createGain();
        const pitchShifter = new GranularPitchShifter(this.ctx);

        // Connect: PitchShifter -> TrackGain -> MasterGain
        pitchShifter.connect(gainNode);
        gainNode.connect(this.masterGainNode);

        this.tracks[name] = {
            name: name,
            url: streamUrl,
            audioBuffer: null,
            sourceNode: null,
            gainNode: gainNode,
            pitchShifter: pitchShifter,
            volume: 100.0,
            pitch: 0,
            isMuted: false,
            isSolo: false,
            isLoading: true,
            isLoaded: false,
            wavesurfer: wavesurferInstance
        };
    }

    async loadTrackAudio(name) {
        const track = this.tracks[name];
        if (!track) return;

        try {
            const response = await fetch(track.url);
            if (!response.ok) {
                throw new Error(`Failed to fetch audio for ${name}: HTTP ${response.status}`);
            }
            const arrayBuffer = await response.arrayBuffer();
            const decodedBuffer = await this.ctx.decodeAudioData(arrayBuffer);

            track.audioBuffer = decodedBuffer;
            track.isLoaded = true;
            track.isLoading = false;

            if (decodedBuffer.duration > this.duration) {
                this.duration = decodedBuffer.duration;
            }

            console.log(`[Player] Loaded track ${name}: ${decodedBuffer.duration.toFixed(2)}s, ${decodedBuffer.sampleRate}Hz`);
        } catch (err) {
            console.error(`[Player] Error loading audio for track ${name}:`, err);
            track.isLoading = false;
        }
    }

    async loadAllTracks(onProgress) {
        const trackNames = Object.keys(this.tracks);
        let loadedCount = 0;

        await Promise.all(trackNames.map(async (name) => {
            await this.loadTrackAudio(name);
            loadedCount++;
            if (onProgress) {
                onProgress(loadedCount, trackNames.length);
            }
        }));

        this.updateAllGains();
    }

    play() {
        if (this.isPlaying) return;
        this.init();

        const offset = Math.min(this.currentTime, this.duration);
        this.startOffset = offset;
        this.playbackStartTime = this.ctx.currentTime;

        // Schedule all loaded tracks at the identical audioContext clock time
        for (const [name, track] of Object.entries(this.tracks)) {
            if (!track.audioBuffer) continue;

            const source = this.ctx.createBufferSource();
            source.buffer = track.audioBuffer;

            // Connect Source -> PitchShifter
            source.connect(track.pitchShifter.input);
            source.start(this.playbackStartTime, offset);
            track.sourceNode = source;
        }

        this.isPlaying = true;
        this._startTimeUpdateLoop();

        if (this.onPlayStateChange) {
            this.onPlayStateChange(true);
        }
    }

    pause() {
        if (!this.isPlaying) return;

        this.currentTime = this._calculateCurrentTime();
        this._stopSourceNodes();

        this.isPlaying = false;
        if (this.animFrameId) {
            cancelAnimationFrame(this.animFrameId);
            this.animFrameId = null;
        }

        if (this.onPlayStateChange) {
            this.onPlayStateChange(false);
        }
    }

    stop() {
        this.pause();
        this.seek(0);
    }

    togglePlay() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }

    seek(timeSeconds) {
        timeSeconds = Math.max(0, Math.min(this.duration, timeSeconds));
        const wasPlaying = this.isPlaying;

        if (wasPlaying) {
            this.pause();
        }

        this.currentTime = timeSeconds;
        this.startOffset = timeSeconds;

        // Sync WaveSurfers
        for (const track of Object.values(this.tracks)) {
            if (track.wavesurfer && this.duration > 0) {
                const progress = timeSeconds / this.duration;
                track.wavesurfer.seekTo(Math.max(0, Math.min(1, progress)));
            }
        }

        if (this.onTimeUpdate) {
            this.onTimeUpdate(this.currentTime, this.duration);
        }

        if (wasPlaying) {
            this.play();
        }
    }

    setMasterVolume(volumePercent) {
        this.masterVolume = Math.max(0, Math.min(200, volumePercent));
        const gainVal = (this.masterVolume / 100.0);
        this.masterGainNode.gain.setTargetAtTime(gainVal, this.ctx.currentTime, 0.02);
    }

    setTrackVolume(name, volumePercent) {
        const track = this.tracks[name];
        if (!track) return;
        track.volume = Math.max(0, Math.min(200, volumePercent));
        this.updateAllGains();
    }

    setTrackPitch(name, semitones) {
        const track = this.tracks[name];
        if (!track) return;
        track.pitch = Math.max(-12, Math.min(12, semitones));
        track.pitchShifter.setPitchSemitones(track.pitch);
    }

    setTrackMute(name, isMuted) {
        const track = this.tracks[name];
        if (!track) return;
        track.isMuted = !!isMuted;
        this.updateAllGains();
    }

    setTrackSolo(name, isSolo) {
        const track = this.tracks[name];
        if (!track) return;
        track.isSolo = !!isSolo;
        this.updateAllGains();
    }

    clearAllSolos() {
        for (const track of Object.values(this.tracks)) {
            track.isSolo = false;
        }
        this.updateAllGains();
    }

    updateAllGains() {
        // Check if ANY stem track is soloed
        const anySolo = Object.values(this.tracks).some(t => t.isSolo);

        for (const track of Object.values(this.tracks)) {
            let effectiveGain = 0.0;

            if (anySolo) {
                // When solo is active, ONLY soloed tracks play (unless muted)
                if (track.isSolo && !track.isMuted) {
                    effectiveGain = track.volume / 100.0;
                } else {
                    effectiveGain = 0.0;
                }
            } else {
                // Normal mode: plays if not muted
                if (!track.isMuted) {
                    effectiveGain = track.volume / 100.0;
                } else {
                    effectiveGain = 0.0;
                }
            }

            // Smooth gain ramp to prevent clicks
            track.gainNode.gain.setTargetAtTime(effectiveGain, this.ctx.currentTime, 0.02);
        }
    }

    _calculateCurrentTime() {
        if (!this.isPlaying) return this.currentTime;
        const elapsed = this.ctx.currentTime - this.playbackStartTime;
        const current = this.startOffset + elapsed;
        if (current >= this.duration && this.duration > 0) {
            this.stop();
            return 0;
        }
        return current;
    }

    _stopSourceNodes() {
        for (const track of Object.values(this.tracks)) {
            if (track.sourceNode) {
                try {
                    track.sourceNode.stop();
                    track.sourceNode.disconnect();
                } catch (e) {
                    // Ignore already stopped nodes
                }
                track.sourceNode = null;
            }
        }
    }

    _startTimeUpdateLoop() {
        const loop = () => {
            if (!this.isPlaying) return;

            this.currentTime = this._calculateCurrentTime();

            if (this.onTimeUpdate) {
                this.onTimeUpdate(this.currentTime, this.duration);
            }

            // Sync visual WaveSurfer playheads
            if (this.duration > 0) {
                const progress = this.currentTime / this.duration;
                for (const track of Object.values(this.tracks)) {
                    if (track.wavesurfer) {
                        track.wavesurfer.seekTo(Math.max(0, Math.min(1, progress)));
                    }
                }
            }

            this.animFrameId = requestAnimationFrame(loop);
        };
        this.animFrameId = requestAnimationFrame(loop);
    }

    destroy() {
        this.stop();
        for (const track of Object.values(this.tracks)) {
            if (track.wavesurfer) {
                try { track.wavesurfer.destroy(); } catch (e) {}
            }
        }
        try { this.ctx.close(); } catch (e) {}
    }
}

window.MultitrackPlayer = MultitrackPlayer;
