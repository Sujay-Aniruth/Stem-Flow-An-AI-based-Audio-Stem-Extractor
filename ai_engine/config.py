"""
config.py - AI Engine configuration for StemFlow AI
"""
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
STORAGE_DIR = BASE_DIR / "storage"
ORIGINALS_DIR = STORAGE_DIR / "originals"
STEMS_DIR = STORAGE_DIR / "stems"
EXPORTS_DIR = STORAGE_DIR / "exports"
TEMP_DIR = STORAGE_DIR / "temp"
LOGS_DIR = BASE_DIR / "logs"

# Keep the model cache accessible to the Apache-launched processing process.
os.environ.setdefault("TORCH_HOME", str(TEMP_DIR / "torch"))

MODEL_NAME = "htdemucs"
DEMUCS_OVERLAP = 0.1
DEFAULT_SAMPLE_RATE = 44100
DEFAULT_CHANNELS = 2
MAX_AUDIO_DURATION = 900  # 15 minutes
MAX_FILE_SIZE = 100 * 1024 * 1024  # 100 MB

STEM_TYPES = ["vocals", "drums", "bass", "other"]

# Ensure directories exist
for d in [STORAGE_DIR, ORIGINALS_DIR, STEMS_DIR, EXPORTS_DIR, TEMP_DIR, LOGS_DIR]:
    d.mkdir(parents=True, exist_ok=True)
