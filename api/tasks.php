<?php
/**
 * Tasks API - Quick tasks management
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $taskSelect = "
            SELECT
                t.*,
                u.username as creator_username,
                u.full_name as creator_name,
                a.name as app_name,
                (SELECT COUNT(*) FROM task_attachments WHERE task_id = t.id) as attachment_count,
                (SELECT COUNT(*) FROM task_shares WHERE task_id = t.id) as share_count
            FROM tasks t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN apps a ON t.app_id = a.id
        ";

        // Single task (edit modal): any task the user can see, completed or shared included.
        if (isset($_GET['id'])) {
            $access = require_task_capability($_GET['id'], 'view');

            $stmt = $db->prepare($taskSelect . " WHERE t.id = ?");
            $stmt->execute([$access['task']['id']]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            $task['capabilities'] = $access['capabilities'];

            success_response($task);
        }

        // Daily summary for the page header: own tasks completed today.
        if (isset($_GET['summary'])) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND is_completed = 1 AND completed_at >= CURDATE()");
            $stmt->execute([$user['id']]);

            success_response(['completed_today' => (int) $stmt->fetchColumn()]);
        }

        // Get tasks for current user
        $showCompleted = isset($_GET['completed']) ? $_GET['completed'] === '1' : false;
        $showShared = isset($_GET['shared']) ? $_GET['shared'] === '1' : false;
        $appId = $_GET['app_id'] ?? null;

        $query = $taskSelect . " WHERE t.company_id = :company_id";
        
        $params = [':company_id' => $user['company_id']];
        
        // Filter: own tasks and those assigned to this user by name; whole-team ones only with "Del equipo"
        $query .= " AND " . task_list_visibility_sql($showShared);
        $params[':user_id'] = $user['id'];
        $params[':user_id2'] = $user['id'];
        
        // Filter by completion status (optionally only the recently completed ones)
        if (!$showCompleted) {
            $query .= " AND t.is_completed = 0";
        } elseif (!empty($_GET['completed_days'])) {
            $query .= " AND (t.is_completed = 0 OR t.completed_at >= DATE_SUB(NOW(), INTERVAL :completed_days DAY))";
            $params[':completed_days'] = max(1, (int) $_GET['completed_days']);
        }

        // Filter by app
        if ($appId) {
            $query .= " AND t.app_id = :app_id";
            $params[':app_id'] = $appId;
        }
        
        // Smart ordering: uncompleted tasks by due_date (nulls last), then by created_at;
        // completed ones, most recently completed first
        $query .= " ORDER BY t.is_completed ASC,
                    CASE WHEN t.is_completed = 1 THEN t.completed_at END DESC,
                    CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
                    t.due_date ASC, 
                    t.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // The query above only returns tasks the user can see, so anything not owned is shared with them.
        foreach ($tasks as &$task) {
            $isOwner = (int) $task['user_id'] === (int) $user['id'];
            $task['capabilities'] = task_capabilities_for_access($isOwner, !$isOwner);
        }
        unset($task);

        success_response($tasks);
        break;
        
    case 'POST':
        // Create new task
        $input = get_json_input();
        
        if (empty($input['title'])) {
            error_response('El título es obligatorio');
        }
        
        $stmt = $db->prepare("
            INSERT INTO tasks (user_id, company_id, app_id, title, description, due_date, is_shared)
            VALUES (:user_id, :company_id, :app_id, :title, :description, :due_date, :is_shared)
        ");
        
        $stmt->execute([
            ':user_id' => $user['id'],
            ':company_id' => $user['company_id'],
            ':app_id' => $input['app_id'] ?? null,
            ':title' => trim($input['title']),
            ':description' => $input['description'] ?? null,
            ':due_date' => $input['due_date'] ?? null,
            ':is_shared' => $input['is_shared'] ?? false
        ]);
        
        $taskId = $db->lastInsertId();
        
        success_response(['id' => $taskId], 'Tarea creada');
        break;
        
    case 'PUT':
        // Update task
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('ID de tarea requerido');
        }
        
        // Owner or shared users can edit/complete; only the owner changes sharing.
        require_task_capability($input['id'], task_capability_for_update($input));

        // Build update query dynamically
        $updates = [];
        $params = [];
        
        if (isset($input['title'])) {
            $updates[] = "title = ?";
            $params[] = trim($input['title']);
        }
        if (isset($input['description'])) {
            $updates[] = "description = ?";
            $params[] = $input['description'];
        }
        if (isset($input['is_completed'])) {
            $updates[] = "is_completed = ?";
            $params[] = $input['is_completed'] ? 1 : 0;
            if ($input['is_completed']) {
                $updates[] = "completed_at = NOW()";
            } else {
                $updates[] = "completed_at = NULL";
            }
        }
        if (isset($input['is_shared'])) {
            $updates[] = "is_shared = ?";
            $params[] = $input['is_shared'] ? 1 : 0;
        }
        if (array_key_exists('app_id', $input)) {
            $updates[] = "app_id = ?";
            $params[] = $input['app_id'];
        }
        if (array_key_exists('due_date', $input)) {
            $updates[] = "due_date = ?";
            $params[] = $input['due_date'];
        }
        
        if (empty($updates)) {
            error_response('Nada que actualizar');
        }
        
        $params[] = $input['id'];
        $stmt = $db->prepare("UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        success_response(null, 'Tarea actualizada');
        break;
        
    case 'DELETE':
        // Delete task
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('ID de tarea requerido');
        }
        
        // Only the owner can delete
        require_task_capability($input['id'], 'delete');

        // Delete attachments files first
        $stmt = $db->prepare("SELECT file_path FROM task_attachments WHERE task_id = ?");
        $stmt->execute([$input['id']]);
        $attachments = $stmt->fetchAll();
        
        foreach ($attachments as $att) {
            $filePath = __DIR__ . '/../' . $att['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Delete task (attachments cascade)
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        success_response(null, 'Tarea eliminada');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
