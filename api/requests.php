<?php
/**
 * Requests API - CRUD operations for feature requests and bug reports
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
        // Get requests with filtering and sorting
        try {
            $where = ['1=1'];
            $params = [];

            // Filter by ID (single request)
            if (!empty($_GET['id'])) {
                $where[] = 'r.id = ?';
                $params[] = $_GET['id'];
            }

            // Filter by app
            if (!empty($_GET['app_id'])) {
                $where[] = 'r.app_id = ?';
                $params[] = $_GET['app_id'];
            }

            // Filter by company (non-superadmins only see their company's requests)
            if ($user['role'] !== 'superadmin') {
                $where[] = 'a.company_id = ?';
                $params[] = $user['company_id'];
            }

            // Filter by priority
            if (!empty($_GET['priority'])) {
                $where[] = 'r.priority = ?';
                $params[] = $_GET['priority'];
            }

            // Filter by status
            if (!empty($_GET['status'])) {
                $where[] = 'r.status = ?';
                $params[] = $_GET['status'];
            }

            // Sorting
            $sort = 'r.created_at DESC'; // Default sort
            if (!empty($_GET['sort'])) {
                switch ($_GET['sort']) {
                    case 'date':
                        $sort = 'r.created_at DESC';
                        break;
                    case 'date_asc':
                        $sort = 'r.created_at ASC';
                        break;
                    case 'priority':
                        // Order by priority: critical > high > medium > low
                        $sort = "CASE r.priority 
                                    WHEN 'critical' THEN 1 
                                    WHEN 'high' THEN 2 
                                    WHEN 'medium' THEN 3 
                                    WHEN 'low' THEN 4 
                                    ELSE 5 
                                END, r.created_at DESC";
                        break;
                    case 'votes':
                        $sort = 'r.vote_count DESC, r.created_at DESC';
                        break;
                    default:
                        $sort = 'r.created_at DESC';
                        break;
                }
            }

            $query = "
                SELECT 
                    r.*,
                    a.name as app_name,
                    COALESCE(u.username, r.requester_name, 'Anónimo') as creator_username,
                    COALESCE(u.full_name, r.requester_name, 'Anónimo') as creator_name,
                    (SELECT COUNT(*) FROM attachments WHERE request_id = r.id) as attachment_count
                FROM requests r
                INNER JOIN apps a ON r.app_id = a.id
                LEFT JOIN users u ON r.created_by = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$sort}
            ";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll();

            success_response($requests);
        } catch (Exception $e) {
            error_response('Failed to fetch requests: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Create new request
        $input = get_json_input();

        if (empty($input['app_id']) || empty($input['title'])) {
            error_response('App ID and title are required');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO requests (app_id, title, description, priority, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $input['app_id'],
                $input['title'],
                $input['description'] ?? null,
                $input['priority'] ?? 'medium',
                $input['status'] ?? 'pending',
                $user['id']
            ]);

            $request_id = $db->lastInsertId();

            success_response(['id' => $request_id], 'Request created successfully');
        } catch (Exception $e) {
            error_response('Failed to create request: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update request
        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('Request ID is required');
        }

        // Check if user has permission (must be admin/superadmin)
        if (!has_role('admin') && !has_role('superadmin')) {
            error_response('Unauthorized', 403);
        }

        try {
            $fields = [];
            $values = [];

            // Get current request state for email notifications
            $stmt = $db->prepare("SELECT status, is_public_request FROM requests WHERE id = ?");
            $stmt->execute([$input['id']]);
            $old_request = $stmt->fetch();
            $old_status = $old_request['status'] ?? null;
            $is_public = $old_request['is_public_request'] ?? 0;

            // Title
            if (isset($input['title'])) {
                $fields[] = 'title = ?';
                $values[] = $input['title'];
            }

            // Description
            if (isset($input['description'])) {
                $fields[] = 'description = ?';
                $values[] = $input['description'];
            }

            // Priority
            if (isset($input['priority'])) {
                $fields[] = 'priority = ?';
                $values[] = $input['priority'];
            }

            // Status with completed_at handling
            if (isset($input['status'])) {
                $fields[] = 'status = ?';
                $values[] = $input['status'];

                // Set completed_at when marking as completed or discarded
                if (in_array($input['status'], ['completed', 'discarded'])) {
                    $fields[] = 'completed_at = NOW()';
                }
                // Reset completed_at when reopening
                elseif (
                    in_array($input['status'], ['pending', 'in_progress']) &&
                    in_array($old_status, ['completed', 'discarded'])
                ) {
                    $fields[] = 'completed_at = NULL';
                }
            }

            if (empty($fields)) {
                error_response('No fields to update');
            }

            $values[] = $input['id'];

            $stmt = $db->prepare("
                UPDATE requests 
                SET " . implode(', ', $fields) . "
                WHERE id = ?
            ");

            $stmt->execute($values);

            // Send email notifications for public requests on status change
            if ($is_public && isset($input['status']) && $old_status !== $input['status']) {
                try {
                    require_once __DIR__ . '/../includes/email.php';

                    // Only send email when completed
                    if ($input['status'] === 'completed') {
                        sendRequestCompletedEmail($input['id']);
                    }
                } catch (Exception $e) {
                    error_log('Failed to send status change email: ' . $e->getMessage());
                }
            }

            success_response(['id' => $input['id']]);
        } catch (Exception $e) {
            error_response('Failed to update request: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Delete request (superadmin only)
        require_role('superadmin');

        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('Request ID is required');
        }

        try {
            $stmt = $db->prepare("DELETE FROM requests WHERE id = ?");
            $stmt->execute([$input['id']]);

            success_response([], 'Request deleted successfully');
        } catch (Exception $e) {
            error_response('Failed to delete request: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
