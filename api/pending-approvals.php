<?php
/**
 * Pending Approvals API - Manage public request approvals
 * Admin/Superadmin only
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Must be admin or superadmin
if (!has_role('admin') && !has_role('superadmin')) {
    error_response('Unauthorized', 403);
}

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get pending approval requests
        try {
            $where = ["r.is_public_request = 1", "r.status = 'pending_approval'"];
            $params = [];

            // Non-superadmins only see their company's requests
            if ($user['role'] !== 'superadmin') {
                $where[] = 'a.company_id = ?';
                $params[] = $user['company_id'];
            }

            $query = "
                SELECT 
                    r.*,
                    a.name as app_name,
                    a.company_id
                FROM requests r
                INNER JOIN apps a ON r.app_id = a.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY r.created_at DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll();

            // Add user voted status for each request
            foreach ($requests as &$request) {
                $stmt = $db->prepare("SELECT id FROM votes WHERE request_id = ? AND user_id = ?");
                $stmt->execute([$request['id'], $user['id']]);
                $request['user_voted'] = $stmt->fetch() !== false;
            }

            success_response($requests);

        } catch (Exception $e) {
            error_response('Error loading pending requests: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Approve or reject a request
        $input = get_json_input();

        if (empty($input['request_id']) || empty($input['action'])) {
            error_response('Request ID and action required');
        }

        if (!in_array($input['action'], ['approve', 'reject'])) {
            error_response('Invalid action');
        }

        try {
            $db->beginTransaction();

            // Get request details
            $stmt = $db->prepare("
                SELECT r.*, a.company_id 
                FROM requests r
                INNER JOIN apps a ON r.app_id = a.id
                WHERE r.id = ? AND r.is_public_request = 1 AND r.status = 'pending_approval'
            ");
            $stmt->execute([$input['request_id']]);
            $request = $stmt->fetch();

            if (!$request) {
                $db->rollBack();
                error_response('Request not found or already processed', 404);
            }

            // Check permissions (non-superadmins can only approve their company's requests)
            if ($user['role'] !== 'superadmin' && $request['company_id'] != $user['company_id']) {
                $db->rollBack();
                error_response('Unauthorized - not your company', 403);
            }

            if ($input['action'] === 'approve') {
                // Approve: Change status to pending and set approved_by
                $stmt = $db->prepare("
                    UPDATE requests 
                    SET status = 'pending', approved_by = ?, approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$user['id'], $input['request_id']]);

                $message = 'Request approved successfully';
            } else {
                // Reject: Delete the request
                $stmt = $db->prepare("DELETE FROM requests WHERE id = ?");
                $stmt->execute([$input['request_id']]);

                $message = 'Request rejected and deleted';
            }

            $db->commit();
            success_response([], $message);

        } catch (Exception $e) {
            $db->rollBack();
            error_response('Error processing request: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
