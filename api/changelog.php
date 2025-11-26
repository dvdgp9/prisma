<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
require_login();

$db = getDB();
$user = get_logged_user();

try {
    // Get filters
    $app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : null;
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;

    // Build query
    $query = "
        SELECT 
            r.*,
            a.name as app_name,
            a.color as app_color,
            u.username as creator_username,
            u.full_name as creator_full_name,
            r.requester_name,
            r.requester_email,
            DATE(COALESCE(r.completed_at, r.updated_at)) as completion_date
        FROM requests r
        LEFT JOIN apps a ON r.app_id = a.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.status = 'completed'
    ";

    $params = [];

    // Filter by app
    if ($app_id) {
        $query .= " AND r.app_id = ?";
        $params[] = $app_id;
    }

    // Filter by date
    if ($days !== 0 && $days !== null) {
        $query .= " AND r.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $days;
    }

    $query .= " ORDER BY r.updated_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse files JSON
    foreach ($results as &$row) {
        if (isset($row['files']) && !empty($row['files'])) {
            $files = json_decode($row['files'], true);
            $row['files'] = is_array($files) ? $files : [];
        } else {
            $row['files'] = [];
        }
    }

    success_response($results);

} catch (Exception $e) {
    error_log("Database error in changelog.php: " . $e->getMessage());
    error_response('Error al obtener el changelog: ' . $e->getMessage(), 500);
}
