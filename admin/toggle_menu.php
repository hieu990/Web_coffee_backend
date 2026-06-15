<?php
/**
 * Toggle Product Availability API
 * File Path: backend/admin/toggle_menu.php
 */

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

// Require session authentication helper
require_once __DIR__ . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$id = $data['id'] ?? '';
$available = isset($data['available']) ? (bool)$data['available'] : true;

if (empty($id)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Product ID is required.']);
    exit;
}

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('menu');

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['available' => $available]]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Product availability status successfully updated.',
        'data' => [
            'id' => $id,
            'available' => $available
        ]
    ]);
} catch (Exception $e) {
    error_log("API Error in toggle_menu.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating status: ' . $e->getMessage()
    ]);
}
