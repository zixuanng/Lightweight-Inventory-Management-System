<?php
// config/auth.php - Session control & RBAC Middleware

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Return current authenticated user array or null
 */
function get_logged_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if a user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

/**
 * Check if the logged-in user is an Admin
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['user']['role'] === 'Admin');
}

/**
 * Middleware: Enforce user authentication for API endpoints
 */
function require_login() {
    if (!is_logged_in()) {
        json_response(['error' => 'Authentication required. Please log in.'], 401);
        exit;
    }
}

/**
 * Middleware: Enforce Admin role for protected API actions
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        json_response(['error' => 'Permission denied. Admin privileges required.'], 403);
        exit;
    }
}

/**
 * Helper to output JSON response with HTTP status code
 */
function json_response($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Helper to parse raw JSON payload from HTTP request body
 */
function get_json_input() {
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        return $_POST;
    }
    $decoded = json_decode($rawInput, true);
    return is_array($decoded) ? $decoded : $_POST;
}
