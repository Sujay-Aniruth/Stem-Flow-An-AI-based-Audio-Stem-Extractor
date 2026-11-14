"""
separation.py - AI Audio Stem Separation using Demucs
"""
import os
import sys
import time
from pathlib import Path
import numpy as np
import soundfile as sf
import config
import torch
import julius
import demucs.pretrained
import demucs.apply

class DemucsSeparator:
    _model = None
    _model_name = None
    _device = None

    @classmethod
    def get_device(cls):
        if torch.cuda.is_available():
            return torch.device("cuda")
        if hasattr(torch.backends, "mps") and torch.backends.mps.is_available():
            return torch.device("mps")
        return torch.device("cpu")

    @classmethod
    def get_model(cls, model_name: str = config.MODEL_NAME):
        device = cls.get_device()
        if cls._model is None or cls._model_name != model_name or cls._device != device:
            print(f"Loading Demucs pretrained model '{model_name}' on {device}...", file=sys.stderr)
            cls._model = demucs.pretrained.get_model(model_name).to(device)
            cls._model.eval()
            cls._model_name = model_name
            cls._device = device
            print(f"Demucs model loaded successfully on {device}.", file=sys.stderr)
        return cls._model

    @classmethod
    def separate(cls, input_audio_path: str, output_directory: str, progress_callback=None) -> dict:
        input_path = Path(input_audio_path)
        output_dir = Path(output_directory)
        output_dir.mkdir(parents=True, exist_ok=True)

        if not input_path.exists():
            raise FileNotFoundError(f"Audio file not found: {input_audio_path}")

        model = cls.get_model(config.MODEL_NAME)
        target_sr = model.samplerate

        # 1. Read input audio
        print(f"Reading audio: {input_path.name}...", file=sys.stderr)
        audio_data, sr = sf.read(str(input_path), dtype="float32")

        # 2. Channel normalization (ensure stereo: [samples, 2])
        if audio_data.ndim == 1:
            audio_data = np.column_stack([audio_data, audio_data])
        elif audio_data.shape[1] > 2:
            audio_data = audio_data[:, :2]

        # Convert to PyTorch Tensor: [channels, samples]
        tensor_audio = torch.from_numpy(audio_data.T)

        # 3. Resample if necessary
        if sr != target_sr:
            print(f"Resampling from {sr}Hz to {target_sr}Hz...", file=sys.stderr)
            tensor_audio = julius.resample_frac(tensor_audio, sr, target_sr)

        duration = tensor_audio.shape[1] / target_sr

        # 4. Run AI separation
        print(f"Starting Demucs AI separation (duration: {duration:.2f}s)...", file=sys.stderr)
        start_time = time.time()

        with torch.no_grad():
            # Apply Demucs model (shifts=0 for fast inference, split=True for memory safety)
            # Output tensor shape: [batch, sources, channels, samples] -> [sources, channels, samples]
            sources_tensor = demucs.apply.apply_model(
                model,
                tensor_audio.to(cls._device)[None],
                shifts=0,
                split=True,
                overlap=config.DEMUCS_OVERLAP,
                progress=False
            )[0]

        elapsed = time.time() - start_time
        print(f"Demucs AI separation completed in {elapsed:.2f}s.", file=sys.stderr)

        # 5. Save generated stems
        # model.sources is typically: ['drums', 'bass', 'other', 'vocals']
        stem_results = {}
        for source_name, source_audio in zip(model.sources, sources_tensor):
            # source_audio is [channels, samples], transpose to [samples, channels]
            stem_np = source_audio.cpu().numpy().T
            stem_filename = f"{source_name}.wav"
            stem_filepath = output_dir / stem_filename

            # Write standard 16-bit PCM WAV for maximum browser compatibility
            sf.write(str(stem_filepath), stem_np, target_sr, subtype="PCM_16")

            stem_size = stem_filepath.stat().st_size
            stem_results[source_name] = {
                "file_name": stem_filename,
                "file_path": str(stem_filepath),
                "file_size": stem_size,
                "duration": round(duration, 2),
                "sample_rate": target_sr,
                "channels": 2
            }
            print(f"Saved stem: {stem_filename} ({round(stem_size / 1024 / 1024, 2)} MB)", file=sys.stderr)

        return {
            "success": True,
            "duration": round(duration, 2),
            "sample_rate": target_sr,
            "channels": 2,
            "stems": stem_results,
            "elapsed_seconds": round(elapsed, 2)
        }
