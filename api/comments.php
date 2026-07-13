<?php
/**
 * Comments API - CRUD operations for request comments with @mentions
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// All operations require login
require_login();

$db = getDB();
$user = get_logged_user();

/**
 * Return active users who can access the request's app. This mirrors the
 * company/app fallback rules in can_access_app(), but evaluates them for the
 * users being mentioned instead of the current session user.
 */
function get_mentionable_users_for_request($db, $request_scope, $usernames = [])
{
    $params = [
        $request_scope['company_id'],
        $request_scope['company_id'],
        $request_scope['app_id']
    ];
    $username_filter = '';

    if (!empty($usernames)) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $username_filter = " AND u.username IN ($placeholders)";
        $params = array_merge($params, array_values($usernames));
    }

    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.username, u.full_name, u.role, u.company_id
        FROM users u
        WHERE u.is_active = 1
          AND (
                u.role = 'superadmin'
                OR (
                    (
                        (
                            EXISTS (SELECT 1 FROM user_companies uc_any WHERE uc_any.user_id = u.id)
                            AND EXISTS (
                                SELECT 1 FROM user_companies uc
                                WHERE uc.user_id = u.id AND uc.company_id = ?
                            )
                        )
                        OR (
                            NOT EXISTS (SELECT 1 FROM user_companies uc_any WHERE uc_any.user_id = u.id)
                            AND u.company_id = ?
                        )
                    )
                    AND (
                        NOT EXISTS (
                            SELECT 1 FROM user_app_permissions uap_any
                            WHERE uap_any.user_id = u.id
                        )
                        OR EXISTS (
                            SELECT 1 FROM user_app_permissions uap
                            WHERE uap.user_id = u.id
                              AND uap.app_id = ?
                              AND uap.can_view = 1
                        )
                    )
                )
          )
          $username_filter
        ORDER BY u.full_name, u.username
    ");

    $stmt->execute($params);
    return $stmt->fetchAll();
}

