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
        // Get all requests (or filtered)
        $app_filter = $_GET['app'] ?? null;
        $priority_filter = $_GET['priority'] ?? null;
        $status_filter = $_GET['status'] ?? null;
        $sort = $_GET['sort'] ?? 'date_desc';
        
        // Build query
        $query = "
            SELECT 
                r.*,
                a.name as app_name,
                u.username as creator_username,
                u.full_name as creator_name,
                (SELECT COUNT(*) FROM votes WHERE request_id = r.id) as vote_count,
                EXISTS(SELECT 1 FROM votes WHERE request_id = r.id AND user_id = ?) as user_voted
            FROM requests r
            LEFT JOIN apps a ON r.app_id = a.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE 1=1
        ";
        
        $params = [$user['id']];
        
        // Company filter for non-superadmins
        if (!has_role('superadmin')) {
            $query .= " AND r.company_id = ?";
            $params[] = $user['company_id'];
        }
        
        // App filter
        if ($app_filter) {
            $query .= " AND r.app_id = ?";
            $params[] = $app_filter;
        }
        
        // Priority filter
        if ($priority_filter) {
            $query .= " AND r.priority = ?";
            $params[] = $priority_filter;
        }
        
        // Status filter
        if ($status_filter) {
            $query .= " AND r.status = ?";
            $params[] = $status_filter;
        }
        
        // Sorting
        switch ($sort) {
            case 'date_asc':
                $query .= " ORDER BY r.created_at ASC";
                break;
            case 'priority':
                $query .= " ORDER BY FIELD(r.priority, 'critical', 'high', 'medium', 'low')";
                break;
            case 'votes':
                $query .= " ORDER BY vote_count DESC";
                break;
            default:
                $query .= " ORDER BY r.created_at DESC";
        }
        
        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            success_response($results);
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
            $updates = [];
            $values = [];

            // Title
            if (isset($input['title'])) {
                $updates[] = 'title = ?';
                $values[] = $input['title'];
            }

            // Description
            if (isset($input['description'])) {
                $updates[] = 'description = ?';
                $values[] = $input['description'];
            }

            // Priority
            if (isset($input['priority'])) {
                $updates[] = 'priority = ?';
                $values[] = $input['priority'];
            }

            // Status - Handle completed_at timestamp
            if (isset($input['status'])) {
                $updates[] = 'status = ?';
                $values[] = $input['status'];

                // Set completed_at when marking as completed or discarded
                if ($input['status'] === 'completed' || $input['status'] === 'discarded') {
                    $updates[] = 'completed_at = NOW()';
                }
                // Reset completed_at when reopening task
                else if ($input['status'] === 'pending' || $input['status'] === 'in_progress') {
                    $updates[] = 'completed_at = NULL';
                }
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
