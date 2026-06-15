<?php
/**
 * Connection Test Endpoint
 * File Path: backend/test.php
 */

// CORS Headers for React Frontend compatibility
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Backend is connected!'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
