# StemFlow AI — Audio Stem Extraction & Production Workspace

StemFlow AI is a web application and online audio stem extraction workspace. Built with a dark DAW-inspired user interface, PHP 8.2+ REST backend, MySQL database, and a Python AI engine powered by Demucs (Hybrid Transformer `htdemucs`), StemFlow AI allows musicians and producers to upload audio tracks, extract four isolated stems (**Vocals**, **Drums**, **Bass**, and **Other Instruments**), play all tracks in sample-accurate synchronization, adjust volume and duration-preserving pitch, and export or download customized stems.

---

## Architecture & Technology Stack

| Layer | Technologies |
| :--- | :--- |
| **Frontend** | HTML5, CSS3 (DAW Dark Theme), JavaScript (ES6+), Bootstrap 5, WaveSurfer.js v7, Web Audio API, Font Awesome 6 |
| **Backend** | PHP 8.2+, MySQL 8 / MariaDB (InnoDB), PDO, Native Sessions, RESTful JSON APIs |
| **AI Audio Engine** | Python 3.11+, PyTorch, Demucs (`htdemucs` pretrained model), Librosa (Phase Vocoder), SoundFile, NumPy, Julius; CUDA or Apple Silicon MPS acceleration when available |
| **Environment** | Local development on XAMPP (Apache & MySQL) and Python virtual environment (`.venv`) |

---

## Key Features

1. **AI-Powered 4-Stem Separation**: Genuine deep-learning inference using Meta's Demucs pretrained model. Separates into `vocals.wav`, `drums.wav`, `bass.wav`, and `other.wav`.
2. **Synchronized Multitrack Web Player**: Built on Web Audio API with a shared `AudioContext` clock. Starts, pauses, and seeks all stems simultaneously with zero drift.
3. **Independent Volume Gain Controls**: Non-destructive volume adjustment from **0% to 200%** using Web Audio API `GainNode`s with real-time numeric readouts and reset buttons.
4. **Duration-Preserving Pitch Shifting**: Adjust pitch by **-12 to +12 semitones** on every individual stem without altering playback speed or audio duration using a real-time granular pitch shifter and Librosa phase-vocoder.
5. **Solo & Mute Matrix**: LED-style Solo (S) and Mute (M) toggles. Solo isolates active tracks while muting all non-soloed channels.
6. **Automatic Debounced Persistence**: Audio settings (volume, pitch, mute, and solo) automatically save to MySQL via a debounced API with visual save-state indicators.
7. **Stem Downloads & ZIP Archive**: Download individual high-fidelity 16-bit PCM WAV stems or download all stems + original audio in a ZIP archive.
8. **Adjusted Stem Export**: Renders new audio files with user-configured pitch and volume adjustments applied via the Python AI engine.
9. **Multi-User Security & Isolation**: Strict user account isolation. Audio files are stored in private storage protected by `.htaccess` (direct public HTTP access denied). All playback and downloads are served through authenticated streaming endpoints verifying user ownership.

---

## Directory Structure

```text
Stem Flow/
├── index.php                      # Studio Landing Page
├── login.php                      # User Authentication Login
├── register.php                   # User Account Registration
├── dashboard.php                  # Studio Dashboard with Stats & Projects Grid
├── upload.php                     # Drag-and-Drop Audio Upload & AI Extraction
├── workspace.php                  # Flagship Audio Workspace (Multitrack DAW)
├── projects.php                   # Projects Library (Search, Filter, Rename, Delete)
├── settings.php                   # Account & AI Engine Diagnostics
├── logout.php                     # Session Termination
│
├── config/
│   ├── app.php                    # App paths, limits, and configuration
│   ├── database.php               # PDO database connection & auto-migration
│   └── security.php               # Sessions, CSRF, sanitization & auth guards
│
├── includes/
│   ├── header.php                 # Global HTML head & asset includes
│   ├── sidebar.php                # DAW navigation sidebar
│   ├── footer.php                 # Layout footers & scripts
│   ├── auth_check.php             # Page-level session authentication guard
│   └── functions.php              # Formatting, DB queries, and file utilities
│
├── api/
│   ├── auth/                      # register.php, login.php, logout.php, profile.php
│   ├── projects/                  # list.php, get.php, rename.php, delete.php
│   ├── audio/                     # upload.php, metadata.php
│   ├── processing/                # start.php, status.php
│   ├── stems/                     # list.php, stream.php, download.php, download-all.php
│   ├── settings/                  # get.php, save.php, reset.php
│   └── export/                    # create.php, status.php
│
├── assets/
│   ├── css/
│   │   ├── style.css              # Dark theme DAW global styling
│   │   └── workspace.css          # Multitrack channel strips & transport styling
│   └── js/
│       ├── main.js                # Global toast notifications & dialogs
│       ├── upload.js              # Drag-and-drop & extraction progress logic
│       ├── pitch-shifter.js       # Web Audio API granular pitch shifter
│       ├── player.js              # Synchronized multitrack audio engine
│       └── workspace.js           # Workspace UI bindings & auto-save
│
├── ai_engine/
│   ├── app.py                     # AI CLI dispatcher (inspect, separate, export)
│   ├── separation.py              # Demucs separation runner
│   ├── audio_validation.py        # SoundFile media inspection & validator
│   ├── pitch_processing.py        # Librosa phase vocoder pitch shifter
│   ├── export_processing.py       # Adjusted stem rendering & ZIP packaging
│   ├── config.py                  # AI constants & directory paths
│   ├── worker.py                  # Standalone background queue worker
│   └── requirements.txt           # Python dependencies
│
├── database/
│   ├── stemflow_db.sql            # Complete MySQL InnoDB schema
│   └── setup.php                  # CLI database installer & seeder
│
├── storage/
│   ├── .htaccess                  # Deny from all (blocks direct HTTP access)
│   ├── originals/                 # Secure private storage for uploaded songs
│   ├── stems/                     # Separated stems grouped by project ID
│   ├── exports/                   # Rendered adjusted audio & ZIP files
│   └── temp/                      # Temporary extraction scratch files
│
├── logs/
│   ├── .htaccess                  # Deny from all
│   └── app.log                    # Execution & error logs
│
├── tests/
│   └── test_audio_engine.py       # Python audio engine test suite
│
├── .gitignore
└── README.md
```

