<?php
session_start();

// Define CSRF token lifetime in seconds (e.g., 1 hour)
define('CSRF_TOKEN_LIFE', 3600);

/**
 * Generate a new CSRF token and store it in the session.
 *
 * @return string The generated CSRF token.
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token']) || time() - ($_SESSION['csrf_token_time'] ?? 0) > CSRF_TOKEN_LIFE) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token.
 *
 * @param string $token The token submitted by the user (e.g., from a form).
 * @return bool True if valid and not expired, false otherwise.
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']) &&
           hash_equals($_SESSION['csrf_token'], $token) &&
           time() - $_SESSION['csrf_token_time'] < CSRF_TOKEN_LIFE;
}

/**
 * Check if the current user has admin privileges.
 *
 * @return bool True if the user is an admin, false otherwise.
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Optional: Regenerate session ID on login or privilege elevation.
 */
function secureSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
}
