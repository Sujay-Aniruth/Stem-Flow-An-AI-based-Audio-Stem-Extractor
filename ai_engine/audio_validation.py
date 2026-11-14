"""
audio_validation.py - Validates audio files and extracts metadata
"""
import sys
import json
from pathlib import Path
import soundfile as sf
import config

def inspect_audio(file_path: str) -> dict:
    path = Path(file_path)
    if not path.is_file():
        return {
            "valid": False,
            "error": f"File does not exist: {file_path}"
        }

    file_size = path.stat().st_size
    if file_size == 0:
        return {"valid": False, "error": "Uploaded file is empty (0 bytes)."}

    if file_size > config.MAX_FILE_SIZE:
        return {
            "valid": False,
            "error": f"File size exceeds limit ({config.MAX_FILE_SIZE // (1024*1024)} MB)."
        }

    try:
        info = sf.info(str(path))
        duration = float(info.duration)

        if duration <= 0:
            return {"valid": False, "error": "Invalid audio duration."}

        if duration > config.MAX_AUDIO_DURATION:
            return {
                "valid": False,
                "error": f"Audio duration ({round(duration)}s) exceeds max limit of {config.MAX_AUDIO_DURATION}s."
            }

        return {
            "valid": True,
            "duration": round(duration, 2),
            "sample_rate": int(info.samplerate),
            "channels": int(info.channels),
            "format": str(info.format),
            "subtype": str(info.subtype),
            "file_size": file_size
        }
    except Exception as e:
        return {
            "valid": False,
            "error": f"Audio decoding failed: {str(e)}"
        }

if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "inspect":
        result = inspect_audio(sys.argv[2])
        print(json.dumps(result))
    elif len(sys.argv) > 1:
        result = inspect_audio(sys.argv[1])
        print(json.dumps(result))
    else:
        print(json.dumps({"valid": False, "error": "No file path provided"}))
