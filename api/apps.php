<?php
/**
 * Apps API - CRUD operations for apps/projects
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// All operations require login
require_login();

$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        // Get all apps (filtered by user permissions)
        try {
            // For superadmin in admin panel, return with company info
            if ($user['role'] === 'superadmin' && isset($_GET['with_company'])) {
                $stmt = $db->query("
                    SELECT a.*, c.name as company_name 
                    FROM apps a
                    LEFT JOIN companies c ON a.company_id = c.id
                    ORDER BY a.name
                ");
                success_response($stmt->fetchAll());
            } else {
                // Check if grouped view is requested
                $grouped = isset($_GET['grouped']) && $_GET['grouped'] === '1';
                $apps = get_user_apps($grouped);
                success_response($apps);
            }
        } catch (Exception $e) {
            error_response('Failed to fetch apps: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Create new app (admin or superadmin)
        require_role('admin');

        $input = get_json_input();

        if (empty($input['name'])) {
            error_response('App name is required');
        }

        try {
            // Admins create apps in their company, superadmins can choose
            $company_id = $user['role'] === 'superadmin' && isset($input['company_id'])
                ? $input['company_id']
                : $user['company_id'];

            $stmt = $db->prepare("
                INSERT INTO apps (name, description, company_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['description'] ?? null,
                $company_id
            ]);

            $app_id = $db->lastInsertId();

            success_response(['id' => $app_id], 'App created successfully');
        } catch (Exception $e) {
            error_response('Failed to create app: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update app (admin or superadmin)
        require_role('admin');

        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('App ID is required');
        }

        try {
            $updates = [];
            $values = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $values[] = $input['name'];
            }

            if (isset($input['description'])) {
                $updates[] = 'description = ?';
                $values[] = $input['description'];
            }

            if (isset($input['is_active'])) {
                $updates[] = 'is_active = ?';
                $values[] = $input['is_active'] ? 1 : 0;
            }

            if (empty($updates)) {
                error_response('No fields to update');
            }

            $values[] = $input['id'];

            $stmt = $db->prepare("
                UPDATE apps 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($values);

            success_response([], 'App updated successfully');
        } catch (Exception $e) {
            error_response('Failed to update app: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Delete app (superadmin only)
        require_role('superadmin');

        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('App ID is required');
        }

        try {
            $stmt = $db->prepare("DELETE FROM apps WHERE id = ?");
            $stmt->execute([$input['id']]);

            success_response([], 'App deleted successfully');
        } catch (Exception $e) {
            error_response('Failed to delete app: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
