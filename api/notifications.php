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
            $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 50;
            $scopeSql = '';
            $scopeParams = [];

            if ($user['role'] !== 'superadmin') {
                $allowedAppIds = array_map('intval', array_column(get_user_apps(), 'id'));
                if (empty($allowedAppIds)) {
                    success_response([
                        'notifications' => [],
                        'unread_count' => 0
                    ]);
                }

                $placeholders = implode(',', array_fill(0, count($allowedAppIds), '?'));
                $scopeSql = " AND r.app_id IN ($placeholders)";
                $scopeParams = $allowedAppIds;
            }

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
                $scopeSql
                ORDER BY n.created_at DESC
                LIMIT {$limit}
            ");
            $stmt->execute(array_merge([$user['id']], $scopeParams));
            $notifications = $stmt->fetchAll();

            // Keep the badge count aligned with the same current access scope.
            $stmtCount = $db->prepare("
                SELECT COUNT(*) as unread
                FROM notifications n
                INNER JOIN requests r ON n.request_id = r.id
                WHERE n.user_id = ? AND n.is_read = 0
                $scopeSql
            ");
            $stmtCount->execute(array_merge([$user['id']], $scopeParams));
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
