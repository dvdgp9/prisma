<?php
/**
 * Authentication Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

/**
 * Attempt to log in a user
 */
function login($username, $password)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT u.*, c.name as company_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE u.username = ? AND u.is_active = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID on login
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['logged_in'] = true;

        return true;
    }

    return false;
}

/**
 * Log out current user
 */
function logout()
{
    $_SESSION = array();

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require login - redirect to login page if not authenticated
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
 * Get apps that the current user has permission to see
 */
function get_user_apps()
{
    $user = get_logged_user();
    if (!$user) {
        return [];
    }

    $db = getDB();

    // Superadmins see all apps
    if ($user['role'] === 'superadmin') {
        $stmt = $db->query("SELECT * FROM apps WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    // Admins see all apps in their company
    if ($user['role'] === 'admin') {
        $stmt = $db->query("SELECT * FROM apps WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    }

    // Regular users only see apps they have permissions for
    $stmt = $db->prepare("
        SELECT DISTINCT a.* 
        FROM apps a
        INNER JOIN user_app_permissions uap ON a.id = uap.app_id
        WHERE uap.user_id = ? AND uap.can_view = 1 AND a.is_active = 1
        ORDER BY a.name
    ");
    $stmt->execute([$user['id']]);
    return $stmt->fetchAll();
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

    // Superadmins and Admins can access all apps
    if (in_array($user['role'], ['superadmin', 'admin'])) {
        return true;
    }

    // Check if user has permission
    $db = getDB();
    $stmt = $db->prepare("
        SELECT can_view 
        FROM user_app_permissions 
        WHERE user_id = ? AND app_id = ? AND can_view = 1
    ");
    $stmt->execute([$user['id'], $app_id]);
    return $stmt->fetch() !== false;
}
