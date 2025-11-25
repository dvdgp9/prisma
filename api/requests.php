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

            $whereClause = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";
            $orderClause = "ORDER BY {$sort}";

            $query = "
                SELECT r.*,
                       a.name as app_name,
                       u.username as created_by,
                       u.full_name as creator_full_name,
                       u.email as creator_email,
                       COALESCE(v.user_voted, 0) as user_voted,
                       COALESCE(vote_counts.votes, 0) as votes
                FROM requests r
                INNER JOIN apps a ON r.app_id = a.id
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN votes v ON r.id = v.request_id AND v.user_id = ?
                LEFT JOIN (
                    SELECT request_id, SUM(value) as votes
                    FROM votes
                    GROUP BY request_id
                ) vote_counts ON r.id = vote_counts.request_id
                {$whereClause}
                {$orderClause}
            ";

            // Add user ID at the beginning for the votes LEFT JOIN
            array_unshift($params, $user['id']);

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

        try {
            // Check if user can modify this request
            $stmt = $db->prepare("SELECT created_by FROM requests WHERE id = ?");
            $stmt->execute([$input['id']]);
            $request = $stmt->fetch();

            if (!$request) {
                error_response('Request not found', 404);
            }

            if (!can_modify_request($request['created_by'])) {
                error_response('You do not have permission to modify this request', 403);
            }

            $updates = [];
            $values = [];

            if (isset($input['title'])) {
                $updates[] = 'title = ?';
                $values[] = $input['title'];
            }

            if (isset($input['description'])) {
                $updates[] = 'description = ?';
                $values[] = $input['description'];
            }

            if (isset($input['priority']) && has_role('admin')) {
                $updates[] = 'priority = ?';
                $values[] = $input['priority'];
            }

            if (isset($input['status']) && has_role('admin')) {
                $updates[] = 'status = ?';
                $values[] = $input['status'];
            }

            if (empty($updates)) {
                error_response('No fields to update');
            }

            $values[] = $input['id'];

            $stmt = $db->prepare("
                UPDATE requests 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($values);

            success_response([], 'Request updated successfully');
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
