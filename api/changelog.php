<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

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
        $query .= " AND r.updated_at >= datetime('now', '-' || ? || ' days')";
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

    echo json_encode([
        'success' => true,
        'data' => $results
    ]);

} catch (PDOException $e) {
    error_log("Database error in changelog.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener el changelog'
    ]);
}
