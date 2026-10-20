<?php
/**
 * Task Shares API - Manage task sharing with specific users
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/task-shares.php';

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
            // Get users this task is shared with (only for people who can see the task)
            require_task_capability($taskId, 'view');
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.full_name, u.email, ts.seen_at
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
        
        // Only the owner changes who the task is shared with
        require_task_capability($taskId, 'share');

        // By difference, so whoever keeps the task keeps their "seen" mark;
        // the people just added get a notice in the bell.
        $added = replace_task_shares($db, $user, $taskId, $shareWithAll, $userIds);
        // The share is already saved: a failed notice must not report the change as failed.
        try {
            create_task_assignment_notifications($db, $taskId, $added, $user);
        } catch (Exception $e) {
            error_log('Failed to create task assignment notifications for task ' . $taskId . ': ' . $e->getMessage());
        }

        success_response(null, 'Configuración de compartir actualizada');
        break;
        
    case 'PUT':
        // Tasks shown to the current user in a list: seen (only their own shares).
        // Opened tasks: seen, and their assignment notice is read.
        $input = get_json_input();
        $result = !empty($input['opened_task_ids'])
            ? mark_tasks_seen($db, $user['id'], $input['opened_task_ids'], true)
            : mark_tasks_seen($db, $user['id'], $input['seen_task_ids'] ?? []);

        success_response($result);
        break;

    case 'DELETE':
        // Remove specific user from task shares
        $input = get_json_input();
        
        if (empty($input['task_id']) || empty($input['user_id'])) {
            error_response('task_id y user_id son requeridos');
        }
        
        // Only the owner changes who the task is shared with
        require_task_capability($input['task_id'], 'share');

        $stmt = $db->prepare("DELETE FROM task_shares WHERE task_id = ? AND user_id = ?");
        $stmt->execute([$input['task_id'], $input['user_id']]);
        $stmt = $db->prepare("DELETE FROM notifications WHERE type = 'task_assignment' AND task_id = ? AND user_id = ?");
        $stmt->execute([$input['task_id'], $input['user_id']]);
        
        success_response(null, 'Usuario eliminado de compartidos');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
