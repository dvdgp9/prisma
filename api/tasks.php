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
        // Get tasks for current user
        $showCompleted = isset($_GET['completed']) ? $_GET['completed'] === '1' : false;
        $showShared = isset($_GET['shared']) ? $_GET['shared'] === '1' : false;
        $appId = $_GET['app_id'] ?? null;
        
        $query = "
            SELECT 
                t.*,
                u.username as creator_username,
                u.full_name as creator_name,
                a.name as app_name,
                (SELECT COUNT(*) FROM task_attachments WHERE task_id = t.id) as attachment_count
            FROM tasks t
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN apps a ON t.app_id = a.id
            WHERE t.company_id = :company_id
        ";
        
        $params = [':company_id' => $user['company_id']];
        
        // Filter: own tasks OR shared tasks from team
        if ($showShared) {
            $query .= " AND (t.user_id = :user_id OR t.is_shared = 1)";
        } else {
            $query .= " AND t.user_id = :user_id";
        }
        $params[':user_id'] = $user['id'];
        
        // Filter by completion status
        if (!$showCompleted) {
            $query .= " AND t.is_completed = 0";
        }
        
        // Filter by app
        if ($appId) {
            $query .= " AND t.app_id = :app_id";
            $params[':app_id'] = $appId;
        }
        
        // Smart ordering: uncompleted tasks by due_date (nulls last), then by created_at
        $query .= " ORDER BY t.is_completed ASC, 
                    CASE WHEN t.due_date IS NULL THEN 1 ELSE 0 END,
                    t.due_date ASC, 
                    t.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        
        // Verify ownership or shared access
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND (user_id = ? OR (is_shared = 1 AND company_id = ?))");
        $stmt->execute([$input['id'], $user['id'], $user['company_id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            error_response('Tarea no encontrada', 404);
        }
        
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
        
        // Verify ownership
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$input['id'], $user['id']]);
        $task = $stmt->fetch();
        
        if (!$task) {
            error_response('Tarea no encontrada o sin permiso', 404);
        }
        
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
