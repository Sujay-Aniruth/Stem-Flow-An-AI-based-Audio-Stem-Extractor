<?php
// api/auth/profile.php - User Profile API (GET & POST)

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

$userId = requireAuthApi();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("SELECT id, name, email, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['success' => false, 'error' => 'User not found.'], 404);
        }

        // Stats
        $stmtProj = $db->prepare("SELECT COUNT(*) AS total_projects, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_projects FROM projects WHERE user_id = ?");
        $stmtProj->execute([$userId]);
        $projStats = $stmtProj->fetch();

        $stmtStems = $db->prepare("SELECT COUNT(s.id) AS total_stems FROM stems s JOIN projects p ON s.project_id = p.id WHERE p.user_id = ?");
        $stmtStems->execute([$userId]);
        $stemStats = $stmtStems->fetch();

        jsonResponse([
            'success' => true,
            'user' => $user,
            'stats' => [
                'total_projects' => (int)($projStats['total_projects'] ?? 0),
                'completed_projects' => (int)($projStats['completed_projects'] ?? 0),
                'total_stems' => (int)($stemStats['total_stems'] ?? 0)
            ]
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'update_profile';

    try {
        if ($action === 'change_password') {
            $currentPassword = $input['current_password'] ?? '';
            $newPassword = $input['new_password'] ?? '';
            $confirmPassword = $input['confirm_password'] ?? '';

            if (empty($currentPassword) || empty($newPassword)) {
                jsonResponse(['success' => false, 'error' => 'Current and new password are required.'], 422);
            }
            if (strlen($newPassword) < 6) {
                jsonResponse(['success' => false, 'error' => 'New password must be at least 6 characters long.'], 422);
            }
            if ($newPassword !== $confirmPassword) {
                jsonResponse(['success' => false, 'error' => 'New passwords do not match.'], 422);
            }

            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();

            if (!password_verify($currentPassword, $currentHash)) {
                jsonResponse(['success' => false, 'error' => 'Incorrect current password.'], 403);
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUpdate = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtUpdate->execute([$newHash, $userId]);

            jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
        } else {
            // Update name
            $name = trim($input['name'] ?? '');
            if (empty($name) || strlen($name) < 2) {
                jsonResponse(['success' => false, 'error' => 'Name must be at least 2 characters long.'], 422);
            }

            $stmtUpdate = $db->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmtUpdate->execute([$name, $userId]);
            $_SESSION['user_name'] = $name;

            jsonResponse(['success' => true, 'message' => 'Profile updated successfully.', 'name' => $name]);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
