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

    // Admins and regular users see apps in their company
    if ($user['role'] === 'admin' || $user['role'] === 'user') {
        // Check if user has specific app permissions
        $stmtPerms = $db->prepare("SELECT app_id FROM user_app_permissions WHERE user_id = ? AND can_view = 1");
        $stmtPerms->execute([$user['id']]);
        $allowed_app_ids = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

        if (empty($allowed_app_ids)) {
            // No specific permissions defined: see all apps in company
            $stmt = $db->prepare("
                SELECT * FROM apps 
                WHERE is_active = 1 AND company_id = ?
                ORDER BY name
            ");
            $stmt->execute([$user['company_id']]);
            return $stmt->fetchAll();
        } else {
            // Specific permissions defined: filter apps
            $placeholders = implode(',', array_fill(0, count($allowed_app_ids), '?'));
            $stmt = $db->prepare("
                SELECT * FROM apps 
                WHERE is_active = 1 AND id IN ($placeholders)
                ORDER BY name
            ");
            $stmt->execute($allowed_app_ids);
            return $stmt->fetchAll();
        }
    }

    return [];
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

    // Superadmins and Admins can access all apps in their company
    if (in_array($user['role'], ['superadmin', 'admin'])) {
        return true;
    }

    // For regular users, check if they have specific permissions
    $db = getDB();
    
    // Check if any permissions are defined for this user
    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM user_app_permissions WHERE user_id = ?");
    $stmtCheck->execute([$user['id']]);
    $has_specific_perms = $stmtCheck->fetchColumn() > 0;

    if (!$has_specific_perms) {
        // No permissions defined: can access any app in their company
        $stmtApp = $db->prepare("SELECT company_id FROM apps WHERE id = ?");
        $stmtApp->execute([$app_id]);
        $app = $stmtApp->fetch();
        return $app && $app['company_id'] == $user['company_id'];
    }

    // Specific permissions defined: check if this app is allowed
    $stmt = $db->prepare("
        SELECT can_view 
        FROM user_app_permissions 
        WHERE user_id = ? AND app_id = ? AND can_view = 1
    ");
    $stmt->execute([$user['id'], $app_id]);
    return $stmt->fetch() !== false;
}
