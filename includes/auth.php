<?php
/**
 * Authentication and authorization functions
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/../config/database.php';

/**
 * Login user
 */
function login($username, $password, $remember = false)
{
    $db = getDB();

    try {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.password, u.role, u.company_id, u.full_name,
                   c.name as company_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE u.username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['company_name'] = $user['company_name'];

        // Set remember me cookie if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (60 * 24 * 60 * 60); // 60 days

            // Store token in database
            $stmt = $db->prepare("
                UPDATE users 
                SET remember_token = ?, remember_token_expiry = FROM_UNIXTIME(?)
                WHERE id = ?
            ");
            $stmt->execute([$token, $expiry, $user['id']]);

            // Set cookie
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
        }

        return true;
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Logout user
 */
function logout()
{
    $db = getDB();

    // Clear remember token from database if exists
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("
            UPDATE users 
            SET remember_token = NULL, remember_token_expiry = NULL
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
    }

    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }

    // Clear session data
    $_SESSION = array();

    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy session
    session_destroy();
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    // Check remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.role, u.company_id, u.full_name,
                   c.name as company_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE u.remember_token = ? 
            AND u.remember_token_expiry > NOW()
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch();

        if ($user) {
            // Restore session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['company_name'] = $user['company_name'];
            return true;
        } else {
            // Invalid/expired token, remove cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }

    return false;
}

/**
 * Require user to be logged in
 */
function require_login()
{
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Get current user data
 */
function get_logged_user()
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
        'company_id' => $_SESSION['company_id'],
        'company_name' => $_SESSION['company_name'],
        'full_name' => $_SESSION['full_name']
    ];
}

/**
 * Check if current user has a specific role
 */
function has_role($role)
{
    if (!is_logged_in()) {
        return false;
    }

    if ($role === 'superadmin') {
        return $_SESSION['role'] === 'superadmin';
    } else if ($role === 'admin') {
        return in_array($_SESSION['role'], ['superadmin', 'admin']);
    } else if ($role === 'user') {
        return in_array($_SESSION['role'], ['superadmin', 'admin', 'user']);
    }

    return false;
}

/**
 * Require specific role - redirect if not authorized
 */
function require_role($role)
{
    require_login();

    if (!has_role($role)) {
        http_response_code(403);
        die('Access denied. Insufficient permissions.');
    }
}

/**
 * Check if user can modify a request (superadmin or admin in same company)
 */
function can_modify_request($request_creator_id = null)
{
    $user = get_logged_user();

    if ($user['role'] === 'superadmin') {
        return true;
    }

    if ($user['role'] === 'admin') {
        return true; // Admins can modify all requests in their company
    }

    // Regular users can only modify their own requests
    if ($request_creator_id !== null) {
        return $user['id'] == $request_creator_id;
    }

    return false;
}

/**
 * Get JSON input from request body
 */
function get_json_input()
{
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Send JSON response
 */
function json_response($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function error_response($message, $status = 400)
{
    json_response(['error' => $message], $status);
}

/**
 * Send success response
 */
function success_response($data = [], $message = 'Success')
{
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * Get companies that the current user belongs to
 */
function get_user_companies()
{
    $user = get_logged_user();
    if (!$user) {
        return [];
    }

    $db = getDB();

    // Superadmins see all companies
    if ($user['role'] === 'superadmin') {
        $stmt = $db->query("SELECT * FROM companies ORDER BY name");
        return $stmt->fetchAll();
    }

    // Check if user has entries in user_companies table
    $stmt = $db->prepare("
        SELECT c.* 
        FROM companies c
        INNER JOIN user_companies uc ON c.id = uc.company_id
        WHERE uc.user_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();

    // Fallback to legacy company_id if no user_companies entries
    if (empty($companies) && $user['company_id']) {
        $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
        $stmt->execute([$user['company_id']]);
        $companies = $stmt->fetchAll();
    }

    return $companies;
}

/**
 * Get apps that the current user has permission to see
 * Returns apps grouped by company for multi-company support
 */
function get_user_apps($grouped = false)
{
    $user = get_logged_user();
    if (!$user) {
        return [];
    }

    $db = getDB();

    // Superadmins see all apps
    if ($user['role'] === 'superadmin') {
        $stmt = $db->query("
            SELECT a.*, c.name as company_name 
            FROM apps a
            LEFT JOIN companies c ON a.company_id = c.id
            WHERE a.is_active = 1 
            ORDER BY c.name, a.name
        ");
        $apps = $stmt->fetchAll();
        
        if ($grouped) {
            return group_apps_by_company($apps);
        }
        return $apps;
    }

    // Get user's companies
    $companies = get_user_companies();
    if (empty($companies)) {
        return [];
    }
    
    $company_ids = array_column($companies, 'id');
    
    // Check if user has specific app permissions
    $stmtPerms = $db->prepare("SELECT app_id FROM user_app_permissions WHERE user_id = ? AND can_view = 1");
    $stmtPerms->execute([$user['id']]);
    $allowed_app_ids = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allowed_app_ids)) {
        // No specific permissions: see all apps in user's companies
        $placeholders = implode(',', array_fill(0, count($company_ids), '?'));
        $stmt = $db->prepare("
            SELECT a.*, c.name as company_name 
            FROM apps a
            LEFT JOIN companies c ON a.company_id = c.id
            WHERE a.is_active = 1 AND a.company_id IN ($placeholders)
            ORDER BY c.name, a.name
        ");
        $stmt->execute($company_ids);
    } else {
        // Specific permissions defined: filter apps
        $placeholders = implode(',', array_fill(0, count($allowed_app_ids), '?'));
        $stmt = $db->prepare("
            SELECT a.*, c.name as company_name 
            FROM apps a
            LEFT JOIN companies c ON a.company_id = c.id
            WHERE a.is_active = 1 AND a.id IN ($placeholders)
            ORDER BY c.name, a.name
        ");
        $stmt->execute($allowed_app_ids);
    }
    
    $apps = $stmt->fetchAll();
    
    if ($grouped) {
        return group_apps_by_company($apps);
    }
    return $apps;
}

/**
 * Group apps by company for sidebar display
 */
function group_apps_by_company($apps)
{
    $grouped = [];
    foreach ($apps as $app) {
        $company_name = $app['company_name'] ?? 'Sin Empresa';
        $company_id = $app['company_id'] ?? 0;
        
        if (!isset($grouped[$company_id])) {
            $grouped[$company_id] = [
                'id' => $company_id,
                'name' => $company_name,
                'apps' => []
            ];
        }
        $grouped[$company_id]['apps'][] = $app;
    }
    return array_values($grouped);
}

/**
 * Check if user can access a specific app
 */
function can_access_app($app_id)
{
    $user = get_logged_user();
    if (!$user) {
        return false;
    }

    // Superadmins can access all apps
    if ($user['role'] === 'superadmin') {
        return true;
    }

    $db = getDB();
    
    // Get app's company
    $stmtApp = $db->prepare("SELECT company_id FROM apps WHERE id = ?");
    $stmtApp->execute([$app_id]);
    $app = $stmtApp->fetch();
    
    if (!$app) {
        return false;
    }

    // Check if user has specific app permissions
    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM user_app_permissions WHERE user_id = ?");
    $stmtCheck->execute([$user['id']]);
    $has_specific_perms = $stmtCheck->fetchColumn() > 0;

    if ($has_specific_perms) {
        // Specific permissions defined: check if this app is allowed
        $stmt = $db->prepare("
            SELECT can_view 
            FROM user_app_permissions 
            WHERE user_id = ? AND app_id = ? AND can_view = 1
        ");
        $stmt->execute([$user['id'], $app_id]);
        return $stmt->fetch() !== false;
    }

    // No specific permissions: check if app's company is in user's companies
    $user_companies = get_user_companies();
    $user_company_ids = array_column($user_companies, 'id');
    
    return in_array($app['company_id'], $user_company_ids);
}
