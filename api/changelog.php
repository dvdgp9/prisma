<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    $user = get_logged_user();

    if ($method === 'GET') {
        // Get completed requests for changelog
        $appId = $_GET['app_id'] ?? null;
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;
        $days = $_GET['days'] ?? 30; // Default to last 30 days

        // Build the query
        $query = "
            SELECT 
                r.id,
                r.app_id,
                r.title,
                r.description,
                r.priority,
                r.status,
                r.completed_at,
                r.created_at,
                r.updated_at,
                r.requester_name,
                r.requester_email,
                a.name as app_name,
                u.username as creator_username,
                u.full_name as creator_full_name
            FROM requests r
            INNER JOIN apps a ON r.app_id = a.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.status = 'completed'
        ";

        $params = [];

        // Filter by app
        if ($appId) {
            $query .= " AND r.app_id = :app_id";
            $params[':app_id'] = $appId;
        }

        // Filter by date range
        if ($dateFrom && $dateTo) {
            $query .= " AND r.completed_at BETWEEN :date_from AND :date_to";
            $params[':date_from'] = $dateFrom . ' 00:00:00';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        } elseif ($days) {
            $query .= " AND r.completed_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            $params[':days'] = (int) $days;
        }

        // Order by completion date (most recent first)
        $query .= " ORDER BY r.completed_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($requests as &$request) {
            $request['completed_at_formatted'] = $request['completed_at']
                ? date('Y-m-d H:i:s', strtotime($request['completed_at']))
                : null;

            // Calculate days ago
            if ($request['completed_at']) {
                $completedDate = new DateTime($request['completed_at']);
                $now = new DateTime();
                $interval = $now->diff($completedDate);
                $request['days_ago'] = $interval->days;
            } else {
                $request['days_ago'] = null;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $requests,
            'count' => count($requests)
        ]);
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ]);
    }
} catch (Exception $e) {
    error_log("Changelog API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
