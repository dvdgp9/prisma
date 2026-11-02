<?php
/**
 * Shared persistence primitives. Callers must validate fields and capabilities.
 * No HTTP output, transaction ownership or notifications: these can participate
 * in a larger transaction (e.g. request + assignees + checklist in IA4).
 */
function create_request_record($db, $user, array $fields)
{
    $stmt = $db->prepare('INSERT INTO requests (
        app_id, title, description, priority, difficulty, status, created_by,
        requester_name, requester_email, due_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $fields['app_id'], $fields['title'], $fields['description'] ?? null,
        $fields['priority'] ?? 'medium', $fields['difficulty'] ?? null,
        $fields['status'] ?? 'pending', $user['id'],
        $fields['requester_name'] ?? null, $fields['requester_email'] ?? null,
        $fields['due_date'] ?? null
    ]);
    return (int) $db->lastInsertId();
}

function create_task_record($db, $user, array $fields)
{
    $stmt = $db->prepare('INSERT INTO tasks (user_id, company_id, app_id, title, description, due_date, is_shared)
        VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'], $user['company_id'], $fields['app_id'] ?? null,
        trim($fields['title']), $fields['description'] ?? null,
        $fields['due_date'] ?? null, empty($fields['is_shared']) ? 0 : 1
    ]);
    return (int) $db->lastInsertId();
}

function create_request_checklist_record($db, $user, $requestId, $title)
{
    $title = trim($title);
    if ($title === '' || mb_strlen($title) > 500) {
        throw new InvalidArgumentException('La subtarea debe tener entre 1 y 500 caracteres');
    }
    $stmt = $db->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM request_checklist_items WHERE request_id = ?');
    $stmt->execute([(int) $requestId]);
    $position = (int) $stmt->fetchColumn();
    $stmt = $db->prepare('INSERT INTO request_checklist_items (request_id, title, is_completed, position, created_by)
        VALUES (?, ?, 0, ?, ?)');
    $stmt->execute([(int) $requestId, $title, $position, $user['id']]);
    return (int) $db->lastInsertId();
}
