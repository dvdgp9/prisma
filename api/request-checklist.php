<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (empty($_GET['request_id'])) {
            error_response('Request ID is required');
        }

        $requestId = (int) $_GET['request_id'];
        require_request_capability($requestId, 'view');

        $stmt = $db->prepare('
            SELECT rci.*, u.username as creator_username, u.full_name as creator_name
            FROM request_checklist_items rci
            LEFT JOIN users u ON rci.created_by = u.id
            WHERE rci.request_id = ?
            ORDER BY rci.position ASC, rci.id ASC
        ');
        $stmt->execute([$requestId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        success_response($items);
        break;

    case 'POST':
        $input = get_json_input();
        if (empty($input['request_id']) || empty(trim($input['title'] ?? ''))) {
            error_response('Request ID and title are required');
        }

        $requestId = (int) $input['request_id'];
        $title = trim($input['title']);
        require_request_capability($requestId, 'checklist');

        if (mb_strlen($title) > 500) {
            error_response('La subtarea no puede superar los 500 caracteres');
        }

        $stmt = $db->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM request_checklist_items WHERE request_id = ?');
        $stmt->execute([$requestId]);
        $position = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('
            INSERT INTO request_checklist_items (request_id, title, is_completed, position, created_by)
            VALUES (?, ?, 0, ?, ?)
        ');
        $stmt->execute([$requestId, $title, $position, $user['id']]);

        success_response(['id' => $db->lastInsertId()], 'Checklist item created');
        break;

    case 'PUT':
        $input = get_json_input();
        if (empty($input['id'])) {
            error_response('Checklist item ID is required');
        }

        $stmt = $db->prepare('
            SELECT rci.*
            FROM request_checklist_items rci
            WHERE rci.id = ?
        ');
        $stmt->execute([(int) $input['id']]);
        $item = $stmt->fetch();
        if (!$item) {
            error_response('Checklist item not found', 404);
        }

        require_request_capability((int) $item['request_id'], 'checklist');

        $fields = [];
        $values = [];

        if (isset($input['title'])) {
            $title = trim($input['title']);
            if ($title === '' || mb_strlen($title) > 500) {
                error_response('La subtarea debe tener entre 1 y 500 caracteres');
            }
            $fields[] = 'title = ?';
            $values[] = $title;
        }

        if (isset($input['is_completed'])) {
            $fields[] = 'is_completed = ?';
            $values[] = $input['is_completed'] ? 1 : 0;
        }

        if (isset($input['position'])) {
            $fields[] = 'position = ?';
            $values[] = (int) $input['position'];
        }

        if (empty($fields)) {
            error_response('No fields to update');
        }

        $values[] = (int) $input['id'];
        $stmt = $db->prepare('UPDATE request_checklist_items SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($values);

        success_response(['id' => (int) $input['id']], 'Checklist item updated');
        break;

    case 'DELETE':
        $input = get_json_input();
        if (empty($input['id'])) {
            error_response('Checklist item ID is required');
        }

        $stmt = $db->prepare('
            SELECT rci.id, rci.request_id
            FROM request_checklist_items rci
            WHERE rci.id = ?
        ');
        $stmt->execute([(int) $input['id']]);
        $item = $stmt->fetch();
        if (!$item) {
            error_response('Checklist item not found', 404);
        }

        require_request_capability((int) $item['request_id'], 'checklist');

        $stmt = $db->prepare('DELETE FROM request_checklist_items WHERE id = ?');
        $stmt->execute([(int) $input['id']]);

        success_response([], 'Checklist item deleted');
        break;

    default:
        error_response('Method not allowed', 405);
}
