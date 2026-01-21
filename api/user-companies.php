<?php
/**
 * User-Companies API - Manage user-company assignments
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

// Only superadmin can manage user-company assignments
if ($user['role'] !== 'superadmin') {
    error_response('No tienes permisos para esta acción', 403);
}

switch ($method) {
    case 'GET':
        // Get companies for a specific user
        $userId = $_GET['user_id'] ?? null;
        
        if ($userId) {
            $stmt = $db->prepare("
                SELECT c.*, uc.is_default
                FROM companies c
                INNER JOIN user_companies uc ON c.id = uc.company_id
                WHERE uc.user_id = ?
                ORDER BY c.name
            ");
            $stmt->execute([$userId]);
            success_response($stmt->fetchAll());
        } else {
            // Get all user-company assignments
            $stmt = $db->query("
                SELECT uc.*, u.username, u.full_name, c.name as company_name
                FROM user_companies uc
                INNER JOIN users u ON uc.user_id = u.id
                INNER JOIN companies c ON uc.company_id = c.id
                ORDER BY u.username, c.name
            ");
            success_response($stmt->fetchAll());
        }
        break;
        
    case 'POST':
        // Assign company to user
        $input = get_json_input();
        
        if (empty($input['user_id']) || empty($input['company_id'])) {
            error_response('user_id y company_id son requeridos');
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO user_companies (user_id, company_id, is_default)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE is_default = VALUES(is_default)
            ");
            $stmt->execute([
                $input['user_id'],
                $input['company_id'],
                $input['is_default'] ?? false
            ]);
            
            success_response(['id' => $db->lastInsertId()], 'Empresa asignada correctamente');
        } catch (PDOException $e) {
            error_response('Error al asignar empresa: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'DELETE':
        // Remove company from user
        $input = get_json_input();
        
        if (empty($input['user_id']) || empty($input['company_id'])) {
            error_response('user_id y company_id son requeridos');
        }
        
        $stmt = $db->prepare("
            DELETE FROM user_companies 
            WHERE user_id = ? AND company_id = ?
        ");
        $stmt->execute([$input['user_id'], $input['company_id']]);
        
        success_response(null, 'Empresa desasignada correctamente');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
