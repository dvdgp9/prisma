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

        $requestId = $_GET['request_id'];
        require_request_capability($requestId, 'view');

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
            $stmt->execute([$requestId]);
            $assignments = $stmt->fetchAll();

            success_response($assignments);
        } catch (Exception $e) {
            error_response('Failed to fetch assignments: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Set assignments for a request (replace all)
        $input = get_json_input();

        if (empty($input['request_id'])) {
            error_response('Request ID is required');
        }

        $requestId = $input['request_id'];
        $requestScope = require_request_capability($requestId, 'edit');

        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $input['user_ids'] ?? []),
            static function ($userId) {
                return $userId > 0;
            }
        )));

        try {
            if (!empty($userIds)) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $params = [
                    $requestScope['company_id'],
                    $requestScope['company_id'],
                    $requestScope['app_id']
                ];
                $params = array_merge($params, $userIds);

                $stmtEligible = $db->prepare("
                    SELECT DISTINCT u.id
                    FROM users u
                    WHERE u.is_active = 1
                      AND (
                            u.role = 'superadmin'
                            OR (
                                (
                                    (
                                        EXISTS (SELECT 1 FROM user_companies uc_any WHERE uc_any.user_id = u.id)
                                        AND EXISTS (
                                            SELECT 1 FROM user_companies uc
                                            WHERE uc.user_id = u.id AND uc.company_id = ?
                                        )
                                    )
                                    OR (
                                        NOT EXISTS (SELECT 1 FROM user_companies uc_any WHERE uc_any.user_id = u.id)
                                        AND u.company_id = ?
                                    )
                                )
                                AND (
                                    NOT EXISTS (
                                        SELECT 1 FROM user_app_permissions uap_any
                                        WHERE uap_any.user_id = u.id
                                    )
                                    OR EXISTS (
                                        SELECT 1 FROM user_app_permissions uap
                                        WHERE uap.user_id = u.id
                                          AND uap.app_id = ?
                                          AND uap.can_view = 1
                                    )
                                )
                            )
                      )
                      AND u.id IN ($placeholders)
                ");
                $stmtEligible->execute($params);
                $eligibleIds = array_map('intval', $stmtEligible->fetchAll(PDO::FETCH_COLUMN));

                if (count($eligibleIds) !== count($userIds)) {
                    error_response('Uno o más usuarios no tienen acceso a esta petición', 403);
                }
            }

            $db->beginTransaction();

            // Get current assignments for notification comparison
            $stmt = $db->prepare("SELECT user_id FROM request_assignments WHERE request_id = ?");
            $stmt->execute([$requestId]);
            $oldAssignedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Remove existing assignments
            $stmt = $db->prepare("DELETE FROM request_assignments WHERE request_id = ?");
            $stmt->execute([$requestId]);

            // Add new assignments
            foreach ($userIds as $userId) {
                $stmt = $db->prepare("INSERT INTO request_assignments (request_id, user_id) VALUES (?, ?)");
                $stmt->execute([$requestId, $userId]);

                // Notify newly assigned users (not already assigned, not self)
                if (!in_array($userId, $oldAssignedIds) && $userId != $user['id']) {
                    $stmtNotif = $db->prepare("
                        INSERT INTO notifications (user_id, type, request_id, triggered_by, message)
                        VALUES (?, 'assignment', ?, ?, ?)
                    ");
                    $stmtNotif->execute([
                        $userId,
                        $requestId,
                        $user['id'],
                        $user['full_name'] . ' te ha asignado una tarea'
                    ]);
                }
            }

            // Also update the legacy assigned_to field (first user or null)
            $assignedTo = !empty($userIds) ? $userIds[0] : null;
            $stmt = $db->prepare("UPDATE requests SET assigned_to = ? WHERE id = ?");
            $stmt->execute([$assignedTo, $requestId]);

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
