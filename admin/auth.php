<?php
/**
 * Session verification helper for admin routes.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Enforce authentication. Restricts access to logged-in admin users.
 */
function require_auth(): void {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Detect if the request expects a JSON response
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentTypeHeader = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (
            strpos($acceptHeader, 'application/json') !== false || 
            strpos($contentTypeHeader, 'application/json') !== false
        ) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized access. Session expired or missing admin credentials.'
            ]);
            exit;
        } else {
            // Redirect page requests to login page
            header('Location: login.php');
            exit;
        }
    }
}
