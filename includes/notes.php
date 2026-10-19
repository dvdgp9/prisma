<?php
/**
 * Shared notes: data access used by api/notes.php.
 *
 * Every function takes the PDO connection so tests can run against SQLite.
 * Permission checks live in the API (require_note_capability) and auth.php.
 */

require_once __DIR__ . '/auth.php';

const NOTE_MAX_CONTENT_LENGTH = 200000;
const NOTE_LIST_MAX = 200;
const NOTE_EXCERPT_LENGTH = 240;

/**
 * Notes the user can see in their current company: own ones plus those shared
 * with the whole team or with them by name. Pinned first, then last edited.
 *
 * Filters: filter (all | mine | shared), app_id, q (title/content), limit.
 */
function list_notes($db, $user, $filters)
{
    $userId = (int) $user['id'];
    $sql = "
        SELECT n.id, n.user_id, n.app_id, n.title,
               SUBSTRING(n.content, 1, " . NOTE_EXCERPT_LENGTH . ") AS excerpt,
               n.is_shared, n.is_pinned, n.updated_by, n.created_at, n.updated_at,
               COALESCE(NULLIF(u.full_name, ''), u.username) AS author_name,
               a.name AS app_name,
               (SELECT COUNT(*) FROM note_shares ns_count WHERE ns_count.note_id = n.id) AS share_count,
               CURRENT_TIMESTAMP AS server_now
        FROM notes n
        LEFT JOIN users u ON u.id = n.user_id
        LEFT JOIN apps a ON a.id = n.app_id
        WHERE n.company_id = :company_id
          AND (n.user_id = :user_id OR n.is_shared = 1
               OR EXISTS (SELECT 1 FROM note_shares ns WHERE ns.note_id = n.id AND ns.user_id = :user_id2))
    ";
    // Native prepares cannot reuse a placeholder, hence user_id / user_id2 / owner_id.
    $params = [':company_id' => (int) $user['company_id'], ':user_id' => $userId, ':user_id2' => $userId];

    $filter = $filters['filter'] ?? 'all';
    if ($filter === 'mine') {
        $sql .= " AND n.user_id = :owner_id";
        $params[':owner_id'] = $userId;
    } elseif ($filter === 'shared') {
        $sql .= " AND n.user_id <> :owner_id";
        $params[':owner_id'] = $userId;
    }

    if (!empty($filters['app_id'])) {
        $sql .= " AND n.app_id = :app_id";
        $params[':app_id'] = (int) $filters['app_id'];
    }

    $q = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
    if ($q !== '') {
        // '!' as escape character works the same in MySQL and SQLite.
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
        $sql .= " AND (n.title LIKE :q_title ESCAPE '!' OR n.content LIKE :q_content ESCAPE '!')";
        $params[':q_title'] = $like;
        $params[':q_content'] = $like;
    }

    $limit = (int) ($filters['limit'] ?? NOTE_LIST_MAX);
    $limit = $limit > 0 ? min($limit, NOTE_LIST_MAX) : NOTE_LIST_MAX;
    $sql .= " ORDER BY n.is_pinned DESC, n.updated_at DESC, n.id DESC LIMIT " . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // The query only returns notes the user can see, so anything not owned is shared with them.
    foreach ($notes as &$note) {
        $isOwner = (int) $note['user_id'] === $userId;
        $note['capabilities'] = note_capabilities_for_access($isOwner, !$isOwner);
    }
    unset($note);

    return $notes;
}

/**
 * One note with names for display and the people it is shared with.
 * Returns false if it does not exist (callers check access first).
 * server_now (also in list rows) is the database clock: the browser computes
 * "Hace X" against it, so a server in another time zone does not skew it.
 */
