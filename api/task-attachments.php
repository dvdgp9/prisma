<?php
/**
 * Task Attachments API - File upload handler for tasks
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get attachments for a task
        if (empty($_GET['task_id'])) {
            error_response('Task ID is required');
        }

        $access = require_task_capability($_GET['task_id'], 'view');

        // No file_path: files are served through api/file.php with a permission check.
        $stmt = $db->prepare("
            SELECT id, task_id, original_filename, file_size, mime_type, created_at
            FROM task_attachments
            WHERE task_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$access['task']['id']]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response($attachments);
        break;
        
    case 'POST':
        // Upload file to task
        if (empty($_POST['task_id'])) {
            error_response('Task ID is required');
        }
        
        $access = require_task_capability($_POST['task_id'], 'attach');
        $taskId = $access['task']['id'];

        if (empty($_FILES['file'])) {
            error_response('No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_response('File upload failed');
        }
        
        // Validate file size (15MB max)
        $maxSize = 15 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            error_response('El archivo excede 15MB');
        }
        
        // Validate file type
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            error_response('Tipo de archivo no permitido');
        }
        
        // Create uploads directory
        $uploadDir = __DIR__ . '/../uploads/tasks/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueFilename = uniqid() . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $uniqueFilename;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            error_response('Error al guardar archivo', 500);
        }
        
        // Save to database
        $stmt = $db->prepare("
            INSERT INTO task_attachments (task_id, filename, original_filename, file_path, file_size, mime_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $taskId,
            $uniqueFilename,
            $file['name'],
            'uploads/tasks/' . $uniqueFilename,
            $file['size'],
            $mimeType
        ]);
        
        success_response([
            'id' => $db->lastInsertId(),
            'original_filename' => $file['name'],
            'file_size' => $file['size']
        ], 'Archivo subido');
        break;
        
    case 'DELETE':
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('Attachment ID required');
        }
        
        $stmt = $db->prepare("SELECT id, task_id, file_path FROM task_attachments WHERE id = ?");
        $stmt->execute([$input['id']]);
        $attachment = $stmt->fetch();

        if (!$attachment) {
            error_response('No encontrado o sin permiso', 404);
        }

        // Removing files stays owner-only, as before
        require_task_capability($attachment['task_id'], 'delete');

        // Delete file
        $filePath = __DIR__ . '/../' . $attachment['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM task_attachments WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        success_response(null, 'Archivo eliminado');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
