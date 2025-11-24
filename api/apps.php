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
$user = get_current_user();

switch ($method) {
    case 'GET':
        // Get all apps
        try {
            $stmt = $db->query("
                SELECT id, name, description, is_active, created_at, updated_at
                FROM apps 
                WHERE is_active = 1
                ORDER BY name ASC
            ");
            $apps = $stmt->fetchAll();

            success_response($apps);
        } catch (Exception $e) {
            error_response('Failed to fetch apps: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Create new app (superadmin only)
        require_role('superadmin');

        $input = get_json_input();

        if (empty($input['name'])) {
            error_response('App name is required');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO apps (name, description) 
                VALUES (?, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['description'] ?? null
            ]);

            $app_id = $db->lastInsertId();

            success_response(['id' => $app_id], 'App created successfully');
        } catch (Exception $e) {
            error_response('Failed to create app: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update app (superadmin only)
        require_role('superadmin');

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
