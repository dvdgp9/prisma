<?php
/**
 * App Files API - File management for applications
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get files for an app
        $appId = $_GET['app_id'] ?? null;
        
        if (!$appId) {
            error_response('App ID is required');
        }
        
        // Verify user has access to app
        if (!can_access_app($appId)) {
            error_response('No tienes acceso a esta aplicación', 403);
        }
        
        $stmt = $db->prepare("
            SELECT 
                af.*,
                u.username as uploaded_by_username,
                u.full_name as uploaded_by_name
            FROM app_files af
            LEFT JOIN users u ON af.uploaded_by = u.id
            WHERE af.app_id = ?
            ORDER BY af.created_at DESC
        ");
        $stmt->execute([$appId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response($files);
        break;
        
    case 'POST':
        // Upload file to app
        if (empty($_POST['app_id'])) {
            error_response('App ID is required');
        }
        
        $appId = $_POST['app_id'];
        
        // Verify user has access to app
        if (!can_access_app($appId)) {
            error_response('No tienes acceso a esta aplicación', 403);
        }
        
        if (empty($_FILES['file'])) {
            error_response('No file uploaded');
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_response('File upload failed');
        }
        
        // Validate file size (15MB max for app files)
        $maxSize = 15 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            error_response('El archivo excede 15MB');
        }
        
        // Get mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Create uploads directory for app files
        $uploadDir = __DIR__ . '/../uploads/apps/';
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
            INSERT INTO app_files (app_id, filename, original_filename, file_path, file_size, mime_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $appId,
            $uniqueFilename,
            $file['name'],
            'uploads/apps/' . $uniqueFilename,
            $file['size'],
            $mimeType,
            $user['id']
        ]);
        
        success_response([
            'id' => $db->lastInsertId(),
            'original_filename' => $file['name'],
            'file_size' => $file['size']
        ], 'Archivo subido correctamente');
        break;
        
    case 'DELETE':
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('File ID required');
        }
        
        // Get file info
        $stmt = $db->prepare("SELECT * FROM app_files WHERE id = ?");
        $stmt->execute([$input['id']]);
        $fileInfo = $stmt->fetch();
        
        if (!$fileInfo) {
            error_response('Archivo no encontrado', 404);
        }
        
        // Verify user has access to app
        if (!can_access_app($fileInfo['app_id'])) {
            error_response('No tienes acceso a esta aplicación', 403);
        }
        
        // Delete file from disk
        $filePath = __DIR__ . '/../' . $fileInfo['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM app_files WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        success_response(null, 'Archivo eliminado');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
