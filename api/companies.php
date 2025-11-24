<?php
/**
 * Companies API - CRUD operations for companies management (superadmin only)
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Superadmin only
require_role('superadmin');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

switch ($method) {
    case 'GET':
        // Get all companies with stats
        try {
            $stmt = $db->query("
                SELECT 
                    c.*,
                    (SELECT COUNT(*) FROM users WHERE company_id = c.id) as user_count,
                    (SELECT COUNT(*) FROM users WHERE company_id = c.id AND role = 'admin') as admin_count
                FROM companies c
                ORDER BY c.name ASC
            ");
            $companies = $stmt->fetchAll();

            success_response($companies);
        } catch (Exception $e) {
            error_response('Failed to fetch companies: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        // Create new company
        $input = get_json_input();

        if (empty($input['name'])) {
            error_response('Company name is required');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO companies (name, description) 
                VALUES (?, ?)
            ");
            $stmt->execute([
                $input['name'],
                $input['description'] ?? null
            ]);

            $company_id = $db->lastInsertId();

            success_response(['id' => $company_id], 'Company created successfully');
        } catch (Exception $e) {
            error_response('Failed to create company: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        // Update company
        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('Company ID is required');
        }

        try {
            $updates = [];
            $values = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $values[] = $input['name'];
            }

            if (isset($input['description'])) {
                $updates[] = 'description = ?';
                $values[] = $input['description'];
            }

            if (empty($updates)) {
                error_response('No fields to update');
            }

            $values[] = $input['id'];

            $stmt = $db->prepare("
                UPDATE companies 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($values);

            success_response([], 'Company updated successfully');
        } catch (Exception $e) {
            error_response('Failed to update company: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Delete company (will cascade delete users)
        $input = get_json_input();

        if (empty($input['id'])) {
            error_response('Company ID is required');
        }

        try {
            $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$input['id']]);

            success_response([], 'Company deleted successfully');
        } catch (Exception $e) {
            error_response('Failed to delete company: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
