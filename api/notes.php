<?php
/**
 * Notes API - Shared team notes (data access in includes/notes.php)
 *
 * GET    ?id=N                              one note (with who it is shared with)
 * GET    ?filter=all|mine|shared&app_id&q&limit&order=recent   list
 * POST   {title, content, app_id, is_pinned, is_shared, shared_user_ids}
 * PUT    {id, title?, content?, app_id?, is_pinned?, expected_revision?}
 *        {id, is_shared?, shared_user_ids?}   (author only)
 * DELETE {id}                                (author only)
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notes.php';

header('Content-Type: application/json');

require_login();

$db = getDB();
$user = get_logged_user();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $access = require_note_capability($_GET['id'], 'view');
            $note = get_note_detail($db, $access['note']['id']);
            $note['capabilities'] = $access['capabilities'];

            success_response($note);
        }

        success_response(list_notes($db, $user, $_GET));
        break;

    case 'POST':
        $input = get_json_input() ?: [];

        $result = normalize_note_fields($input, true);
        if ($result['error']) {
            error_response($result['error']);
        }
        $fields = $result['fields'];

        if ($fields['app_id'] !== null && !can_access_app($fields['app_id'])) {
            error_response('No tienes acceso a esa aplicación', 403);
        }

        $noteId = create_note($db, $user, $fields);

        if (!empty($input['is_shared']) || !empty($input['shared_user_ids'])) {
            $added = replace_note_shares($db, $user, $noteId, !empty($input['is_shared']), $input['shared_user_ids'] ?? []);
            create_note_share_notifications($db, $noteId, $added, $user);
        }

        $note = get_note_detail($db, $noteId);
        $note['capabilities'] = note_capabilities_for_access(true, false);

        success_response($note, 'Nota creada');
        break;

    case 'PUT':
        $input = get_json_input() ?: [];

        if (empty($input['id'])) {
            error_response('ID de nota requerido');
        }

        // People the note is shared with can edit it; only the author changes who it is shared with.
        $access = require_note_capability($input['id'], note_capability_for_update($input));
        $noteId = (int) $access['note']['id'];

        $result = normalize_note_fields($input, false);
        if ($result['error']) {
            error_response($result['error']);
        }
        $fields = $result['fields'];

        if (isset($fields['app_id']) && !can_access_app($fields['app_id'])) {
            error_response('No tienes acceso a esa aplicación', 403);
        }

        // Someone else saved the note since this person opened it: send the current version back.
        if (note_edit_conflict($access['note'], $input)) {
            $current = get_note_detail($db, $noteId);
            $current['capabilities'] = $access['capabilities'];
            $who = $current['updated_by_name'] ?: 'Otra persona';

            json_response([
                'error' => "{$who} ha cambiado esta nota mientras la editabas",
                'conflict' => true,
                'data' => $current
            ], 409);
        }

        $changesSharing = note_capability_for_update($input) === 'share';
        if (empty($fields) && !$changesSharing) {
            error_response('Nada que actualizar');
        }

        update_note_fields($db, $user, $noteId, $fields);

        if ($changesSharing) {
            $isShared = array_key_exists('is_shared', $input)
                ? !empty($input['is_shared'])
                : (int) $access['note']['is_shared'] === 1;
            $userIds = array_key_exists('shared_user_ids', $input)
                ? (array) $input['shared_user_ids']
                : array_column(get_note_detail($db, $noteId)['shared_users'], 'id');

            // Aviso en Notificaciones solo a quien se añade ahora (no a quien ya la tenía).
            $added = replace_note_shares($db, $user, $noteId, $isShared, $userIds);
            create_note_share_notifications($db, $noteId, $added, $user);
        }

        $note = get_note_detail($db, $noteId);
        $note['capabilities'] = $access['capabilities'];

        success_response($note, 'Nota actualizada');
        break;

    case 'DELETE':
        $input = get_json_input() ?: [];

        if (empty($input['id'])) {
            error_response('ID de nota requerido');
        }

        // Only the author can delete
        $access = require_note_capability($input['id'], 'delete');
        delete_note($db, (int) $access['note']['id']);

        success_response(null, 'Nota eliminada');
        break;

    default:
        error_response('Method not allowed', 405);
}
