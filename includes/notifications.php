<?php
/**
 * In-app notifications: which ones each user can see in the inbox.
 *
 * Request notifications follow the user's app access; note notifications
 * follow access to the note (author, whole team of the company, or shared by
 * name). Functions take the PDO connection so tests can run against SQLite.
 */

/**
 * WHERE fragment + positional params limiting notifications to what the user
 * can still open. $allowed_app_ids: null = every app (superadmin), [] = none.
 */
function notification_visibility_sql($user, $allowed_app_ids)
{
    $params = [];
    if ($allowed_app_ids === null) {
        $requestScope = 'r.id IS NOT NULL';
    } elseif (empty($allowed_app_ids)) {
        $requestScope = '0 = 1';
    } else {
        $requestScope = 'r.app_id IN (' . implode(',', array_fill(0, count($allowed_app_ids), '?')) . ')';
        $params = array_map('intval', $allowed_app_ids);
    }

    $sql = "(
        (n.request_id IS NOT NULL AND {$requestScope})
        OR (n.note_id IS NOT NULL AND nt.id IS NOT NULL AND (
            nt.user_id = ?
            OR (nt.is_shared = 1 AND nt.company_id = ?)
            OR EXISTS (SELECT 1 FROM note_shares ns WHERE ns.note_id = nt.id AND ns.user_id = ?)
        ))
    )";
    $params = array_merge($params, [(int) $user['id'], (int) $user['company_id'], (int) $user['id']]);

    return ['sql' => $sql, 'params' => $params];
}

function fetch_user_notifications($db, $user, $allowed_app_ids, $limit)
{
    $scope = notification_visibility_sql($user, $allowed_app_ids);
    $limit = max(1, min(100, (int) $limit));

    $stmt = $db->prepare("
        SELECT
            n.*,
            r.title AS request_title,
            nt.title AS note_title,
            trigger_user.username AS triggered_by_username,
            trigger_user.full_name AS triggered_by_name
        FROM notifications n
        LEFT JOIN requests r ON r.id = n.request_id
        LEFT JOIN notes nt ON nt.id = n.note_id
        INNER JOIN users trigger_user ON trigger_user.id = n.triggered_by
        WHERE n.user_id = ? AND {$scope['sql']}
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute(array_merge([(int) $user['id']], $scope['params']));

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Keeps the badge count aligned with the same access scope as the list.
function count_unread_notifications($db, $user, $allowed_app_ids)
{
    $scope = notification_visibility_sql($user, $allowed_app_ids);

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM notifications n
        LEFT JOIN requests r ON r.id = n.request_id
        LEFT JOIN notes nt ON nt.id = n.note_id
        WHERE n.user_id = ? AND n.is_read = 0 AND {$scope['sql']}
    ");
    $stmt->execute(array_merge([(int) $user['id']], $scope['params']));

    return (int) $stmt->fetchColumn();
}
