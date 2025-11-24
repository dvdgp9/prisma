<?php
/**
 * Votes API - Voting system for requests
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// All operations require login
require_login();

$db = getDB();
$user = get_current_user();

if ($method === 'POST') {
    $input = get_json_input();

    if (empty($input['request_id'])) {
        error_response('Request ID is required');
    }

    $action = $input['action'] ?? 'up'; // 'up' or 'down'

    try {
        $db->beginTransaction();

        // Check if user already voted
        $stmt = $db->prepare("
            SELECT id, vote_type 
            FROM votes 
            WHERE request_id = ? AND user_id = ?
        ");
        $stmt->execute([$input['request_id'], $user['id']]);
        $existing_vote = $stmt->fetch();

        if ($action === 'up') {
            if ($existing_vote) {
                // User already voted
                if (has_role('admin')) {
                    // Admins can vote multiple times, but we'll just keep one vote per admin
                    // Update to ensure it's an upvote
                    $stmt = $db->prepare("
                        UPDATE votes 
                        SET vote_type = 'up' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$existing_vote['id']]);
                } else {
                    // Regular users can only vote once
                    $db->rollBack();
                    error_response('You have already voted on this request');
                }
            } else {
                // Add new vote
                $stmt = $db->prepare("
                    INSERT INTO votes (request_id, user_id, vote_type) 
                    VALUES (?, ?, 'up')
                ");
                $stmt->execute([$input['request_id'], $user['id']]);
            }

            // Update vote count
            $stmt = $db->prepare("
                UPDATE requests 
                SET vote_count = (SELECT COUNT(*) FROM votes WHERE request_id = ? AND vote_type = 'up')
                WHERE id = ?
            ");
            $stmt->execute([$input['request_id'], $input['request_id']]);

        } else if ($action === 'down') {
            // Only admins can downvote (remove votes)
            if (!has_role('admin')) {
                $db->rollBack();
                error_response('Only admins can remove votes', 403);
            }

            if ($existing_vote) {
                // Remove the vote
                $stmt = $db->prepare("DELETE FROM votes WHERE id = ?");
                $stmt->execute([$existing_vote['id']]);

                // Update vote count
                $stmt = $db->prepare("
                    UPDATE requests 
                    SET vote_count = (SELECT COUNT(*) FROM votes WHERE request_id = ? AND vote_type = 'up')
                    WHERE id = ?
                ");
                $stmt->execute([$input['request_id'], $input['request_id']]);
            }
        }

        // Get updated vote count
        $stmt = $db->prepare("SELECT vote_count FROM requests WHERE id = ?");
        $stmt->execute([$input['request_id']]);
        $result = $stmt->fetch();

        $db->commit();

        success_response([
            'vote_count' => $result['vote_count'],
            'user_voted' => $action === 'up'
        ], 'Vote recorded successfully');

    } catch (Exception $e) {
        $db->rollBack();
        error_response('Failed to record vote: ' . $e->getMessage(), 500);
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
