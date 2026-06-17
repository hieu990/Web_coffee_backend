<?php
/**
 * Update Order Status API (Admin POS)
 * File Path: backend/admin/update_order.php
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

// Require session authentication helper
require_once __DIR__ . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    $id = $data['id'] ?? '';
    $status = $data['status'] ?? '';

    if (empty($id) || empty($status)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Order ID and Status are required.']);
        exit;
    }

    // Validate input status
    $allowedStatuses = ['Pending', 'Preparing', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowedStatuses)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Trạng thái đơn hàng không hợp lệ.']);
        exit;
    }

    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('orders');

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        [
            '$set' => ['status' => $status],
            '$push' => [
                'status_history' => [
                    'status' => $status,
                    'changed_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        ]
    );

    // Auto-deduct raw materials when order is Completed
    if ($status === 'Completed') {
        $order = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        if ($order && isset($order['items'])) {
            $inventoryCol = $db->selectCollection('inventory');
            foreach ($order['items'] as $item) {
                $itemId = (string)($item['id'] ?? '');
                $qty = (int)($item['quantity'] ?? 1);
                
                if (empty($itemId)) continue;

                $ingredients = $inventoryCol->find(['recipe_mappings.product_id' => $itemId])->toArray();
                foreach ($ingredients as $ing) {
                    $deductAmt = 0.0;
                    foreach ($ing['recipe_mappings'] as $mapping) {
                        if ((string)($mapping['product_id'] ?? '') === $itemId) {
                            $deductAmt = (float)($mapping['deduction_amount'] ?? 0.0);
                            break;
                        }
                    }
                    
                    $totalDeduct = $deductAmt * $qty;
                    if ($totalDeduct > 0) {
                        $inventoryCol->updateOne(
                            ['_id' => $ing['_id']],
                            [
                                '$inc' => ['stock_level' => -$totalDeduct],
                                '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                            ]
                        );
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order status successfully updated.',
        'data' => [
            'id' => $id,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    error_log("API Error in update_order.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating status: ' . $e->getMessage()
    ]);
}
