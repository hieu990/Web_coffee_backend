<?php
/**
 * Soft Delete Product API — v2
 * Fix #8: Chuyển sang soft-delete (is_deleted = true) thay vì xóa vĩnh viễn
 * Hỗ trợ tham số: { "id": "...", "permanent": false }
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed.']);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Product ID is required.']);
    exit;
}

$id        = $data['id'];
$permanent = isset($data['permanent']) ? (bool)$data['permanent'] : false;

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('products');

    if ($permanent) {
        // Hard delete — only for already soft-deleted items (Trash)
        $result = $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id), 'is_deleted' => true]);
        echo json_encode([
            'success'       => true,
            'message'       => 'Sản phẩm đã bị xóa vĩnh viễn khỏi hệ thống.',
            'deleted_count' => $result->getDeletedCount()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        // Soft delete
        $result = $collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => [
                'is_deleted'  => true,
                'deleted_at'  => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        echo json_encode([
            'success'        => true,
            'message'        => 'Sản phẩm đã được chuyển vào Thùng rác. Có thể khôi phục trong 30 ngày.',
            'modified_count' => $result->getModifiedCount()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    error_log("API Error in menu/delete.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
