<?php
/**
 * Task Shares API - Manage task sharing with specific users
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get users shared with a task, or get available users to share with
        $taskId = $_GET['task_id'] ?? null;
        
        if ($taskId) {
            // Get users this task is shared with
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.full_name, u.email
                FROM task_shares ts
                JOIN users u ON ts.user_id = u.id
                WHERE ts.task_id = ?
                ORDER BY u.full_name
            ");
            $stmt->execute([$taskId]);
            $sharedWith = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            success_response($sharedWith);
        } else {
            // Get all users in the same company (team members)
            $stmt = $db->prepare("
                SELECT id, username, full_name, email
                FROM users
                WHERE company_id = ? AND id != ? AND is_active = 1
                ORDER BY full_name
            ");
            $stmt->execute([$user['company_id'], $user['id']]);
            $teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            success_response($teamMembers);
        }
        break;
        
    case 'POST':
        // Share task with users
        $input = get_json_input();
        
        if (empty($input['task_id'])) {
            error_response('task_id es requerido');
        }
        
        $taskId = $input['task_id'];
        $userIds = $input['user_ids'] ?? [];
        $shareWithAll = $input['share_with_all'] ?? false;
        
        // Verify task ownership
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $user['id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            error_response('Tarea no encontrada o sin permiso', 404);
        }
        
        // Update is_shared flag
        $stmt = $db->prepare("UPDATE tasks SET is_shared = ? WHERE id = ?");
        $stmt->execute([$shareWithAll ? 1 : 0, $taskId]);
        
        // Clear existing shares and add new ones
        $stmt = $db->prepare("DELETE FROM task_shares WHERE task_id = ?");
        $stmt->execute([$taskId]);
        
        if (!empty($userIds) && !$shareWithAll) {
            $stmt = $db->prepare("INSERT INTO task_shares (task_id, user_id) VALUES (?, ?)");
            foreach ($userIds as $userId) {
                // Verify user is in same company
                $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
                $checkStmt->execute([$userId, $user['company_id']]);
                if ($checkStmt->fetch()) {
                    $stmt->execute([$taskId, $userId]);
                }
            }
        }
        
        success_response(null, 'Configuración de compartir actualizada');
        break;
        
    case 'DELETE':
        // Remove specific user from task shares
        $input = get_json_input();
        
        if (empty($input['task_id']) || empty($input['user_id'])) {
            error_response('task_id y user_id son requeridos');
        }
        
        // Verify task ownership
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$input['task_id'], $user['id']]);
        if (!$stmt->fetch()) {
            error_response('Tarea no encontrada o sin permiso', 404);
        }
        
        $stmt = $db->prepare("DELETE FROM task_shares WHERE task_id = ? AND user_id = ?");
        $stmt->execute([$input['task_id'], $input['user_id']]);
        
        success_response(null, 'Usuario eliminado de compartidos');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
