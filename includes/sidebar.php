<?php
// includes/sidebar.php - DAW-inspired Navigation Sidebar

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentUser = getAuthUser();
?>
<aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <div class="logo-icon">
            <i class="fas fa-wave-square"></i>
        </div>
        <div>
            <h4>StemFlow <span style="color: var(--secondary);">AI</span></h4>
            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 500;">Demucs AI Studio</div>
        </div>
    </a>

    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large" style="width: 20px;"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="upload.php" class="sidebar-link <?= $currentPage === 'upload.php' ? 'active' : '' ?>">
                <i class="fas fa-cloud-upload-alt" style="width: 20px;"></i>
                <span>Upload & Extract</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="projects.php" class="sidebar-link <?= $currentPage === 'projects.php' ? 'active' : '' ?>">
                <i class="fas fa-folder-open" style="width: 20px;"></i>
                <span>Projects Library</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="settings.php" class="sidebar-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-sliders-h" style="width: 20px;"></i>
                <span>Account & Engine</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <?php if ($currentUser): ?>
        <div class="user-badge mb-2">
            <div class="user-avatar">
                <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
            </div>
            <div style="overflow: hidden; flex-grow: 1;">
                <div style="font-size: 0.85rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                    <?= e($currentUser['name']) ?>
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                    <?= e($currentUser['email']) ?>
                </div>
            </div>
            <a href="logout.php" title="Sign Out" style="color: var(--text-muted); font-size: 0.9rem;" class="p-1 hover-light">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
        <?php endif; ?>
        
        <div class="text-center" style="font-size: 0.7rem; color: #4B5563;">
            Demucs v4.1 • 4-Stem AI
        </div>
    </div>
</aside>
