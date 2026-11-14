/**
 * pitch-shifter.js - Granular Pitch Shifter for Web Audio API
 * 
 * Implements duration-preserving pitch shifting using overlapping Hann-windowed
 * audio grains. Shifts pitch by semitones (-12 to +12) without altering playback
 * speed or audio duration.
 */

class GranularPitchShifter {
    constructor(audioContext, options = {}) {
        this.ctx = audioContext;
        this.bufferSize = options.bufferSize || 4096;
        this.grainSize = options.grainSize || 8192; // ~186ms at 44.1kHz
        this.pitchRatio = 1.0;
        this.semitones = 0;

        // Internal grain state
        this.grainWindow = new Float32Array(this.grainSize);
        for (let i = 0; i < this.grainSize; i++) {
            // Periodic Hann Window
            this.grainWindow[i] = 0.5 * (1.0 - Math.cos((2.0 * Math.PI * i) / (this.grainSize - 1)));
        }

        this.delayBufferL = new Float32Array(this.bufferSize * 4);
        this.delayBufferR = new Float32Array(this.bufferSize * 4);
        this.writeIndex = 0;
        this.halfGrain = Math.floor(this.grainSize / 2);
        this.baseDelay = this.grainSize + Math.floor(this.bufferSize / 2);
        this.grains = [
            { age: 0, start: -this.baseDelay },
            { age: this.halfGrain, start: -this.baseDelay - this.halfGrain }
        ];

        // Create ScriptProcessor audio node (stereo)
        this.node = this.ctx.createScriptProcessor(this.bufferSize, 2, 2);
        this.node.onaudioprocess = (e) => this._process(e);

        this.input = this.node;
        this.output = this.node;
    }

    setPitchSemitones(semitones) {
        this.semitones = Math.max(-12, Math.min(12, semitones));
        this.pitchRatio = Math.pow(2.0, this.semitones / 12.0);
    }

    connect(destination) {
        this.output.connect(destination);
    }

    disconnect() {
        this.output.disconnect();
    }

    _process(event) {
        const inputL = event.inputBuffer.getChannelData(0);
        const inputR = event.inputBuffer.getChannelData(1);
        const outputL = event.outputBuffer.getChannelData(0);
        const outputR = event.outputBuffer.getChannelData(1);
        const numSamples = inputL.length;

        const delayLen = this.delayBufferL.length;
        const bypass = Math.abs(this.pitchRatio - 1.0) < 0.001;

        for (let i = 0; i < numSamples; i++) {
            this.delayBufferL[this.writeIndex] = inputL[i];
            this.delayBufferR[this.writeIndex] = inputR[i];

            if (bypass) {
                outputL[i] = inputL[i];
                outputR[i] = inputR[i];
            } else {
                let sampleL = 0;
                let sampleR = 0;

                for (const grain of this.grains) {
                    const phase = grain.age / this.grainSize;
                    const window = 0.5 - 0.5 * Math.cos(2.0 * Math.PI * phase);
                    const sourcePosition = grain.start + grain.age * this.pitchRatio;
                    sampleL += this._read(this.delayBufferL, sourcePosition) * window;
                    sampleR += this._read(this.delayBufferR, sourcePosition) * window;
                }

                outputL[i] = sampleL;
                outputR[i] = sampleR;
            }

            for (const grain of this.grains) {
                grain.age++;
                if (grain.age >= this.grainSize) {
                    grain.age = 0;
                    grain.start = this.writeIndex - this.baseDelay;
                }
            }

            this.writeIndex = (this.writeIndex + 1) % delayLen;
        }
    }

    _read(buffer, position) {
        const length = buffer.length;
        const wrappedPosition = (position % length + length) % length;
        const index = Math.floor(wrappedPosition);
        const nextIndex = (index + 1) % length;
        const fraction = wrappedPosition - index;
        return buffer[index] + (buffer[nextIndex] - buffer[index]) * fraction;
    }
}

window.GranularPitchShifter = GranularPitchShifter;