switch ($method) {
    case 'GET':
        // Get comments for a request
        if (empty($_GET['request_id'])) {
            error_response('Request ID is required');
        }

        $request_id = (int) $_GET['request_id'];
        require_request_capability($request_id, 'view');

        try {
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    u.username,
                    u.full_name,
                    u.role as user_role
                FROM request_comments c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.request_id = ?
                ORDER BY c.created_at ASC
            ");
            $stmt->execute([$request_id]);
            $comments = $stmt->fetchAll();

            // Get mentions for each comment
            foreach ($comments as &$comment) {
                $stmtMentions = $db->prepare("
                    SELECT 
                        cm.mentioned_user_id,
                        u.username,
                        u.full_name
                    FROM comment_mentions cm
                    INNER JOIN users u ON cm.mentioned_user_id = u.id
                    WHERE cm.comment_id = ?
                ");
                $stmtMentions->execute([$comment['id']]);
                $comment['mentions'] = $stmtMentions->fetchAll();
            }

            success_response($comments);
        } catch (Exception $e) {
            error_response('Failed to fetch comments: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Create new comment
        $input = get_json_input();

        if (empty($input['request_id']) || empty($input['content'])) {
            error_response('Request ID and content are required');
        }

        $request_id = (int) $input['request_id'];
        $request_scope = require_request_capability($request_id, 'comment');

        try {
            $db->beginTransaction();

            // Insert comment
            $stmt = $db->prepare("
                INSERT INTO request_comments (request_id, user_id, content)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $request_id,
                $user['id'],
                $input['content']
            ]);

            $comment_id = $db->lastInsertId();

            // Parse mentions (@username) from content
            preg_match_all('/@(\w+)/', $input['content'], $matches);
            $mentioned_usernames = array_unique($matches[1]);

            $mentioned_users = [];
            if (!empty($mentioned_usernames)) {
                // Only users who can access this request's app may be mentioned.
                $mentioned_users = get_mentionable_users_for_request($db, $request_scope, $mentioned_usernames);

                // Insert mentions and create notifications
                foreach ($mentioned_users as $mentioned_user) {
                    $stmtMention = $db->prepare("
                        INSERT INTO comment_mentions (comment_id, mentioned_user_id)
                        VALUES (?, ?)
                    ");
                    $stmtMention->execute([$comment_id, $mentioned_user['id']]);

                    // Create mention notification (don't notify yourself)
                    if ($mentioned_user['id'] != $user['id']) {
                        $stmtNotif = $db->prepare("
                            INSERT INTO notifications (user_id, type, request_id, triggered_by, message)
                            VALUES (?, 'mention', ?, ?, ?)
                        ");
                        $stmtNotif->execute([
                            $mentioned_user['id'],
                            $request_id,
                            $user['id'],
                            $user['full_name'] . ' te ha mencionado en un comentario'
                        ]);
                    }
                }
            }

            // Notify assigned users about the comment (except commenter and already-mentioned)
            $mentionedIds = array_column($mentioned_users, 'id');
            $stmtAssigned = $db->prepare("
                SELECT user_id FROM request_assignments 
                WHERE request_id = ? AND user_id != ?
            ");
            $stmtAssigned->execute([$request_id, $user['id']]);
            $assignedUsers = $stmtAssigned->fetchAll(PDO::FETCH_COLUMN);
            $mentionableIds = array_map(
                'intval',
                array_column(get_mentionable_users_for_request($db, $request_scope), 'id')
            );

            foreach ($assignedUsers as $assignedUserId) {
                $assignedUserId = (int) $assignedUserId;
                if (in_array($assignedUserId, $mentionableIds, true) && !in_array($assignedUserId, $mentionedIds)) {
                    $stmtNotif = $db->prepare("
                        INSERT INTO notifications (user_id, type, request_id, triggered_by, message)
                        VALUES (?, 'comment', ?, ?, ?)
                    ");
                    $stmtNotif->execute([
                        $assignedUserId,
                        $request_id,
                        $user['id'],
                        $user['full_name'] . ' ha comentado en una tarea asignada a ti'
                    ]);
                }
            }

            $db->commit();

            // Fetch the created comment with user info
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    u.username,
                    u.full_name,
                    u.role as user_role
                FROM request_comments c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();

            success_response($comment, 'Comment created successfully');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_response('Failed to create comment: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update comment (only own comments)
        $input = get_json_input();

        if (empty($input['id']) || empty($input['content'])) {
            error_response('Comment ID and content are required');
        }

        try {
            // Check ownership
            $stmt = $db->prepare("SELECT user_id, request_id FROM request_comments WHERE id = ?");
            $stmt->execute([$input['id']]);
            $comment = $stmt->fetch();

            if (!$comment) {
                error_response('Comment not found', 404);
            }

            $request_scope = require_request_capability((int) $comment['request_id'], 'comment');

            // Only owner or admin can edit
            if ($comment['user_id'] != $user['id'] && !has_role('admin')) {
                error_response('Unauthorized', 403);
            }

            $db->beginTransaction();

            // Update comment
            $stmt = $db->prepare("UPDATE request_comments SET content = ? WHERE id = ?");
            $stmt->execute([$input['content'], $input['id']]);

            // Clear old mentions and re-parse
            $stmt = $db->prepare("DELETE FROM comment_mentions WHERE comment_id = ?");
            $stmt->execute([$input['id']]);

            // Parse mentions (@username) from content
            preg_match_all('/@(\w+)/', $input['content'], $matches);
            $mentioned_usernames = array_unique($matches[1]);

            if (!empty($mentioned_usernames)) {
                $mentioned_users = get_mentionable_users_for_request($db, $request_scope, $mentioned_usernames);

                foreach ($mentioned_users as $mentioned_user) {
                    $stmtMention = $db->prepare("
                        INSERT INTO comment_mentions (comment_id, mentioned_user_id)
                        VALUES (?, ?)
                    ");
                    $stmtMention->execute([$input['id'], $mentioned_user['id']]);
                }
            }

            $db->commit();

            success_response([], 'Comment updated successfully');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_response('Failed to update comment: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Delete comment (only own comments or admin)
        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('Comment ID is required');
        }

        try {
            // Check ownership
            $stmt = $db->prepare("SELECT user_id, request_id FROM request_comments WHERE id = ?");
            $stmt->execute([$input['id']]);
            $comment = $stmt->fetch();

            if (!$comment) {
                error_response('Comment not found', 404);
            }

            require_request_capability((int) $comment['request_id'], 'comment');

            // Only owner or admin can delete
            if ($comment['user_id'] != $user['id'] && !has_role('admin')) {
                error_response('Unauthorized', 403);
            }

            $stmt = $db->prepare("DELETE FROM request_comments WHERE id = ?");
            $stmt->execute([$input['id']]);

            success_response([], 'Comment deleted successfully');
        } catch (Exception $e) {
            error_response('Failed to delete comment: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
