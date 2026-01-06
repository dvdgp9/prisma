<?php
/**
 * Users API - CRUD operations for user management (admin/superadmin)
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Admin or superadmin required
require_role('admin');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        // Get all users (superadmin sees all, admin sees their company)
        try {
            if ($user['role'] === 'superadmin') {
                $stmt = $db->query("
                    SELECT 
                        u.id, u.username, u.full_name, u.email, u.role, 
                        u.is_active, u.company_id, u.created_at,
                        c.name as company_name
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    ORDER BY u.created_at DESC
                ");
            } else {
                $stmt = $db->prepare("
                    SELECT 
                        u.id, u.username, u.full_name, u.email, u.role, 
                        u.is_active, u.company_id, u.created_at,
                        c.name as company_name
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    WHERE u.company_id = ?
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute([$user['company_id']]);
            }

            $users = $stmt->fetchAll();

            // Add app permissions to each user
            foreach ($users as &$u) {
                $stmtPerms = $db->prepare("SELECT app_id FROM user_app_permissions WHERE user_id = ? AND can_view = 1");
                $stmtPerms->execute([$u['id']]);
                $u['app_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
            }

            success_response($users);
        } catch (Exception $e) {
            error_response('Failed to fetch users: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Create new user
        $input = get_json_input();

        if (empty($input['username']) || empty($input['password']) || empty($input['company_id'])) {
            error_response('Username, password and company are required');
        }

        // Admins can only create users in their own company
        if ($user['role'] === 'admin' && $input['company_id'] != $user['company_id']) {
            error_response('You can only create users in your own company', 403);
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO users (username, password, full_name, email, role, company_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['username'],
                password_hash($input['password'], PASSWORD_DEFAULT),
                $input['full_name'] ?? null,
                $input['email'] ?? null,
                $input['role'] ?? 'user',
                $input['company_id'],
                $input['is_active'] ?? 1
            ]);

            $user_id = $db->lastInsertId();

            // Save app permissions
            if (isset($input['app_permissions']) && is_array($input['app_permissions'])) {
                foreach ($input['app_permissions'] as $app_id) {
                    $stmtPerms = $db->prepare("INSERT INTO user_app_permissions (user_id, app_id, can_view) VALUES (?, ?, 1)");
                    $stmtPerms->execute([$user_id, $app_id]);
                }
            }

            success_response(['id' => $user_id], 'User created successfully');
        } catch (Exception $e) {
            error_response('Failed to create user: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update user
        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('User ID is required');
        }

        try {
            // Check if user is in same company (for admins)
            if ($user['role'] === 'admin') {
                $stmt = $db->prepare("SELECT company_id FROM users WHERE id = ?");
                $stmt->execute([$input['id']]);
                $target_user = $stmt->fetch();

                if ($target_user['company_id'] != $user['company_id']) {
                    error_response('You can only edit users in your own company', 403);
                }
            }

            $updates = [];
            $values = [];

            if (isset($input['username'])) {
                $updates[] = 'username = ?';
                $values[] = $input['username'];
            }

            if (isset($input['full_name'])) {
                $updates[] = 'full_name = ?';
                $values[] = $input['full_name'];
            }

            if (isset($input['email'])) {
                $updates[] = 'email = ?';
                $values[] = $input['email'];
            }

            if (isset($input['role']) && $user['role'] === 'superadmin') {
                $updates[] = 'role = ?';
                $values[] = $input['role'];
            }

            if (isset($input['company_id']) && $user['role'] === 'superadmin') {
                $updates[] = 'company_id = ?';
                $values[] = $input['company_id'];
            }

            if (isset($input['is_active'])) {
                $updates[] = 'is_active = ?';
                $values[] = $input['is_active'] ? 1 : 0;
            }

            if (isset($input['password']) && !empty($input['password'])) {
                $updates[] = 'password = ?';
                $values[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                error_response('No fields to update');
            }

            $values[] = $input['id'];

            $stmt = $db->prepare("
                UPDATE users 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($values);

            // Update app permissions
            if (isset($input['app_permissions']) && is_array($input['app_permissions'])) {
                // Remove existing permissions
                $stmtDel = $db->prepare("DELETE FROM user_app_permissions WHERE user_id = ?");
                $stmtDel->execute([$input['id']]);

                // Add new permissions
                foreach ($input['app_permissions'] as $app_id) {
                    $stmtIns = $db->prepare("INSERT INTO user_app_permissions (user_id, app_id, can_view) VALUES (?, ?, 1)");
                    $stmtIns->execute([$input['id'], $app_id]);
                }
            }

            success_response([], 'User updated successfully');
        } catch (Exception $e) {
            error_response('Failed to update user: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Delete user (superadmin only)
        require_role('superadmin');

        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('User ID is required');
        }

        try {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$input['id']]);

            success_response([], 'User deleted successfully');
        } catch (Exception $e) {
            error_response('Failed to delete user: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
