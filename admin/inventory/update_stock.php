<?php
/**
 * Update Inventory Stock Level API — v2
 * Fix #9: Hỗ trợ mode "add" (cộng thêm) hoặc "set" (ghi đè tuyệt đối)
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
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Material ID is required.']);
    exit;
}

$id   = $data['id'];
$mode = $data['mode'] ?? 'set'; // 'set' | 'add'

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('inventory');

    $setFields = ['updated_at' => new MongoDB\BSON\UTCDateTime()];
    $incFields = [];
    $mongoUpdate = [];

    if ($mode === 'add' && isset($data['add_amount'])) {
        // Add to existing stock
        $addAmount = (float)$data['add_amount'];
        $incFields['stock_level'] = $addAmount;
    } elseif (isset($data['stock_level'])) {
        // Overwrite stock level
        $setFields['stock_level'] = (float)$data['stock_level'];
    }

    if (isset($data['low_stock_threshold'])) {
        $setFields['low_stock_threshold'] = (float)$data['low_stock_threshold'];
    }
    if (isset($data['ingredient_name'])) {
        $setFields['ingredient_name'] = htmlspecialchars(trim($data['ingredient_name']), ENT_QUOTES, 'UTF-8');
    }

    $mongoUpdate['$set'] = $setFields;
    if (!empty($incFields)) {
        $mongoUpdate['$inc'] = $incFields;
    }

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        $mongoUpdate
    );

    // Return updated document
    $updated = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    $newStockLevel = $updated ? (float)($updated['stock_level'] ?? 0) : null;

    echo json_encode([
        'success'         => true,
        'message'         => $mode === 'add' ? 'Đã nhập thêm nguyên liệu vào kho.' : 'Tồn kho đã được cập nhật.',
        'mode'            => $mode,
        'new_stock_level' => $newStockLevel,
        'modified_count'  => $result->getModifiedCount()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in inventory/update_stock.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
