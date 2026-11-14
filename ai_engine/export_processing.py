"""
export_processing.py - Export adjusted stems with pitch & volume adjustments
"""
import os
import sys
import json
import zipfile
from pathlib import Path
import numpy as np
import soundfile as sf

import config
from pitch_processing import shift_pitch, apply_gain

def export_adjusted_stems(project_id: int, stems_dir: str, output_dir: str, settings: dict) -> dict:
    stems_path = Path(stems_dir)
    out_path = Path(output_dir)
    out_path.mkdir(parents=True, exist_ok=True)

    # Determine solo state across all stems
    stem_settings = settings.get("stems", {})
    any_solo = any(stem_settings.get(s, {}).get("is_solo", False) for s in config.STEM_TYPES)

    master_volume = float(settings.get("master_volume", 100.0))
    exported_stems = {}
    mix_audio = None
    common_sr = config.DEFAULT_SAMPLE_RATE

    for stem_name in config.STEM_TYPES:
        stem_file = stems_path / f"{stem_name}.wav"
        if not stem_file.exists():
            print(f"Warning: Stem file {stem_file.name} not found. Skipping.", file=sys.stderr)
            continue

        audio, sr = sf.read(str(stem_file), dtype="float32")
        common_sr = sr

        if audio.ndim == 1:
            audio = np.column_stack([audio, audio])

        # Check mute / solo state
        stem_meta = stem_settings.get(stem_name, {})
        is_muted = stem_meta.get("is_muted", False)
        is_solo = stem_meta.get("is_solo", False)

        should_mute = is_muted or (any_solo and not is_solo)

        pitch_semitones = int(settings.get(f"{stem_name}_pitch", 0))
        volume_pct = float(settings.get(f"{stem_name}_volume", 100.0))

        if should_mute:
            # Produce silence for mix, but preserve stem for individual export
            processed_audio = np.zeros_like(audio)
            indiv_audio = shift_pitch(audio, sr, pitch_semitones)
            indiv_audio = apply_gain(indiv_audio, volume_pct)
        else:
            processed_audio = shift_pitch(audio, sr, pitch_semitones)
            processed_audio = apply_gain(processed_audio, volume_pct)
            indiv_audio = processed_audio

        # Save individual adjusted stem
        out_stem_file = out_path / f"adjusted_{stem_name}.wav"
        sf.write(str(out_stem_file), indiv_audio, sr, subtype="PCM_16")

        exported_stems[stem_name] = {
            "file_name": out_stem_file.name,
            "file_path": str(out_stem_file),
            "file_size": out_stem_file.stat().st_size
        }

        # Accumulate for master mix
        if not should_mute:
            if mix_audio is None:
                mix_audio = np.zeros_like(processed_audio)
            mix_audio += processed_audio

    # Save full adjusted master mix
    if mix_audio is not None:
        mix_audio = apply_gain(mix_audio, master_volume)
        mix_file = out_path / f"adjusted_master_mix_{project_id}.wav"
        sf.write(str(mix_file), mix_audio, common_sr, subtype="PCM_16")
    else:
        mix_file = None

    # Package into a ZIP archive
    zip_filename = f"StemFlow_Adjusted_Project_{project_id}.zip"
    zip_path = out_path / zip_filename
    with zipfile.ZipFile(str(zip_path), "w", zipfile.ZIP_DEFLATED) as zf:
        for stem_name, info in exported_stems.items():
            zf.write(info["file_path"], arcname=f"adjusted_{stem_name}.wav")
        if mix_file and mix_file.exists():
            zf.write(str(mix_file), arcname="adjusted_master_mix.wav")

    return {
        "success": True,
        "zip_path": str(zip_path),
        "zip_filename": zip_filename,
        "zip_size": zip_path.stat().st_size,
        "master_mix_path": str(mix_file) if mix_file else None,
        "stems": exported_stems
    }
