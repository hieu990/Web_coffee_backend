<?php
/**
 * Retrieve All Shifts API (Admin)
 * File Path: backend/admin/shifts/list.php
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed on this endpoint.']);
    exit;
}

// Load MongoDB connection manager
require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('shifts');

    $cursor = $collection->find([], ['sort' => ['start_time' => -1]]);
    $shifts = [];

    foreach ($cursor as $document) {
        $item = (array) $document;

        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }

        // Format dates
        if (isset($item['start_time']) && $item['start_time'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['start_time'] = $item['start_time']->toDateTime()->format('Y-m-d H:i:s');
        }
        if (isset($item['end_time']) && $item['end_time'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['end_time'] = $item['end_time']->toDateTime()->format('Y-m-d H:i:s');
        }
        if (isset($item['updated_at']) && $item['updated_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['updated_at'] = $item['updated_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        $shifts[] = $item;
    }

    echo json_encode($shifts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in shifts/list.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching shifts: ' . $e->getMessage()
    ]);
}
