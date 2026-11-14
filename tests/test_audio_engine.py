"""
tests/test_audio_engine.py - Unit and Integration Tests for StemFlow AI Python Engine
"""
import os
import sys
import unittest
from pathlib import Path
import numpy as np
import soundfile as sf

# Add ai_engine to path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "ai_engine"))

import config
from audio_validation import inspect_audio
from pitch_processing import shift_pitch, apply_gain
from separation import DemucsSeparator
from export_processing import export_adjusted_stems

class TestAudioEngine(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp_dir = config.TEMP_DIR / "tests"
        cls.temp_dir.mkdir(parents=True, exist_ok=True)
        cls.test_wav = cls.temp_dir / "synthetic_test.wav"

        # Generate 2 seconds of stereo audio (440Hz sine wave tone + drums-like pulse)
        sr = 44100
        duration = 2.0
        t = np.linspace(0, duration, int(sr * duration), endpoint=False)
        sine = 0.5 * np.sin(2 * np.pi * 440 * t).astype(np.float32)
        noise = 0.2 * np.random.randn(len(t)).astype(np.float32)
        stereo = np.column_stack([sine, noise])
        sf.write(str(cls.test_wav), stereo, sr)

    def test_01_audio_validation(self):
        meta = inspect_audio(str(self.test_wav))
        self.assertTrue(meta["valid"])
        self.assertEqual(meta["sample_rate"], 44100)
        self.assertEqual(meta["channels"], 2)
        self.assertAlmostEqual(meta["duration"], 2.0, places=1)

    def test_02_pitch_shifting_preserves_duration(self):
        sr = 44100
        tone = np.random.randn(44100, 2).astype(np.float32)
        # Shift pitch by +4 semitones
        shifted = shift_pitch(tone, sr, 4)
        self.assertEqual(shifted.shape, tone.shape, "Shifted pitch audio length must strictly match original duration")

        # Shift pitch by -3 semitones
        shifted_down = shift_pitch(tone, sr, -3)
        self.assertEqual(shifted_down.shape, tone.shape, "Shifted pitch audio length must strictly match original duration")

    def test_03_volume_gain_and_clipping(self):
        audio = np.array([0.5, -0.5, 0.8, -0.8], dtype=np.float32)
        # 150% volume
        gained = apply_gain(audio, 150)
        np.testing.assert_allclose(gained, [0.75, -0.75, 1.0, -1.0])

        # 0% volume (mute)
        muted = apply_gain(audio, 0)
        np.testing.assert_allclose(muted, [0.0, 0.0, 0.0, 0.0])

    def test_04_demucs_separation(self):
        out_dir = self.temp_dir / "separated"
        res = DemucsSeparator.separate(str(self.test_wav), str(out_dir))
        self.assertTrue(res["success"])
        self.assertEqual(len(res["stems"]), 4)

        for stem_name in ["vocals", "drums", "bass", "other"]:
            self.assertIn(stem_name, res["stems"])
            stem_file = Path(res["stems"][stem_name]["file_path"])
            self.assertTrue(stem_file.exists())
            self.assertGreater(stem_file.stat().st_size, 0)

    def test_05_export_processing(self):
        stems_dir = self.temp_dir / "separated"
        out_export_dir = self.temp_dir / "exported"
        settings = {
            "vocals_volume": 120,
            "drums_volume": 80,
            "bass_volume": 100,
            "other_volume": 90,
            "vocals_pitch": 2,
            "drums_pitch": 0,
            "bass_pitch": -2,
            "other_pitch": 0,
            "master_volume": 100,
            "stems": {
                "vocals": {"is_muted": False, "is_solo": False},
                "drums": {"is_muted": False, "is_solo": False},
                "bass": {"is_muted": False, "is_solo": False},
                "other": {"is_muted": False, "is_solo": False}
            }
        }
        res = export_adjusted_stems(999, str(stems_dir), str(out_export_dir), settings)
        self.assertTrue(res["success"])
        self.assertTrue(Path(res["zip_path"]).exists())
        self.assertGreater(Path(res["zip_path"]).stat().st_size, 0)

if __name__ == "__main__":
    unittest.main()
