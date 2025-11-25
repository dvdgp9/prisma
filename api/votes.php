<?php
/**
 * Votes API - Handle voting on feature requests
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Must be logged in
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$user = get_logged_user();

if ($method === 'POST') {
    $input = get_json_input();

    if (empty($input['request_id'])) {
        error_response('Request ID is required');
    }

    $request_id = $input['request_id'];
    $action = $input['action'] ?? 'up'; // 'up' or 'down'
    $is_admin = has_role('admin') || has_role('superadmin');

    try {
        $db->beginTransaction();

        // Get current vote count
        $stmt = $db->prepare("SELECT vote_count FROM requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            $db->rollBack();
            error_response('Request not found', 404);
        }

        $current_votes = $request['vote_count'] ?? 0;

        if ($action === 'up') {
            // Upvote
            if (!$is_admin) {
                // Regular users: check if already voted
                $stmt = $db->prepare("
                    SELECT id FROM votes 
                    WHERE request_id = ? AND user_id = ?
                ");
                $stmt->execute([$request_id, $user['id']]);

                if ($stmt->fetch()) {
                    $db->rollBack();
                    error_response('You have already voted for this request');
                }

                // Record the vote
                $stmt = $db->prepare("
                    INSERT INTO votes (request_id, user_id) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$request_id, $user['id']]);
            }

            // Increment vote count
            $stmt = $db->prepare("
                UPDATE requests 
                SET vote_count = vote_count + 1 
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            $new_votes = $current_votes + 1;

        } else if ($action === 'down') {
            // Downvote (admin only)
            if (!$is_admin) {
                $db->rollBack();
                error_response('Unauthorized - only admins can downvote', 403);
            }

            // Prevent negative votes
            if ($current_votes <= 0) {
                $db->rollBack();
                error_response('Vote count cannot be negative');
            }

            // Decrement vote count
            $stmt = $db->prepare("
                UPDATE requests 
                SET vote_count = GREATEST(0, vote_count - 1)
                WHERE id = ?
            ");
            $stmt->execute([$request_id]);
            $new_votes = max(0, $current_votes - 1);

        } else {
            $db->rollBack();
            error_response('Invalid action');
        }

        $db->commit();
        success_response([
            'votes' => $new_votes,
            'action' => $action
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        error_response('Failed to process vote: ' . $e->getMessage(), 500);
    }

} else if ($method === 'GET') {
    // Get vote status for a request
    if (empty($_GET['request_id'])) {
        error_response('Request ID is required');
    }

    try {
        // Check if current user voted
        $stmt = $db->prepare("
            SELECT id 
            FROM votes 
            WHERE request_id = ? AND user_id = ?
        ");
        $stmt->execute([$_GET['request_id'], $user['id']]);
        $user_voted = $stmt->fetch() !== false;

        // Get total vote count
        $stmt = $db->prepare("SELECT vote_count FROM requests WHERE id = ?");
        $stmt->execute([$_GET['request_id']]);
        $result = $stmt->fetch();

        success_response([
            'vote_count' => $result['vote_count'] ?? 0,
            'user_voted' => $user_voted
        ]);

    } catch (Exception $e) {
        error_response('Failed to get vote status: ' . $e->getMessage(), 500);
    }

} else {
    error_response('Method not allowed', 405);
}
