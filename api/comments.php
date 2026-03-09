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

switch ($method) {
    case 'GET':
        // Get comments for a request
        if (empty($_GET['request_id'])) {
            error_response('Request ID is required');
        }

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
            $stmt->execute([$_GET['request_id']]);
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

        try {
            $db->beginTransaction();

            // Insert comment
            $stmt = $db->prepare("
                INSERT INTO request_comments (request_id, user_id, content)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $input['request_id'],
                $user['id'],
                $input['content']
            ]);

            $comment_id = $db->lastInsertId();

            // Parse mentions (@username) from content
            preg_match_all('/@(\w+)/', $input['content'], $matches);
            $mentioned_usernames = array_unique($matches[1]);

            if (!empty($mentioned_usernames)) {
                // Get user IDs for mentioned usernames
                $placeholders = implode(',', array_fill(0, count($mentioned_usernames), '?'));
                $stmt = $db->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
                $stmt->execute($mentioned_usernames);
                $mentioned_users = $stmt->fetchAll();

                // Insert mentions
                foreach ($mentioned_users as $mentioned_user) {
                    $stmtMention = $db->prepare("
                        INSERT INTO comment_mentions (comment_id, mentioned_user_id)
                        VALUES (?, ?)
                    ");
                    $stmtMention->execute([$comment_id, $mentioned_user['id']]);
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
            $db->rollBack();
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
            $stmt = $db->prepare("SELECT user_id FROM request_comments WHERE id = ?");
            $stmt->execute([$input['id']]);
            $comment = $stmt->fetch();

            if (!$comment) {
                error_response('Comment not found', 404);
            }

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
                $placeholders = implode(',', array_fill(0, count($mentioned_usernames), '?'));
                $stmt = $db->prepare("SELECT id, username FROM users WHERE username IN ($placeholders)");
                $stmt->execute($mentioned_usernames);
                $mentioned_users = $stmt->fetchAll();

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
            $db->rollBack();
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
            $stmt = $db->prepare("SELECT user_id FROM request_comments WHERE id = ?");
            $stmt->execute([$input['id']]);
            $comment = $stmt->fetch();

            if (!$comment) {
                error_response('Comment not found', 404);
            }

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
