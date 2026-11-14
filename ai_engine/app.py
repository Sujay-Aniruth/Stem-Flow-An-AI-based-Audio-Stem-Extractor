"""
app.py - Main CLI entrypoint for Python AI Engine
"""
import sys
import json
import traceback
from pathlib import Path

import config
from audio_validation import inspect_audio
from separation import DemucsSeparator
from export_processing import export_adjusted_stems

def handle_inspect(args):
    if len(args) < 1:
        print(json.dumps({"valid": False, "error": "Missing audio file path"}))
        return
    res = inspect_audio(args[0])
    print(json.dumps(res))

def handle_separate(args):
    if len(args) < 3:
        print(json.dumps({"success": False, "error": "Usage: separate <project_id> <input_file> <output_dir> [status_file]"}))
        return

    project_id = int(args[0])
    input_file = args[1]
    output_dir = args[2]
    status_file = args[3] if len(args) > 3 else str(Path(output_dir) / "status.json")

    status_path = Path(status_file)
    status_path.parent.mkdir(parents=True, exist_ok=True)

    # Initial queued/processing state file
    with open(status_path, "w", encoding="utf-8") as f:
        json.dump({"status": "processing", "project_id": project_id, "progress": 10}, f)

    try:
        result = DemucsSeparator.separate(input_file, output_dir)
        result["status"] = "completed"
        result["project_id"] = project_id
        with open(status_path, "w", encoding="utf-8") as f:
            json.dump(result, f, indent=2)
        print(json.dumps(result))
    except Exception as e:
        err_msg = str(e)
        traceback.print_exc(file=sys.stderr)
        err_result = {
            "status": "failed",
            "success": False,
            "project_id": project_id,
            "error": err_msg
        }
        with open(status_path, "w", encoding="utf-8") as f:
            json.dump(err_result, f, indent=2)
        print(json.dumps(err_result))

def handle_export(args):
    if len(args) < 4:
        print(json.dumps({"success": False, "error": "Usage: export <project_id> <stems_dir> <output_dir> <settings_json_or_file> [status_file]"}))
        return

    project_id = int(args[0])
    stems_dir = args[1]
    output_dir = args[2]
    settings_raw = args[3]
    status_file = args[4] if len(args) > 4 else str(Path(output_dir) / "export_status.json")

    # Parse settings (either direct JSON string or file path)
    if Path(settings_raw).is_file():
        with open(settings_raw, "r", encoding="utf-8") as f:
            settings = json.load(f)
    else:
        settings = json.loads(settings_raw)

    try:
        result = export_adjusted_stems(project_id, stems_dir, output_dir, settings)
        result["status"] = "completed"
        result["project_id"] = project_id
        with open(status_file, "w", encoding="utf-8") as f:
            json.dump(result, f, indent=2)
        print(json.dumps(result))
    except Exception as e:
        err_result = {
            "status": "failed",
            "success": False,
            "project_id": project_id,
            "error": str(e)
        }
        with open(status_file, "w", encoding="utf-8") as f:
            json.dump(err_result, f, indent=2)
        print(json.dumps(err_result))

def main():
    if len(sys.argv) < 2:
        print("StemFlow AI Engine CLI")
        print("Usage: python app.py [inspect|separate|export] [args...]")
        sys.exit(1)

    cmd = sys.argv[1].lower()
    args = sys.argv[2:]

    if cmd == "inspect":
        handle_inspect(args)
    elif cmd == "separate":
        handle_separate(args)
    elif cmd == "export":
        handle_export(args)
    else:
        print(f"Unknown command: {cmd}")
        sys.exit(1)

if __name__ == "__main__":
    main()
