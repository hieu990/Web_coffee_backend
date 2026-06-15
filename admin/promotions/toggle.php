<?php
/**
 * Toggle Promotion Active Status API (Admin)
 * File Path: backend/admin/promotions/toggle.php
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

// Require session authentication helper
require_once dirname(__DIR__) . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Promotion ID is required.']);
    exit;
}

$id = $data['id'];
$is_active = isset($data['is_active']) ? (bool)$data['is_active'] : null;

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('promotions');

    // Find the item first if is_active is not explicitly passed
    if ($is_active === null) {
        $promo = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        if (!$promo) {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy khuyến mãi này.']);
            exit;
        }
        $is_active = !($promo['is_active'] ?? false);
    }

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        [
            '$set' => [
                'is_active' => $is_active,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Trạng thái khuyến mãi đã được cập nhật thành công.',
        'data' => [
            'id' => $id,
            'is_active' => $is_active
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in promotions/toggle.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi cập nhật trạng thái khuyến mãi: ' . $e->getMessage()
    ]);
}
