<?php
// projects.php - Projects Library

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/functions.php';

$userId = (int)$_SESSION['user_id'];
$db = getDB();

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

$pageTitle = 'Projects Library — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="main-content">
    <header class="top-navbar">
        <div>
            <h5 class="mb-0 fw-bold">Projects Library</h5>
            <span class="text-muted small">Manage all your uploaded audio songs, stem extractions, and exports</span>
        </div>
        <a href="upload.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> New Audio Upload
        </a>
    </header>

    <div class="content-body">
        <!-- Search and Sort Filter -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <form method="GET" action="projects.php" class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="q" class="form-control" placeholder="Search projects..." value="<?= e($search) ?>">
                    <?php if (!empty($search)): ?>
                    <a href="projects.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="d-flex align-items-center gap-2">
                <label class="text-secondary small mb-0 d-none d-sm-block">Sort:</label>
                <select class="form-select form-select-sm" style="width: 170px;" onchange="location.href='projects.php?q=<?= urlencode($search) ?>&sort=' + this.value">
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                </select>
            </div>
        </div>

        <?php if (empty($projects)): ?>
        <div class="daw-card text-center py-5">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(139, 92, 246, 0.1); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 16px;">
                <i class="fas fa-folder-open"></i>
            </div>
            <h5 class="fw-bold">No Projects Found</h5>
            <p class="text-secondary small mb-4">
                <?= !empty($search) ? 'No projects match your filter.' : 'You have not uploaded any tracks yet.' ?>
            </p>
            <a href="upload.php" class="btn btn-primary">Upload Audio File</a>
        </div>
        <?php else: ?>
        <div class="daw-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" style="background-color: var(--bg-card);">
                    <thead style="background-color: #14141C; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary);">
                        <tr>
                            <th class="ps-4 py-3">Project Title</th>
                            <th class="py-3">Original File</th>
                            <th class="py-3">Duration</th>
                            <th class="py-3">Size</th>
                            <th class="py-3">Stems</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Created</th>
                            <th class="pe-4 py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.88rem; vertical-align: middle;">
                        <?php foreach ($projects as $proj): ?>
                        <tr id="tableRowProj_<?= $proj['id'] ?>">
                            <td class="ps-4 fw-bold">
                                <a href="workspace.php?id=<?= $proj['id'] ?>" class="text-white text-decoration-none hover-primary">
                                    <?= e($proj['title']) ?>
                                </a>
                                <button class="btn btn-sm btn-link text-muted p-0 ms-1" title="Rename" onclick="StemFlow.renameProject(<?= $proj['id'] ?>, '<?= addslashes(e($proj['title'])) ?>', () => location.reload())">
                                    <i class="fas fa-pencil-alt small"></i>
                                </button>
                            </td>
                            <td class="text-secondary text-truncate" style="max-width: 180px;" title="<?= e($proj['original_filename']) ?>">
                                <i class="fas fa-file-audio me-1 text-muted"></i> <?= e($proj['original_filename']) ?>
                            </td>
                            <td class="text-secondary">
                                <?= formatDuration((float)$proj['audio_duration']) ?>
                            </td>
                            <td class="text-secondary">
                                <?= formatBytes((int)$proj['file_size']) ?>
                            </td>
                            <td>
                                <span class="badge bg-dark border border-secondary border-opacity-25">
                                    <?= (int)$proj['stem_count'] ?> / 4
                                </span>
                            </td>
                            <td>
                                <span class="badge-status <?= e($proj['status']) ?>">
                                    <?= e($proj['status']) ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('M j, Y', strtotime($proj['created_at'])) ?>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-inline-flex gap-2">
                                    <?php if ($proj['status'] === 'completed'): ?>
                                    <a href="workspace.php?id=<?= $proj['id'] ?>" class="btn btn-primary btn-sm px-3" title="Open Studio Workspace">
                                        <i class="fas fa-sliders-h me-1"></i> Studio
                                    </a>
                                    <a href="api/stems/download-all.php?project_id=<?= $proj['id'] ?>" class="btn btn-secondary btn-sm" title="Download Stems ZIP">
                                        <i class="fas fa-file-archive"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-secondary btn-sm" title="Delete Project" onclick="StemFlow.confirmDeleteProject(<?= $proj['id'] ?>, '<?= addslashes(e($proj['title'])) ?>', () => document.getElementById('tableRowProj_<?= $proj['id'] ?>').remove())">
                                        <i class="fas fa-trash-alt text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
