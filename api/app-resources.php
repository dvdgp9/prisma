<?php
/**
 * App Resources API - Links and notes management for applications
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get resources for an app
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
                ar.*,
                u.username as created_by_username,
                u.full_name as created_by_name
            FROM app_resources ar
            LEFT JOIN users u ON ar.created_by = u.id
            WHERE ar.app_id = ?
            ORDER BY ar.created_at DESC
        ");
        $stmt->execute([$appId]);
        $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response($resources);
        break;
        
    case 'POST':
        // Create new resource (link or note)
        $input = get_json_input();
        
        if (empty($input['app_id'])) {
            error_response('App ID is required');
        }
        
        if (empty($input['type']) || !in_array($input['type'], ['link', 'note'])) {
            error_response('Type must be "link" or "note"');
        }
        
        if (empty($input['title'])) {
            error_response('Title is required');
        }
        
        $appId = $input['app_id'];
        
        // Verify user has access to app
        if (!can_access_app($appId)) {
            error_response('No tienes acceso a esta aplicación', 403);
        }
        
        // Validate content for links
        if ($input['type'] === 'link' && empty($input['content'])) {
            error_response('URL is required for links');
        }
        
        // Validate URL format for links
        if ($input['type'] === 'link' && !filter_var($input['content'], FILTER_VALIDATE_URL)) {
            error_response('Invalid URL format');
        }
        
        $stmt = $db->prepare("
            INSERT INTO app_resources (app_id, type, title, content, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $appId,
            $input['type'],
            $input['title'],
            $input['content'] ?? '',
            $user['id']
        ]);
        
        $newId = $db->lastInsertId();
        
        // Fetch the created resource
        $stmt = $db->prepare("
            SELECT 
                ar.*,
                u.username as created_by_username,
                u.full_name as created_by_name
            FROM app_resources ar
            LEFT JOIN users u ON ar.created_by = u.id
            WHERE ar.id = ?
        ");
        $stmt->execute([$newId]);
        $resource = $stmt->fetch(PDO::FETCH_ASSOC);
        
        success_response($resource, $input['type'] === 'link' ? 'Enlace añadido' : 'Nota añadida');
        break;
        
    case 'PUT':
        // Update resource
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('Resource ID required');
        }
        
        // Get resource info
        $stmt = $db->prepare("SELECT * FROM app_resources WHERE id = ?");
        $stmt->execute([$input['id']]);
        $resource = $stmt->fetch();
        
        if (!$resource) {
            error_response('Recurso no encontrado', 404);
        }
        
        // Verify user has access to app
        if (!can_access_app($resource['app_id'])) {
            error_response('No tienes acceso a esta aplicación', 403);
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        if (isset($input['title'])) {
            $updates[] = 'title = ?';
            $params[] = $input['title'];
        }
        
        if (isset($input['content'])) {
            // Validate URL for links
            if ($resource['type'] === 'link' && !filter_var($input['content'], FILTER_VALIDATE_URL)) {
                error_response('Invalid URL format');
            }
            $updates[] = 'content = ?';
            $params[] = $input['content'];
        }
        
        if (empty($updates)) {
            error_response('No fields to update');
        }
        
        $params[] = $input['id'];
        
        $stmt = $db->prepare("UPDATE app_resources SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        success_response(null, 'Recurso actualizado');
        break;
        
    case 'DELETE':
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('Resource ID required');
        }
        
        // Get resource info
        $stmt = $db->prepare("SELECT * FROM app_resources WHERE id = ?");
        $stmt->execute([$input['id']]);
        $resource = $stmt->fetch();
        
        if (!$resource) {
            error_response('Recurso no encontrado', 404);
        }
        
        // Verify user has access to app
        if (!can_access_app($resource['app_id'])) {
            error_response('No tienes acceso a esta aplicación', 403);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM app_resources WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        success_response(null, $resource['type'] === 'link' ? 'Enlace eliminado' : 'Nota eliminada');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
