<?php
/**
 * Place Order API (Customer)
 * File Path: backend/api/place_order.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (empty($data) || empty($data['items'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Order items cannot be empty.']);
        exit;
    }

    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('orders');

    // Prepare items list safely
    $sanitizedItems = [];
    foreach ($data['items'] as $item) {
        $sanitizedItems[] = [
            'id' => htmlspecialchars($item['id'] ?? '', ENT_QUOTES, 'UTF-8'),
            'name' => htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'price' => (float)($item['price'] ?? 0.0),
            'quantity' => (int)($item['quantity'] ?? 1),
            'category' => htmlspecialchars($item['category'] ?? 'Coffee', ENT_QUOTES, 'UTF-8')
        ];
    }

    $document = [
        'items' => $sanitizedItems,
        'totalPrice' => (float)$data['totalPrice'],
        'customerName' => htmlspecialchars($data['customerName'] ?? 'Vãng lai', ENT_QUOTES, 'UTF-8'),
        'tableNumber' => htmlspecialchars($data['tableNumber'] ?? 'Mang về', ENT_QUOTES, 'UTF-8'),
        'status' => 'Pending', // Pending, Preparing, Completed, Cancelled
        'status_history' => [
            [
                'status' => 'Pending',
                'changed_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ],
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $collection->insertOne($document);
    $insertedId = (string)$result->getInsertedId();

    echo json_encode([
        'success' => true,
        'message' => 'Order successfully placed.',
        'order_id' => $insertedId
    ]);
} catch (Exception $e) {
    error_log("API Error in place_order.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while placing order: ' . $e->getMessage()
    ]);
}
