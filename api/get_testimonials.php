<?php
/**
 * Retrieve Approved Testimonials API (Public)
 * File Path: backend/api/get_testimonials.php
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('testimonials');

    // Fetch approved testimonials only
    $cursor = $collection->find(['status' => 'Approved'], ['sort' => ['created_at' => -1]]);

    $testimonials = [];
    foreach ($cursor as $document) {
        $item = (array) $document;

        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }

        if (isset($item['created_at']) && $item['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['created_at'] = $item['created_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        $testimonials[] = $item;
    }

    echo json_encode($testimonials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in get_testimonials.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching feedback: ' . $e->getMessage()
    ]);
}
