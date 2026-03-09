<?php
/**
 * Notifications API - Get and manage user notifications
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

require_login();

$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $stmt = $db->prepare("
                SELECT 
                    n.*,
                    r.title as request_title,
                    trigger_user.username as triggered_by_username,
                    trigger_user.full_name as triggered_by_name
                FROM notifications n
                INNER JOIN requests r ON n.request_id = r.id
                INNER JOIN users trigger_user ON n.triggered_by = trigger_user.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user['id'], $limit]);
            $notifications = $stmt->fetchAll();

            // Count unread
            $stmtCount = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmtCount->execute([$user['id']]);
            $unread = $stmtCount->fetch()['unread'];

            success_response([
                'notifications' => $notifications,
                'unread_count' => (int)$unread
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
                $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([$input['id'], $user['id']]);
            }
            
            success_response([], 'Notifications updated');
        } catch (Exception $e) {
            error_response('Failed to update notifications: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
