<?php
/**
 * Users List API - Get users for mentions and assignment
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// All operations require login
require_login();

$db = getDB();
$user = get_logged_user();

try {
    // Get users that can be mentioned/assigned
    // Superadmins see all users, others see users in their companies
    if ($user['role'] === 'superadmin') {
        $stmt = $db->query("
            SELECT id, username, full_name, role, company_id
            FROM users 
            WHERE is_active = 1
            ORDER BY full_name, username
        ");
    } else {
        // Get users from same companies
        $companies = get_user_companies();
        $companyIds = array_map('intval', array_column($companies, 'id'));
        
        if (empty($companyIds)) {
            $companyIds = [(int)$user['company_id']];
        }
        
        $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT u.id, u.username, u.full_name, u.role, u.company_id
            FROM users u
            LEFT JOIN user_companies uc ON u.id = uc.user_id
            WHERE u.is_active = 1 
            AND (u.company_id IN ($placeholders) OR uc.company_id IN ($placeholders))
            ORDER BY u.full_name, u.username
        ");
        $stmt->execute(array_merge($companyIds, $companyIds));
    }
    
    $users = $stmt->fetchAll();
    
    success_response($users);
} catch (Exception $e) {
    error_response('Failed to fetch users: ' . $e->getMessage(), 500);
}
