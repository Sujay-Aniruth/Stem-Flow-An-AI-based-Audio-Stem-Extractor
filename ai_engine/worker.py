"""
worker.py - Background job queue worker for StemFlow AI
Polls the job queue directory for pending separation or export jobs.
"""
import time
import json
import traceback
from pathlib import Path

import config
from separation import DemucsSeparator
from export_processing import export_adjusted_stems

JOBS_DIR = config.TEMP_DIR / "jobs"
JOBS_DIR.mkdir(parents=True, exist_ok=True)

def process_job_file(job_file: Path):
    print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Processing job file: {job_file.name}")
    try:
        with open(job_file, "r", encoding="utf-8") as f:
            job = json.load(f)

        job_type = job.get("type", "separate")
        project_id = int(job.get("project_id"))
        status_file = Path(job.get("status_file", config.STEMS_DIR / str(project_id) / "status.json"))

        # Mark in progress
        with open(status_file, "w", encoding="utf-8") as f:
            json.dump({"status": "processing", "project_id": project_id}, f)

        if job_type == "separate":
            input_file = job["input_file"]
            output_dir = job["output_dir"]
            result = DemucsSeparator.separate(input_file, output_dir)
            result["status"] = "completed"
            result["project_id"] = project_id
            with open(status_file, "w", encoding="utf-8") as f:
                json.dump(result, f, indent=2)

        elif job_type == "export":
            stems_dir = job["stems_dir"]
            output_dir = job["output_dir"]
            settings = job["settings"]
            result = export_adjusted_stems(project_id, stems_dir, output_dir, settings)
            result["status"] = "completed"
            result["project_id"] = project_id
            with open(status_file, "w", encoding="utf-8") as f:
                json.dump(result, f, indent=2)

        # Remove processed job file
        job_file.unlink(missing_ok=True)
        print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Job {job_file.name} completed successfully.")

    except Exception as e:
        err = str(e)
        traceback.print_exc()
        try:
            with open(status_file, "w", encoding="utf-8") as f:
                json.dump({"status": "failed", "error": err}, f)
        except Exception:
            pass
        job_file.unlink(missing_ok=True)

def run_worker():
    print(f"StemFlow AI background worker started. Watching {JOBS_DIR}...")
    # Preload Demucs model so separation starts instantly when a job arrives
    try:
        DemucsSeparator.get_model()
    except Exception as e:
        print(f"Warning: Failed to preload Demucs model: {e}")

    while True:
        job_files = sorted(list(JOBS_DIR.glob("job_*.json")))
        if job_files:
            for jf in job_files:
                process_job_file(jf)
        else:
            time.sleep(1)

if __name__ == "__main__":
    run_worker()
