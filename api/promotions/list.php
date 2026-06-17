<?php
/**
 * Retrieve Active Promotions API (Public/Client)
 * File Path: backend/api/promotions/list.php
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

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

try {
    $db = DatabaseConnection::getConnection();
    $promotionsCol = $db->selectCollection('promotions');

    // Fetch only active promotions
    $cursor = $promotionsCol->find(['is_active' => true], ['sort' => ['created_at' => -1]])->toArray();
    $promotions = [];

    foreach ($cursor as $document) {
        $item = (array) $document;
        $item['_id'] = (string)$item['_id'];

        if (isset($item['created_at']) && $item['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['created_at'] = $item['created_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        if ($item['promotion_type'] === 'FlashSale' && isset($item['flash_sale_details'])) {
            $details = (array)$item['flash_sale_details'];
            if (isset($details['start_date']) && $details['start_date'] instanceof MongoDB\BSON\UTCDateTime) {
                $details['start_date'] = $details['start_date']->toDateTime()->format('Y-m-d H:i:s');
            }
            if (isset($details['end_date']) && $details['end_date'] instanceof MongoDB\BSON\UTCDateTime) {
                $details['end_date'] = $details['end_date']->toDateTime()->format('Y-m-d H:i:s');
            }
            $item['flash_sale_details'] = $details;
        }

        $promotions[] = $item;
    }

    echo json_encode($promotions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in api/promotions/list.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching active promotions: ' . $e->getMessage()
    ]);
}
