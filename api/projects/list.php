<?php
// api/projects/list.php - List projects for authenticated user

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuthApi();
$db = getDB();

$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc';
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT p.*, 
           COUNT(s.id) AS stem_count
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

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
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

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();

    foreach ($projects as &$p) {
        $p['file_size_formatted'] = formatBytes((int)$p['file_size']);
        $p['duration_formatted'] = formatDuration((float)$p['audio_duration']);
        $p['created_at_formatted'] = date('M j, Y g:i A', strtotime($p['created_at']));
    }

    jsonResponse([
        'success' => true,
        'count' => count($projects),
        'projects' => $projects
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
