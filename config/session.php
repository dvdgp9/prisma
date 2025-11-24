<?php
/**
 * Session Configuration
 * Secure session handling for user authentication
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie parameters before starting session
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 86400,  // 24 hours
        'path' => '/',
        'domain' => $cookieParams['domain'],
        'secure' => false,    // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_name('PRISMA_SESSION');
    session_start();
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
