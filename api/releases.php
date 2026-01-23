<?php
/**
 * Releases API - Scheduled releases management (SUPERADMIN ONLY)
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_role('superadmin');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get all scheduled releases
        $status = $_GET['status'] ?? null;
        $month = $_GET['month'] ?? null; // Format: YYYY-MM
        
        $query = "
            SELECT 
                r.*,
                a.name as app_name
            FROM scheduled_releases r
            LEFT JOIN apps a ON r.app_id = a.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($status) {
            $query .= " AND r.status = :status";
            $params[':status'] = $status;
        }
        
        if ($month) {
            $query .= " AND DATE_FORMAT(r.announce_at, '%Y-%m') = :month";
            $params[':month'] = $month;
        }
        
        $query .= " ORDER BY r.announce_at ASC, r.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $releases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        success_response($releases);
        break;
        
    case 'POST':
        // Create new release
        $input = get_json_input();
        
        if (empty($input['title'])) {
            error_response('El título es obligatorio');
        }
        if (empty($input['announce_at'])) {
            error_response('La fecha de anuncio es obligatoria');
        }
        
        $stmt = $db->prepare("
            INSERT INTO scheduled_releases 
            (title, description, internal_notes, link, announce_at, status, app_id)
            VALUES 
            (:title, :description, :internal_notes, :link, :announce_at, :status, :app_id)
        ");
        
        $stmt->execute([
            ':title' => trim($input['title']),
            ':description' => $input['description'] ?? null,
            ':internal_notes' => $input['internal_notes'] ?? null,
            ':link' => $input['link'] ?? null,
            ':announce_at' => $input['announce_at'],
            ':status' => $input['status'] ?? 'scheduled',
            ':app_id' => $input['app_id'] ?? null
        ]);
        
        $releaseId = $db->lastInsertId();
        
        success_response(['id' => $releaseId], 'Release programado');
        break;
        
    case 'PUT':
        // Update release
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('ID de release requerido');
        }
        
        // Verify exists
        $stmt = $db->prepare("SELECT * FROM scheduled_releases WHERE id = ?");
        $stmt->execute([$input['id']]);
        $release = $stmt->fetch();
        
        if (!$release) {
            error_response('Release no encontrado', 404);
        }
        
        // Build update query dynamically
        $updates = [];
        $params = [];
        
        if (isset($input['title'])) {
            $updates[] = "title = ?";
            $params[] = trim($input['title']);
        }
        if (isset($input['description'])) {
            $updates[] = "description = ?";
            $params[] = $input['description'];
        }
        if (isset($input['internal_notes'])) {
            $updates[] = "internal_notes = ?";
            $params[] = $input['internal_notes'];
        }
        if (isset($input['link'])) {
            $updates[] = "link = ?";
            $params[] = $input['link'];
        }
        if (isset($input['completed_at'])) {
            $updates[] = "completed_at = ?";
            $params[] = $input['completed_at'];
        }
        if (isset($input['announce_at'])) {
            $updates[] = "announce_at = ?";
            $params[] = $input['announce_at'];
        }
        if (isset($input['status'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
        }
        if (array_key_exists('app_id', $input)) {
            $updates[] = "app_id = ?";
            $params[] = $input['app_id'];
        }
        
        if (empty($updates)) {
            error_response('Nada que actualizar');
        }
        
        $params[] = $input['id'];
        $stmt = $db->prepare("UPDATE scheduled_releases SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        success_response(null, 'Release actualizado');
        break;
        
    case 'DELETE':
        // Delete release
        $input = get_json_input();
        
        if (empty($input['id'])) {
            error_response('ID de release requerido');
        }
        
        $stmt = $db->prepare("DELETE FROM scheduled_releases WHERE id = ?");
        $stmt->execute([$input['id']]);
        
        if ($stmt->rowCount() === 0) {
            error_response('Release no encontrado', 404);
        }
        
        success_response(null, 'Release eliminado');
        break;
        
    default:
        error_response('Method not allowed', 405);
}
