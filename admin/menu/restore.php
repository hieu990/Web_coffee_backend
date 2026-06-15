<?php
/**
 * Restore Soft-Deleted Product API
 * Fix #8: Khôi phục sản phẩm từ Thùng rác
 */

header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

require_once dirname(__DIR__) . '/auth.php';
require_auth();

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Product ID is required.']);
    exit;
}

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('products');

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($data['id'])],
        ['$unset' => ['is_deleted' => '', 'deleted_at' => '']]
    );

    echo json_encode([
        'success'        => true,
        'message'        => 'Sản phẩm đã được khôi phục vào thực đơn.',
        'modified_count' => $result->getModifiedCount()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in menu/restore.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