---

## Setup & Installation

### 1. Requirements
- **Web Server**: Apache (via XAMPP) with PHP 8.2+
- **Database**: MySQL 8 / MariaDB (via XAMPP)
- **PHP Extensions**: `pdo`, `pdo_mysql`, `zip`, `fileinfo`, `session`, `json`
- **Python**: Python 3.11+ with the included virtual environment (`.venv`)

### 2. XAMPP Configuration
1. Open XAMPP Manager (`/Applications/XAMPP/manager-osx.app`).
2. Start the **Apache** and **MySQL** services.
3. Verify that the project is in your XAMPP web root:
   `/Applications/XAMPP/xamppfiles/htdocs/Stem Flow`
4. Ensure Apache can write audio and log files. On the default macOS XAMPP setup, Apache runs as `daemon`; grant that account access to the application storage folders:
   ```bash
   for dir in storage/originals storage/stems storage/exports storage/temp logs; do
     sudo chmod +a "daemon allow read,write,execute,add_file,add_subdirectory,delete_child,file_inherit,directory_inherit" "$dir"
   done
   mkdir -p storage/temp/jobs
   chmod +a "daemon allow read,write,execute,add_file,add_subdirectory,delete_child,file_inherit,directory_inherit" storage/temp/jobs
   ```
   If your Apache worker runs as a different user, replace `daemon` with that account. The upload endpoint reports a storage-permission error if `storage/originals` is not writable.
   The native Python worker also needs access to output folders created by Apache. Grant your macOS login account inherited access to `storage/stems` (replace `YOUR_MACOS_USERNAME`):
   ```bash
   chmod +a "YOUR_MACOS_USERNAME allow read,write,execute,add_file,add_subdirectory,delete_child,file_inherit,directory_inherit" storage/stems
   ```

### 3. Database Initialization
Ensure MySQL is running, then run the automated setup script in your terminal:
```bash
/Applications/XAMPP/xamppfiles/bin/php database/setup.php
```
Alternatively, import `database/stemflow_db.sql` into phpMyAdmin or MySQL CLI:
```bash
/Applications/XAMPP/xamppfiles/bin/mysql -u root < database/stemflow_db.sql
```
This initializes the `stemflow_db` database and seeds a default demo account:
- **Email**: `demo@stemflow.ai`
- **Password**: `password123`

### 4. Python Environment & Pretrained Model
The Python virtual environment (`.venv`) is configured with Demucs 4.1.0, PyTorch, Librosa, SoundFile, and Julius.
Stem separation uses CUDA when available, otherwise Apple Silicon's MPS GPU, and falls back to CPU. Inference uses a reduced Demucs segment overlap (`0.1`) to avoid redundant work; this may slightly affect transitions between segments. Exact runtime depends on the hardware, audio, and first-run model download. The model cache is kept in `storage/temp/torch`.

To run the automated verification test suite:
```bash
.venv/bin/python3 tests/test_audio_engine.py
```
Expected output:
```text
Ran 5 tests in ~2.9s: OK
```

---

## Running the Application

1. Start the AI worker in a Terminal window and leave it running while using stem extraction:
   ```bash
   cd "/Applications/XAMPP/xamppfiles/htdocs/Stem Flow"
   .venv/bin/python3 ai_engine/worker.py
   ```
   The worker runs in the native Apple Silicon Python environment, preloads Demucs once, and processes queued uploads. On first startup it may download the model.
2. Open your web browser and navigate to:
   ```text
   http://localhost/Stem%20Flow/
   ```
   (Or `http://127.0.0.1/Stem%20Flow/`)
3. Sign in with the demo account or register a new user account.
4. Click **"New Audio Extraction"** or navigate to **Upload & Extract**.
5. Drag and drop any MP3, WAV, FLAC, M4A, or OGG song.
6. Click **"Start Demucs Extraction"**.
7. The Demucs AI engine will separate the track into Vocals, Drums, Bass, and Other Instruments.
8. Click **"Open in Audio Workspace"** to enter the studio DAW:
   - Hit **Play** (or press Spacebar) to audition all stems in synchronization.
   - Adjust volume (0% to 200%) or pitch (-12 to +12 semitones) on any stem.
   - Toggle **Solo** (S) or **Mute** (M) on individual tracks.
   - All adjustments are automatically saved to your MySQL project profile.
   - Click **"Export Adjusted Stems"** to render and download your customized audio files.
