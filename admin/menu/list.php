<?php
/**
 * Retrieve All Products API (Admin)
 * File Path: backend/admin/menu/list.php
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
    $collection = $db->selectCollection('products');

    // ?view=trash returns deleted items; default returns active items only
    $viewTrash = isset($_GET['view']) && $_GET['view'] === 'trash';
    $filter = $viewTrash
        ? ['is_deleted' => true]
        : ['$or' => [['is_deleted' => false], ['is_deleted' => ['$exists' => false]]]];

    $cursor = $collection->find($filter, ['sort' => ['created_at' => -1]]);
    $products = [];

    foreach ($cursor as $document) {
        $item = (array) $document;

        // Cast ObjectId to string
        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }

        // Format created_at date
        if (isset($item['created_at']) && $item['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['created_at'] = $item['created_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        $products[] = $item;
    }

    echo json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in backend/admin/menu/list.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching products: ' . $e->getMessage()
    ]);
}
