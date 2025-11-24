<?php
/**
 * Profile API - Update user profile
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Must be logged in
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        // Get current user profile
        try {
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.email, u.full_name, u.role, 
                       u.company_id, c.name as company_name
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE u.id = ?
            ");
            $stmt->execute([$user['id']]);
            $profile = $stmt->fetch();

            success_response($profile);
        } catch (Exception $e) {
            error_response('Failed to fetch profile: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update profile
        $input = get_json_input();

        try {
            $updates = [];
            $values = [];

            // Username
            if (isset($input['username']) && !empty($input['username'])) {
                $updates[] = 'username = ?';
                $values[] = $input['username'];
            }

            // Email
            if (isset($input['email'])) {
                $updates[] = 'email = ?';
                $values[] = $input['email'] ?: null;
            }

            // Full name
            if (isset($input['full_name'])) {
                $updates[] = 'full_name = ?';
                $values[] = $input['full_name'] ?: null;
            }

            // Password (only if provided)
            if (isset($input['password']) && !empty($input['password'])) {
                $updates[] = 'password = ?';
                $values[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                error_response('No fields to update');
            }

            $values[] = $user['id'];

            $stmt = $db->prepare("
                UPDATE users 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($values);

            // Update session data
            if (isset($input['username'])) {
                $_SESSION['username'] = $input['username'];
            }
            if (isset($input['full_name'])) {
                $_SESSION['full_name'] = $input['full_name'];
            }

            success_response([], 'Perfil actualizado correctamente');
        } catch (Exception $e) {
            error_response('Failed to update profile: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
