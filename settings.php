<?php
// settings.php - Account & AI Engine Settings

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int)$_SESSION['user_id'];
$currentUser = getAuthUser();
$db = getDB();

// Calculate storage stats
function getDirSize(string $path): int {
    if (!is_dir($path)) return 0;
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

$origStorage = getDirSize(ORIGINALS_PATH);
$stemStorage = getDirSize(STEMS_PATH);
$exportStorage = getDirSize(EXPORTS_PATH);
$totalStorage = $origStorage + $stemStorage + $exportStorage;

$pageTitle = 'Account & Engine Settings — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <header class="top-navbar">
        <div>
            <h5 class="mb-0 fw-bold">Settings & Diagnostics</h5>
            <span class="text-muted small">Manage account credentials and inspect the AI separation engine</span>
        </div>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
    </header>

    <div class="content-body">
        <div class="row g-4">
            <!-- Account Profile Card -->
            <div class="col-lg-6">
                <div class="daw-card h-100">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-user-circle text-primary"></i> Profile Information
                    </h5>

                    <form id="profileForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Full Name</label>
                            <input type="text" id="profileName" class="form-control" value="<?= e($currentUser['name']) ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">Email Address</label>
                            <input type="email" class="form-control" value="<?= e($currentUser['email']) ?>" disabled readonly>
                            <span class="text-muted" style="font-size: 0.72rem;">Email cannot be changed directly for security.</span>
                        </div>
                        <button type="submit" id="btnSaveProfile" class="btn btn-primary btn-sm px-3">
                            Save Profile
                        </button>
                    </form>

                    <hr class="my-4" style="border-color: var(--border-color);">

                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-key text-secondary"></i> Change Password
                    </h5>

                    <form id="passwordForm">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">Current Password</label>
                            <input type="password" id="currentPassword" class="form-control" required placeholder="••••••••">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">New Password</label>
                            <input type="password" id="newPassword" class="form-control" minlength="6" required placeholder="••••••••">
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">Confirm New Password</label>
                            <input type="password" id="confirmPassword" class="form-control" minlength="6" required placeholder="••••••••">
                        </div>
                        <button type="submit" id="btnChangePassword" class="btn btn-secondary btn-sm px-3">
                            Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- AI Engine & Diagnostics Card -->
            <div class="col-lg-6">
                <div class="daw-card mb-4">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-brain text-info"></i> AI Separation Engine
                    </h5>
                    <ul class="list-group list-group-flush bg-transparent">
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Pretrained Model</span>
                            <span class="badge bg-dark border border-secondary border-opacity-25 text-white">htdemucs (Hybrid Transformer)</span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Target Stems</span>
                            <span class="badge bg-primary">Vocals • Drums • Bass • Other</span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Demucs AI Status</span>
                            <span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i> Pretrained Weights Loaded</span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Pitch Shifter Engine</span>
                            <span class="text-info small fw-bold"><i class="fas fa-check-circle me-1"></i> Web Audio Granular & Librosa Phase Vocoder</span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Sample Rate</span>
                            <span class="text-white small">44,100 Hz (Stereo 16-bit PCM)</span>
                        </li>
                    </ul>
                </div>

                <!-- Storage & Server Environment Card -->
                <div class="daw-card">
                    <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="fas fa-server text-secondary"></i> System & Storage
                    </h5>
                    <ul class="list-group list-group-flush bg-transparent mb-3">
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>PHP Version</span>
                            <span class="text-white small"><?= phpversion() ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Database</span>
                            <span class="text-white small">MySQL 8 / MariaDB (InnoDB)</span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Original Audio Storage</span>
                            <span class="text-white small"><?= formatBytes($origStorage) ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Separated Stems Storage</span>
                            <span class="text-white small"><?= formatBytes($stemStorage) ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2" style="border-color: var(--border-color);">
                            <span>Export Files Storage</span>
                            <span class="text-white small"><?= formatBytes($exportStorage) ?></span>
                        </li>
                        <li class="list-group-item bg-transparent text-secondary d-flex justify-content-between align-items-center px-0 py-2 fw-bold" style="border-color: var(--border-color);">
                            <span>Total Storage Allocated</span>
                            <span class="text-info"><?= formatBytes($totalStorage) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Save Profile
document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('profileName').value.trim();
    const btn = document.getElementById('btnSaveProfile');
    btn.disabled = true;

    try {
        const res = await fetch('api/auth/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_profile', name })
        });
        const data = await res.json();
        if (data.success) {
            StemFlow.toast('Profile updated successfully', 'success');
        } else {
            StemFlow.toast(data.error || 'Failed to update profile', 'error');
        }
    } catch (err) {
        StemFlow.toast('Network error updating profile', 'error');
    } finally {
        btn.disabled = false;
    }
});

// Change Password
document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const current_password = document.getElementById('currentPassword').value;
    const new_password = document.getElementById('newPassword').value;
    const confirm_password = document.getElementById('confirmPassword').value;

    if (new_password !== confirm_password) {
        StemFlow.toast('New passwords do not match', 'error');
        return;
    }

    const btn = document.getElementById('btnChangePassword');
    btn.disabled = true;

    try {
        const res = await fetch('api/auth/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'change_password',
                current_password,
                new_password,
                confirm_password
            })
        });
        const data = await res.json();
        if (data.success) {
            StemFlow.toast('Password changed successfully', 'success');
            document.getElementById('passwordForm').reset();
        } else {
            StemFlow.toast(data.error || 'Failed to change password', 'error');
        }
    } catch (err) {
        StemFlow.toast('Network error updating password', 'error');
    } finally {
        btn.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
