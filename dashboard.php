<?php
// dashboard.php - Main Application Dashboard

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int)$_SESSION['user_id'];
$currentUser = getAuthUser();
$db = getDB();

// Fetch metrics
$stmtMetrics = $db->prepare("
    SELECT 
        COUNT(*) AS total_projects,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects
    FROM projects 
    WHERE user_id = ?
");
$stmtMetrics->execute([$userId]);
$metrics = $stmtMetrics->fetch();

$stmtStems = $db->prepare("
    SELECT COUNT(s.id) AS total_stems 
    FROM stems s 
    JOIN projects p ON s.project_id = p.id 
    WHERE p.user_id = ?
");
$stmtStems->execute([$userId]);
$totalStems = (int)$stmtStems->fetchColumn();

// Fetch projects with search & sort
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc';

$sql = "
    SELECT p.*, COUNT(s.id) AS stem_count
    FROM projects p
    LEFT JOIN stems s ON p.id = s.project_id
    WHERE p.user_id = ?
";
$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (p.title LIKE ? OR p.original_filename LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY p.id";

switch ($sort) {
    case 'date_asc':
        $sql .= " ORDER BY p.created_at ASC";
        break;
    case 'title_asc':
        $sql .= " ORDER BY p.title ASC";
        break;
    case 'title_desc':
        $sql .= " ORDER BY p.title DESC";
        break;
    case 'date_desc':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

$pageTitle = 'Dashboard — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <!-- Top Navbar -->
    <header class="top-navbar">
        <div>
            <h5 class="mb-0 fw-bold">Studio Dashboard</h5>
            <span class="text-muted small">Welcome back, <?= e($currentUser['name']) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="upload.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> New Audio Extraction
            </a>
        </div>
    </header>

    <div class="content-body">
        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="daw-card d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-secondary small fw-bold text-uppercase">Total Projects</div>
                        <h3 class="fw-bold mb-0 mt-1"><?= (int)($metrics['total_projects'] ?? 0) ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(139, 92, 246, 0.15); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        <i class="fas fa-folder"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="daw-card d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-secondary small fw-bold text-uppercase">Extracted Songs</div>
                        <h3 class="fw-bold mb-0 mt-1"><?= (int)($metrics['completed_projects'] ?? 0) ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(16, 185, 129, 0.15); color: #34D399; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="daw-card d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-secondary small fw-bold text-uppercase">Generated Stems</div>
                        <h3 class="fw-bold mb-0 mt-1"><?= $totalStems ?></h3>
                    </div>
                    <div style="width: 48px; height: 48px; border-radius: 10px; background: rgba(34, 211, 238, 0.15); color: var(--secondary); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <form method="GET" action="dashboard.php" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="q" class="form-control" placeholder="Search by project name or audio file..." value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="d-flex align-items-center gap-2">
                <label class="text-secondary small mb-0 d-none d-sm-block">Sort:</label>
                <select class="form-select form-select-sm" style="width: 170px;" onchange="location.href='dashboard.php?q=<?= urlencode($search) ?>&sort=' + this.value">
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                </select>
            </div>
        </div>

        <!-- Projects Grid -->
        <?php if (empty($projects)): ?>
        <div class="daw-card text-center py-5">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(139, 92, 246, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 16px;">
                <i class="fas fa-music"></i>
            </div>
            <h5 class="fw-bold">No Projects Found</h5>
            <p class="text-secondary small mb-4" style="max-width: 400px; margin: 0 auto;">
                <?= !empty($search) ? 'No songs match your search query. Try another search term.' : 'Upload your first audio track to separate Vocals, Drums, Bass, and Instruments using Demucs AI.' ?>
            </p>
            <a href="upload.php" class="btn btn-primary">
                <i class="fas fa-cloud-upload-alt me-1"></i> Upload First Song
            </a>
        </div>
        <?php else: ?>
        <div class="row g-3">
            <?php foreach ($projects as $proj): ?>
            <div class="col-md-6 col-lg-4" id="projectCard_<?= $proj['id'] ?>">
                <div class="daw-card h-100 d-flex flex-direction-column justify-content-between p-3">
                    <div>
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <h6 class="fw-bold mb-0 text-truncate" title="<?= e($proj['title']) ?>">
                                <?= e($proj['title']) ?>
                            </h6>
                            <span class="badge-status <?= e($proj['status']) ?>">
                                <?= e($proj['status']) ?>
                            </span>
                        </div>

                        <div class="text-muted small text-truncate mb-3" title="<?= e($proj['original_filename']) ?>">
                            <i class="fas fa-file-audio me-1"></i> <?= e($proj['original_filename']) ?>
                        </div>

                        <div class="d-flex align-items-center gap-3 text-secondary small mb-3">
                            <div>
                                <i class="fas fa-clock me-1 text-muted"></i> <?= formatDuration((float)$proj['audio_duration']) ?>
                            </div>
                            <div>
                                <i class="fas fa-hdd me-1 text-muted"></i> <?= formatBytes((int)$proj['file_size']) ?>
                            </div>
                            <div>
                                <i class="fas fa-layer-group me-1 text-muted"></i> <?= (int)$proj['stem_count'] ?>/4 Stems
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 d-flex align-items-center justify-content-between" style="border-color: var(--border-color) !important;">
                        <span class="text-muted" style="font-size: 0.75rem;">
                            <?= date('M j, Y', strtotime($proj['created_at'])) ?>
                        </span>

                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-secondary btn-sm px-2" title="Delete Project" onclick="StemFlow.confirmDeleteProject(<?= $proj['id'] ?>, '<?= addslashes(e($proj['title'])) ?>', () => document.getElementById('projectCard_<?= $proj['id'] ?>').remove())">
                                <i class="fas fa-trash-alt text-danger"></i>
                            </button>

                            <?php if ($proj['status'] === 'completed'): ?>
                            <a href="workspace.php?id=<?= $proj['id'] ?>" class="btn btn-primary btn-sm px-3">
                                <i class="fas fa-sliders-h me-1"></i> Studio
                            </a>
                            <?php elseif ($proj['status'] === 'processing'): ?>
                            <a href="workspace.php?id=<?= $proj['id'] ?>" class="btn btn-outline-info btn-sm px-3">
                                <i class="fas fa-spinner fa-spin me-1"></i> View Status
                            </a>
                            <?php else: ?>
                            <a href="upload.php" class="btn btn-secondary btn-sm px-3">
                                <i class="fas fa-redo me-1"></i> Re-Extract
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
