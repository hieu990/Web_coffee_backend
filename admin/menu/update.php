<?php
/**
 * Update Product API (Admin)
 * File Path: backend/admin/menu/update.php
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
    echo json_encode(['success' => false, 'message' => 'Product ID is required.']);
    exit;
}

$id = $data['id'];

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('products');

    // Build update criteria
    $updateFields = [];

    if (isset($data['name'])) {
        $updateFields['name'] = htmlspecialchars(trim($data['name']), ENT_QUOTES, 'UTF-8');
    }
    if (isset($data['price'])) {
        $updateFields['price'] = (float)$data['price'];
    }
    if (isset($data['description'])) {
        $updateFields['description'] = htmlspecialchars(trim($data['description']), ENT_QUOTES, 'UTF-8');
    }
    if (isset($data['category'])) {
        $updateFields['category'] = htmlspecialchars(trim($data['category']), ENT_QUOTES, 'UTF-8');
    }
    if (isset($data['image_url'])) {
        $updateFields['image_url'] = htmlspecialchars(trim($data['image_url']), ENT_QUOTES, 'UTF-8');
    }
    if (isset($data['is_in_stock'])) {
        $updateFields['is_in_stock'] = (bool)$data['is_in_stock'];
    }
    if (isset($data['sizes'])) {
        $updateFields['sizes'] = array_map(function($sz) {
            return [
                'size' => htmlspecialchars($sz['size'] ?? '', ENT_QUOTES, 'UTF-8'),
                'upcharge' => (float)($sz['upcharge'] ?? 0.0)
            ];
        }, $data['sizes']);
    }
    if (isset($data['ice_levels'])) {
        $updateFields['ice_levels'] = array_map(function($ice) {
            return htmlspecialchars($ice, ENT_QUOTES, 'UTF-8');
        }, $data['ice_levels']);
    }
    if (isset($data['toppings'])) {
        $updateFields['toppings'] = array_map(function($top) {
            return [
                'name' => htmlspecialchars($top['name'] ?? '', ENT_QUOTES, 'UTF-8'),
                'price' => (float)($top['price'] ?? 0.0)
            ];
        }, $data['toppings']);
    }

    if (empty($updateFields)) {
        echo json_encode(['success' => true, 'message' => 'No fields updated.']);
        exit;
    }

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        ['$set' => $updateFields]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Sản phẩm đã được cập nhật thành công.',
        'matched_count' => $result->getMatchedCount(),
        'modified_count' => $result->getModifiedCount()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in backend/admin/menu/update.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi cập nhật sản phẩm: ' . $e->getMessage()
    ]);
}
