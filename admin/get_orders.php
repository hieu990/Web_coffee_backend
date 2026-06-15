<?php
/**
 * Retrieve All Orders API (Admin POS)
 * File Path: backend/admin/get_orders.php
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
    $collection = $db->selectCollection('orders');

    // Retrieve all orders sorted by creation time descending
    $cursor = $collection->find([], ['sort' => ['created_at' => -1]]);

    $orders = [];
    foreach ($cursor as $document) {
        $item = (array) $document;

        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }

        if (isset($item['created_at']) && $item['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['created_at'] = $item['created_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        if (isset($item['status_history'])) {
            $formattedHistory = [];
            foreach ($item['status_history'] as $historyItem) {
                $hItem = (array)$historyItem;
                if (isset($hItem['changed_at']) && $hItem['changed_at'] instanceof MongoDB\BSON\UTCDateTime) {
                    $hItem['changed_at'] = $hItem['changed_at']->toDateTime()->format('Y-m-d H:i:s');
                }
                $formattedHistory[] = $hItem;
            }
            $item['status_history'] = $formattedHistory;
        }

        $orders[] = $item;
    }

    echo json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in get_orders.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching orders: ' . $e->getMessage()
    ]);
}
