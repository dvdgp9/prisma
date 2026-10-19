<?php
/**
 * Notifications API - Get and manage user notifications
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

require_login();

$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        try {
            $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 50;

            // Request notices follow the user's apps (superadmin: all); note notices
            // follow access to the note (see includes/notifications.php).
            $allowedAppIds = $user['role'] === 'superadmin'
                ? null
                : array_map('intval', array_column(get_user_apps(), 'id'));

            success_response([
                'notifications' => fetch_user_notifications($db, $user, $allowedAppIds, $limit),
                'unread_count' => count_unread_notifications($db, $user, $allowedAppIds)
            ]);
        } catch (Exception $e) {
            error_response('Failed to fetch notifications: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        $input = get_json_input();
        
        try {
            if (isset($input['mark_all_read']) && $input['mark_all_read']) {
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
                $stmt->execute([$user['id']]);
            } elseif (!empty($input['id'])) {
                // Allow toggling back to unread so the dot can act as a "pending" marker
                $isRead = array_key_exists('is_read', $input) ? ((int) (bool) $input['is_read']) : 1;
                $stmt = $db->prepare("UPDATE notifications SET is_read = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$isRead, $input['id'], $user['id']]);
            }
            
            success_response([], 'Notifications updated');
        } catch (Exception $e) {
            error_response('Failed to update notifications: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
