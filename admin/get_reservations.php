<?php
/**
 * Retrieve All Reservations API (Admin)
 * File Path: backend/admin/get_reservations.php
 */

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

// Require session authentication helper
require_once __DIR__ . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('reservations');

    // Retrieve reservations sorted by creation time descending
    $cursor = $collection->find([], ['sort' => ['created_at' => -1]]);

    $reservations = [];
    foreach ($cursor as $document) {
        $item = (array) $document;

        // Cast ObjectId to string
        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }

        // Format MongoDB UTCDateTime to formatted string
        if (isset($item['created_at']) && $item['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['created_at'] = $item['created_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        $reservations[] = $item;
    }

    echo json_encode($reservations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in get_reservations.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching reservations: ' . $e->getMessage()
    ]);
}
