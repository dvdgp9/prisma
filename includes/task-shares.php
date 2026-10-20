<?php
/**
 * Tasks shared with specific people: who has each task, whether they have seen
 * it yet, and the "te han asignado una tarea" notice.
 *
 * Every function takes the PDO connection so tests can run against SQLite.
 * Permission checks live in the API (require_task_capability in auth.php).
 */

const TASK_SEEN_MAX_IDS = 500;

// Positive, unique integer ids (anything else is dropped).
function task_share_clean_ids($ids)
{
    return array_values(array_unique(array_filter(
        array_map('intval', (array) $ids),
        fn($id) => $id > 0
    )));
}

/**
 * Set who a task is shared with. Whole team clears the individual list;
 * otherwise only teammates of the same company are kept (never the owner).
 * Works by difference so people who keep the task keep their "seen" mark.
 * Returns the people newly added, for notifications.
 */
function replace_task_shares($db, $user, $task_id, $share_with_all, $user_ids)
{
    $task_id = (int) $task_id;

    $stmt = $db->prepare("SELECT user_id FROM task_shares WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $before = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $valid = [];
    if (!$share_with_all) {
        $candidates = array_values(array_filter(
            task_share_clean_ids($user_ids),
            fn($id) => $id !== (int) $user['id']
        ));

        if ($candidates) {
            $placeholders = implode(', ', array_fill(0, count($candidates), '?'));
            $stmt = $db->prepare("SELECT id FROM users WHERE company_id = ? AND id IN ({$placeholders}) ORDER BY id");
            $stmt->execute(array_merge([(int) $user['company_id']], $candidates));
            $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    $removed = array_values(array_diff($before, $valid));
    $added = array_values(array_diff($valid, $before));

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE tasks SET is_shared = ? WHERE id = ?");
        $stmt->execute([$share_with_all ? 1 : 0, $task_id]);

        if ($removed) {
            $placeholders = implode(', ', array_fill(0, count($removed), '?'));
            $stmt = $db->prepare("DELETE FROM task_shares WHERE task_id = ? AND user_id IN ({$placeholders})");
            $stmt->execute(array_merge([$task_id], $removed));

            // Whoever no longer has the task loses its notice too.
            $stmt = $db->prepare("DELETE FROM notifications WHERE type = 'task_assignment' AND task_id = ? AND user_id IN ({$placeholders})");
            $stmt->execute(array_merge([$task_id], $removed));
        }

        $stmt = $db->prepare("INSERT INTO task_shares (task_id, user_id) VALUES (?, ?)");
        foreach ($added as $userId) {
            $stmt->execute([$task_id, $userId]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    return $added;
}

/**
 * In-app notice for each person a task was just assigned to by name (sharing
 * with the whole team does not notify). A repeated notice replaces the
 * previous one, so removing and adding someone again leaves a single notice.
 * Returns how many were created.
 */
function create_task_assignment_notifications($db, $task_id, $recipient_ids, $actor)
{
    $recipients = array_values(array_filter(
        task_share_clean_ids($recipient_ids),
        fn($id) => $id !== (int) $actor['id']
    ));
    if (!$recipients) {
        return 0;
    }

    $name = trim((string) ($actor['full_name'] ?? '')) ?: ($actor['username'] ?? 'Alguien');
    $delete = $db->prepare("DELETE FROM notifications WHERE type = 'task_assignment' AND task_id = ? AND user_id = ?");
    $insert = $db->prepare("
        INSERT INTO notifications (user_id, type, task_id, triggered_by, message)
        VALUES (?, 'task_assignment', ?, ?, ?)
    ");
    foreach ($recipients as $userId) {
        $delete->execute([(int) $task_id, $userId]);
        $insert->execute([$userId, (int) $task_id, (int) $actor['id'], "{$name} te ha asignado una tarea"]);
    }

    return count($recipients);
}

/**
 * The user has seen these tasks (they were shown on screen): stamp the first
 * time only, and read the matching assignment notices, which are no longer needed.
 * Only touches the user's own rows, so no other permission check is needed.
 */
function mark_tasks_seen($db, $user_id, $task_ids)
{
    $ids = array_slice(task_share_clean_ids($task_ids), 0, TASK_SEEN_MAX_IDS);
    if (!$ids) {
        return ['seen' => 0, 'notifications_read' => 0];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $params = array_merge([(int) $user_id], $ids);

    $stmt = $db->prepare("UPDATE task_shares SET seen_at = CURRENT_TIMESTAMP WHERE user_id = ? AND seen_at IS NULL AND task_id IN ({$placeholders})");
    $stmt->execute($params);
    $seen = $stmt->rowCount();

    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'task_assignment' AND is_read = 0 AND task_id IN ({$placeholders})");
    $stmt->execute($params);

    return ['seen' => $seen, 'notifications_read' => $stmt->rowCount()];
}

/**
 * Who each task is shared with by name and when they saw it (null = not yet),
 * keyed by task id. Tasks without individual shares are left out.
 */
function task_share_status($db, $task_ids)
{
    $ids = task_share_clean_ids($task_ids);
    if (!$ids) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT task_id, user_id, seen_at FROM task_shares WHERE task_id IN ({$placeholders}) ORDER BY task_id, user_id");
    $stmt->execute($ids);

    $status = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status[(int) $row['task_id']][] = ['user_id' => (int) $row['user_id'], 'seen_at' => $row['seen_at']];
    }

    return $status;
}
