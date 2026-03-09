<?php
/**
 * Assignments API - Manage request assignments (multi-user)
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

require_login();

$db = getDB();
$user = get_logged_user();

switch ($method) {
    case 'GET':
        // Get assignments for a request
        if (empty($_GET['request_id'])) {
            error_response('Request ID is required');
        }

        try {
            $stmt = $db->prepare("
                SELECT 
                    ra.*,
                    u.username,
                    u.full_name
                FROM request_assignments ra
                INNER JOIN users u ON ra.user_id = u.id
                WHERE ra.request_id = ?
                ORDER BY ra.created_at ASC
            ");
            $stmt->execute([$_GET['request_id']]);
            $assignments = $stmt->fetchAll();

            success_response($assignments);
        } catch (Exception $e) {
            error_response('Failed to fetch assignments: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Set assignments for a request (replace all)
        if (!can_edit_requests()) {
            error_response('Unauthorized', 403);
        }

        $input = get_json_input();

        if (empty($input['request_id'])) {
            error_response('Request ID is required');
        }

        $userIds = $input['user_ids'] ?? [];

        try {
            $db->beginTransaction();

            // Get current assignments for notification comparison
            $stmt = $db->prepare("SELECT user_id FROM request_assignments WHERE request_id = ?");
            $stmt->execute([$input['request_id']]);
            $oldAssignedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Remove existing assignments
            $stmt = $db->prepare("DELETE FROM request_assignments WHERE request_id = ?");
            $stmt->execute([$input['request_id']]);

            // Add new assignments
            foreach ($userIds as $userId) {
                $stmt = $db->prepare("INSERT INTO request_assignments (request_id, user_id) VALUES (?, ?)");
                $stmt->execute([$input['request_id'], $userId]);

                // Notify newly assigned users (not already assigned, not self)
                if (!in_array($userId, $oldAssignedIds) && $userId != $user['id']) {
                    $stmtNotif = $db->prepare("
                        INSERT INTO notifications (user_id, type, request_id, triggered_by, message)
                        VALUES (?, 'assignment', ?, ?, ?)
                    ");
                    $stmtNotif->execute([
                        $userId,
                        $input['request_id'],
                        $user['id'],
                        $user['full_name'] . ' te ha asignado una tarea'
                    ]);
                }
            }

            // Also update the legacy assigned_to field (first user or null)
            $assignedTo = !empty($userIds) ? $userIds[0] : null;
            $stmt = $db->prepare("UPDATE requests SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$assignedTo, $input['request_id']]);

            $db->commit();

            success_response([], 'Assignments updated');
        } catch (Exception $e) {
            $db->rollBack();
            error_response('Failed to update assignments: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