function get_note_detail($db, $note_id)
{
    $stmt = $db->prepare("
        SELECT n.*,
               COALESCE(NULLIF(u.full_name, ''), u.username) AS author_name,
               COALESCE(NULLIF(ub.full_name, ''), ub.username) AS updated_by_name,
               a.name AS app_name,
               CURRENT_TIMESTAMP AS server_now
        FROM notes n
        LEFT JOIN users u ON u.id = n.user_id
        LEFT JOIN users ub ON ub.id = n.updated_by
        LEFT JOIN apps a ON a.id = n.app_id
        WHERE n.id = ?
    ");
    $stmt->execute([(int) $note_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        return false;
    }

    $stmt = $db->prepare("
        SELECT u.id, u.username, u.full_name
        FROM note_shares ns
        JOIN users u ON u.id = ns.user_id
        WHERE ns.note_id = ?
        ORDER BY u.full_name, u.id
    ");
    $stmt->execute([(int) $note_id]);
    $note['shared_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $note['revision'] = note_revision($note);

    return $note;
}

/**
 * Clean the editable fields of a note from request input.
 *
 * On create every field is returned (with defaults); on update only the ones
 * sent. Unknown keys are ignored. Returns ['fields' => [...], 'error' => ?string].
 */
function normalize_note_fields($input, $is_create)
{
    $fields = [];

    if ($is_create || array_key_exists('title', $input)) {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['fields' => [], 'error' => 'El título es obligatorio'];
        }
        if (mb_strlen($title) > 255) {
            return ['fields' => [], 'error' => 'El título no puede superar 255 caracteres'];
        }
        $fields['title'] = $title;
    }

    if ($is_create || array_key_exists('content', $input)) {
        $content = (string) ($input['content'] ?? '');
        if (mb_strlen($content) > NOTE_MAX_CONTENT_LENGTH) {
            return ['fields' => [], 'error' => 'La nota es demasiado larga'];
        }
        $fields['content'] = $content === '' ? null : $content;
    }

    if ($is_create || array_key_exists('app_id', $input)) {
        $appId = (int) ($input['app_id'] ?? 0);
        $fields['app_id'] = $appId > 0 ? $appId : null;
    }

    if ($is_create || array_key_exists('is_pinned', $input)) {
        $fields['is_pinned'] = !empty($input['is_pinned']) ? 1 : 0;
    }

    return ['fields' => $fields, 'error' => null];
}

function create_note($db, $user, $fields)
{
    $stmt = $db->prepare("
        INSERT INTO notes (company_id, user_id, app_id, title, content, is_pinned, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $user['company_id'],
        (int) $user['id'],
        $fields['app_id'] ?? null,
        $fields['title'],
        $fields['content'] ?? null,
        $fields['is_pinned'] ?? 0,
        (int) $user['id'],
    ]);

    return (int) $db->lastInsertId();
}

/**
 * Apply normalized fields. Title/content edits record who edited and when;
 * pinning or moving to another app does not count as an edit.
 */
function update_note_fields($db, $user, $note_id, $fields)
{
    if (empty($fields)) {
        return;
    }

    $sets = [];
    $params = [];
    foreach ($fields as $column => $value) {
        $sets[] = "{$column} = ?";
        $params[] = $value;
    }

    if (array_key_exists('title', $fields) || array_key_exists('content', $fields)) {
        $sets[] = 'updated_by = ?';
        $params[] = (int) $user['id'];
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
    } else {
        // An explicit assignment stops MySQL's ON UPDATE CURRENT_TIMESTAMP.
        $sets[] = 'updated_at = updated_at';
    }

    $params[] = (int) $note_id;
    $stmt = $db->prepare("UPDATE notes SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->execute($params);
}

/**
 * Version stamp of a note's text. updated_at alone only has one-second
 * precision, so title and content are part of it too.
 */
function note_revision($note)
{
    return substr(sha1($note['updated_at'] . "\n" . $note['title'] . "\n" . ($note['content'] ?? '')), 0, 16);
}

/**
 * True when a title/content edit starts from a version someone else has
 * since changed (the client sends back the revision it loaded).
 */
function note_edit_conflict($note, $input)
{
    if (!array_key_exists('title', $input) && !array_key_exists('content', $input)) {
        return false;
    }
    if (empty($input['expected_revision'])) {
        return false;
    }

    return (string) $input['expected_revision'] !== note_revision($note);
}

/**
 * Set who a note is shared with. Whole team clears the individual list;
 * otherwise only active teammates of the company are kept (never the author).
 * Returns the people newly added, for notifications.
 */
function replace_note_shares($db, $user, $note_id, $is_shared, $user_ids)
{
    $note_id = (int) $note_id;

    $stmt = $db->prepare("SELECT user_id FROM note_shares WHERE note_id = ?");
    $stmt->execute([$note_id]);
    $before = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $valid = [];
    if (!$is_shared) {
        $candidates = array_values(array_unique(array_filter(
            array_map('intval', (array) $user_ids),
            fn($id) => $id > 0 && $id !== (int) $user['id']
        )));

        if ($candidates) {
            $placeholders = implode(', ', array_fill(0, count($candidates), '?'));
            $stmt = $db->prepare("
                SELECT id FROM users
                WHERE company_id = ? AND is_active = 1 AND id IN ({$placeholders})
                ORDER BY id
            ");
            $stmt->execute(array_merge([(int) $user['company_id']], $candidates));
            $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE notes SET is_shared = ?, updated_at = updated_at WHERE id = ?");
        $stmt->execute([$is_shared ? 1 : 0, $note_id]);

        $stmt = $db->prepare("DELETE FROM note_shares WHERE note_id = ?");
        $stmt->execute([$note_id]);

        $stmt = $db->prepare("INSERT INTO note_shares (note_id, user_id) VALUES (?, ?)");
        foreach ($valid as $userId) {
            $stmt->execute([$note_id, $userId]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    return array_values(array_diff($valid, $before));
}

/**
 * Delete a note with its shares and its notifications.
 */
function delete_note($db, $note_id)
{
    $note_id = (int) $note_id;

    $db->beginTransaction();
    try {
        foreach ([
            "DELETE FROM notifications WHERE note_id = ?",
            "DELETE FROM note_shares WHERE note_id = ?",
            "DELETE FROM notes WHERE id = ?",
        ] as $sql) {
            $stmt = $db->prepare($sql);
            $stmt->execute([$note_id]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
