<?php
/**
 * Delete Shift API (Admin)
 * File Path: backend/admin/shifts/delete.php
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
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
require_once dirname(__DIR__) . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed on this endpoint.'
    ]);
    exit;
}

// Load MongoDB connection manager
require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

// Parse raw JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Shift ID is required.']);
    exit;
}

$id = $data['id'];

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('shifts');

    $result = $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);

    echo json_encode([
        'success' => true,
        'message' => 'Đã xóa ca làm việc khỏi lịch trình.',
        'deleted_count' => $result->getDeletedCount()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in shifts/delete.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi xóa ca làm việc: ' . $e->getMessage()
    ]);
}
