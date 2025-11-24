<?php
/**
 * User App Permissions API - Manage which users can access which apps
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Require admin or superadmin
require_role('admin');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        // Get permissions for a user
        if (empty($_GET['user_id'])) {
            error_response('User ID is required');
        }

        try {
            $stmt = $db->prepare("
                SELECT 
                    a.id,
                    a.name,
                    a.description,
                    COALESCE(uap.can_view, 0) as can_view,
                    COALESCE(uap.can_create, 0) as can_create,
                    COALESCE(uap.can_edit, 0) as can_edit
                FROM apps a
                LEFT JOIN user_app_permissions uap ON a.id = uap.app_id AND uap.user_id = ?
                WHERE a.is_active = 1
                ORDER BY a.name
            ");
            $stmt->execute([$_GET['user_id']]);
            $permissions = $stmt->fetchAll();

            success_response($permissions);
        } catch (Exception $e) {
            error_response('Failed to fetch permissions: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Grant or update permission
        $input = get_json_input();

        if (empty($input['user_id']) || empty($input['app_id'])) {
            error_response('User ID and App ID are required');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO user_app_permissions (user_id, app_id, can_view, can_create, can_edit)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    can_view = VALUES(can_view),
                    can_create = VALUES(can_create),
                    can_edit = VALUES(can_edit)
            ");

            $stmt->execute([
                $input['user_id'],
                $input['app_id'],
                $input['can_view'] ?? true,
                $input['can_create'] ?? true,
                $input['can_edit'] ?? false
            ]);

            success_response([], 'Permissions updated successfully');
        } catch (Exception $e) {
            error_response('Failed to update permissions: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Revoke permission
        $input = get_json_input();

        if (empty($input['user_id']) || empty($input['app_id'])) {
            error_response('User ID and App ID are required');
        }

        try {
            $stmt = $db->prepare("DELETE FROM user_app_permissions WHERE user_id = ? AND app_id = ?");
            $stmt->execute([$input['user_id'], $input['app_id']]);

            success_response([], 'Permission revoked successfully');
        } catch (Exception $e) {
            error_response('Failed to revoke permission: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
