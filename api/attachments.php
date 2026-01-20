<?php
/**
 * Attachments API - Get and delete attachments for requests
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get attachments for a request
        $request_id = $_GET['request_id'] ?? null;
        
        if (!$request_id) {
            error_response('Request ID is required');
        }
        
        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.request_id,
                a.filename,
                a.original_filename,
                a.file_path,
                a.file_size,
                a.mime_type,
                a.created_at,
                u.username as uploaded_by_username,
                u.full_name as uploaded_by_name
            FROM attachments a
            LEFT JOIN users u ON a.uploaded_by = u.id
            WHERE a.request_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$request_id]);
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response($attachments);
        break;
        
    case 'DELETE':
        // Delete an attachment
        $input = get_json_input();
        $attachment_id = $input['id'] ?? null;
        
        if (!$attachment_id) {
            error_response('Attachment ID is required');
        }
        
        // Get attachment info
        $stmt = $db->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            error_response('Attachment not found', 404);
        }
        
        // Check permission (only admin or uploader can delete)
        if ($user['role'] !== 'superadmin' && $user['role'] !== 'admin' && $attachment['uploaded_by'] != $user['id']) {
            error_response('No tienes permiso para eliminar este archivo', 403);
        }
        
        // Delete file from disk
        $file_path = __DIR__ . '/../' . $attachment['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);
        
        success_response(null, 'Archivo eliminado correctamente');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
