<?php
/**
 * Export requests to CSV
 * GET /api/export-requests.php?company_id=X
 */

require_once __DIR__ . '/../includes/auth.php';

// Require login
if (!is_logged_in()) {
    error_response('No autorizado', 401);
}

$user = get_logged_user();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    error_response('Método no permitido', 405);
}

$company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;

if (!$company_id) {
    error_response('Empresa no especificada', 400);
}

// Verify user has access to this company
$user_companies = get_user_companies();
$company_ids = array_column($user_companies, 'id');

if (!in_array($company_id, $company_ids)) {
    error_response('No tienes acceso a esta empresa', 403);
}

// Get company name for filename
$db = getDB();
$stmtCompany = $db->prepare("SELECT name FROM companies WHERE id = ?");
$stmtCompany->execute([$company_id]);
$company = $stmtCompany->fetch();

if (!$company) {
    error_response('Empresa no encontrada', 404);
}

// Get all requests for this company (pending and in_progress)
$stmt = $db->prepare("
    SELECT 
        a.name as app_name,
        r.title,
        r.priority,
        r.difficulty,
        r.status
    FROM requests r
    INNER JOIN apps a ON r.app_id = a.id
    WHERE a.company_id = ?
    AND r.status IN ('pending', 'in_progress')
    ORDER BY a.name, 
        CASE r.priority 
            WHEN 'critical' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
        END,
        r.created_at DESC
");
$stmt->execute([$company_id]);
$requests = $stmt->fetchAll();

// Priority labels
$priorityLabels = [
    'critical' => 'Crítica',
    'high' => 'Alta',
    'medium' => 'Media',
    'low' => 'Baja'
];

// Difficulty labels
$difficultyLabels = [
    'high' => 'Alta',
    'medium' => 'Media',
    'low' => 'Baja',
    '' => 'Sin definir',
    null => 'Sin definir'
];

// Generate CSV
$filename = 'mejoras_' . preg_replace('/[^a-zA-Z0-9]/', '_', $company['name']) . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['Aplicación', 'Título', 'Prioridad', 'Dificultad'], ';');

// Data rows
foreach ($requests as $request) {
    fputcsv($output, [
        $request['app_name'],
        $request['title'],
        $priorityLabels[$request['priority']] ?? $request['priority'],
        $difficultyLabels[$request['difficulty']] ?? 'Sin definir'
    ], ';');
}

fclose($output);
exit;
