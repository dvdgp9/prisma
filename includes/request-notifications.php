<?php

/**
 * Resolve internal users who should hear that a request was completed.
 *
 * Assignment history is already recorded in notifications. For each person
 * who remains assigned, the latest assignment event identifies the assigner
 * without requiring a database migration.
 */
function get_request_completion_recipient_ids($db, $request_id, $actor_id)
{
    $request_id = (int) $request_id;
    $actor_id = (int) $actor_id;
    $recipient_ids = [];

    $stmtCreator = $db->prepare('SELECT created_by FROM requests WHERE id = ?');
    $stmtCreator->execute([$request_id]);
    $creator_id = (int) $stmtCreator->fetchColumn();

    if ($creator_id > 0 && $creator_id !== $actor_id) {
        $recipient_ids[] = $creator_id;
    }

    $stmtAssigners = $db->prepare("
        SELECT DISTINCT n.triggered_by
        FROM notifications n
        INNER JOIN request_assignments ra
            ON ra.request_id = n.request_id
           AND ra.user_id = n.user_id
        WHERE n.request_id = ?
          AND n.type = 'assignment'
          AND n.triggered_by IS NOT NULL
          AND n.id = (
              SELECT MAX(n_latest.id)
              FROM notifications n_latest
              WHERE n_latest.request_id = n.request_id
                AND n_latest.user_id = n.user_id
                AND n_latest.type = 'assignment'
          )
    ");
    $stmtAssigners->execute([$request_id]);

    foreach ($stmtAssigners->fetchAll(PDO::FETCH_COLUMN) as $assigner_id) {
        $assigner_id = (int) $assigner_id;
        if ($assigner_id > 0 && $assigner_id !== $actor_id) {
            $recipient_ids[] = $assigner_id;
        }
    }

    $recipient_ids = array_values(array_unique($recipient_ids));
    sort($recipient_ids, SORT_NUMERIC);

    return $recipient_ids;
}

/**
 * Create one in-app notification per unique completion recipient.
 */
function create_request_completion_notifications($db, $request_id, $actor_id, $actor_name)
{
    $recipient_ids = get_request_completion_recipient_ids($db, $request_id, $actor_id);
    if (empty($recipient_ids)) {
        return 0;
    }

    $actor_name = trim((string) $actor_name);
    if ($actor_name === '') {
        $actor_name = 'Alguien';
    }

    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, request_id, triggered_by, message)
        VALUES (?, 'completion', ?, ?, ?)
    ");
    $message = $actor_name . ' ha completado la mejora';

    foreach ($recipient_ids as $recipient_id) {
        $stmt->execute([
            $recipient_id,
            (int) $request_id,
            (int) $actor_id,
            $message
        ]);
    }

    return count($recipient_ids);
}
