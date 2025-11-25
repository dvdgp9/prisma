<?php
/**
 * Public Request API - Handle external improvement requests
 * No authentication required - includes rate limiting
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Helper functions (since we can't use auth.php without session)
function error_response($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function success_response($data = [], $message = '')
{
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    error_response('Invalid JSON data');
}

// Validate required fields
$required = ['company_id', 'app_id', 'title', 'description', 'requester_name', 'requester_email'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        error_response("Campo requerido: {$field}");
    }
}

// Validate email format
if (!filter_var($input['requester_email'], FILTER_VALIDATE_EMAIL)) {
    error_response('Formato de correo electrónico inválido');
}

try {
    $db = getDB();
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    error_response('Error de conexión a la base de datos', 500);
}

// Get client IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
// Clean IP if it contains multiple addresses
$ip = explode(',', $ip)[0];

// Rate limiting check (5 requests per hour)
try {
    $db->beginTransaction();

    // Clean old entries (older than 1 hour)
    $stmt = $db->prepare("DELETE FROM public_request_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute();

    // Check current rate for this IP + company
    $stmt = $db->prepare("
        SELECT request_count, window_start 
        FROM public_request_limits 
        WHERE ip_address = ? AND company_id = ? 
        AND window_start > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$ip, $input['company_id']]);
    $limit = $stmt->fetch();

    if ($limit) {
        if ($limit['request_count'] >= 5) {
            $db->rollBack();
            error_response('Has alcanzado el límite de solicitudes (5 por hora). Intenta más tarde.', 429);
        }

        // Increment count
        $stmt = $db->prepare("
            UPDATE public_request_limits 
            SET request_count = request_count + 1 
            WHERE ip_address = ? AND company_id = ?
        ");
        $stmt->execute([$ip, $input['company_id']]);
    } else {
        // Create new limit entry
        $stmt = $db->prepare("
            INSERT INTO public_request_limits (ip_address, company_id, request_count, window_start) 
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([$ip, $input['company_id']]);
    }

    // Verify app belongs to company
    $stmt = $db->prepare("SELECT id FROM apps WHERE id = ? AND company_id = ?");
    $stmt->execute([$input['app_id'], $input['company_id']]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        error_response('La aplicación no pertenece a esta empresa', 400);
    }

    // Create request with "pending_approval" status
    error_log('Creating public request with data: ' . json_encode([
        'app_id' => $input['app_id'],
        'title' => $input['title'],
        'status' => 'pending_approval',
        'is_public_request' => 1
    ]));

    $stmt = $db->prepare("
        INSERT INTO requests (
            app_id, 
            title, 
            description, 
            priority, 
            status, 
            requester_name, 
            requester_email, 
            is_public_request
        ) VALUES (?, ?, ?, 'medium', 'pending_approval', ?, ?, 1)
    ");

    $stmt->execute([
        $input['app_id'],
        $input['title'],
        $input['description'],
        $input['requester_name'],
        $input['requester_email']
    ]);

    $request_id = $db->lastInsertId();
    error_log('Created request ID: ' . $request_id);

    $db->commit();

    success_response(['id' => $request_id], 'Solicitud enviada correctamente');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Public request error: ' . $e->getMessage());
    error_response('Error al procesar la solicitud: ' . $e->getMessage(), 500);
}
