"""
pitch_processing.py - Duration-preserving pitch shifting and volume adjustment
"""
import numpy as np
import librosa
import soundfile as sf

def shift_pitch(audio_data: np.ndarray, sample_rate: int, semitones: int) -> np.ndarray:
    """
    Pitch shift audio by semitones (-12 to +12) while strictly preserving duration.
    Uses Librosa's phase vocoder time-stretch and resample implementation.
    """
    if semitones == 0:
        return audio_data

    target_length = len(audio_data)

    if audio_data.ndim == 1:
        # Mono
        shifted = librosa.effects.pitch_shift(
            audio_data.astype(np.float32),
            sr=sample_rate,
            n_steps=semitones
        )
        # Pad or trim to ensure exact original sample count
        if len(shifted) < target_length:
            shifted = np.pad(shifted, (0, target_length - len(shifted)))
        else:
            shifted = shifted[:target_length]
        return shifted
    else:
        # Multi-channel (e.g. Stereo: [samples, channels])
        channels = audio_data.shape[1]
        shifted_channels = []
        for ch in range(channels):
            ch_data = audio_data[:, ch].astype(np.float32)
            shifted_ch = librosa.effects.pitch_shift(
                ch_data,
                sr=sample_rate,
                n_steps=semitones
            )
            if len(shifted_ch) < target_length:
                shifted_ch = np.pad(shifted_ch, (0, target_length - len(shifted_ch)))
            else:
                shifted_ch = shifted_ch[:target_length]
            shifted_channels.append(shifted_ch)

        return np.column_stack(shifted_channels)

def apply_gain(audio_data: np.ndarray, volume_percent: float) -> np.ndarray:
    """
    Apply gain multiplier based on volume percentage (0% to 200%).
    Clips audio to [-1.0, 1.0] to prevent hard clipping.
    """
    multiplier = max(0.0, volume_percent / 100.0)
    adjusted = audio_data * multiplier
    return np.clip(adjusted, -1.0, 1.0)
