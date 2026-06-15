<?php
/**
 * Retrieve Inventory List API (Admin)
 * File Path: backend/admin/inventory/list.php
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
    $inventoryCol = $db->selectCollection('inventory');

    $count = $inventoryCol->countDocuments([]);
    if ($count === 0) {
        // Auto-seed sample materials linked to existing products
        $productsCol = $db->selectCollection('products');
        $sampleProducts = $productsCol->find([], ['limit' => 3])->toArray();

        $prod1Id = isset($sampleProducts[0]) ? (string)$sampleProducts[0]['_id'] : '666dadbeef00000000000001';
        $prod2Id = isset($sampleProducts[1]) ? (string)$sampleProducts[1]['_id'] : '666dadbeef00000000000002';
        $prod3Id = isset($sampleProducts[2]) ? (string)$sampleProducts[2]['_id'] : '666dadbeef00000000000003';

        $seeds = [
            [
                'ingredient_name' => 'Hạt Cà Phê Robusta (Coffee Beans)',
                'stock_level' => 5000.0,
                'unit' => 'g',
                'low_stock_threshold' => 1000.0,
                'recipe_mappings' => [
                    ['product_id' => $prod1Id, 'deduction_amount' => 18.0],
                    ['product_id' => $prod2Id, 'deduction_amount' => 18.0]
                ],
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'ingredient_name' => 'Sữa Tươi Thanh Trùng (Fresh Milk)',
                'stock_level' => 3000.0,
                'unit' => 'ml',
                'low_stock_threshold' => 800.0,
                'recipe_mappings' => [
                    ['product_id' => $prod2Id, 'deduction_amount' => 120.0],
                    ['product_id' => $prod3Id, 'deduction_amount' => 150.0]
                ],
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            [
                'ingredient_name' => 'Ly Cà Phê Takeaway (Cups)',
                'stock_level' => 120.0,
                'unit' => 'cái',
                'low_stock_threshold' => 30.0,
                'recipe_mappings' => [
                    ['product_id' => $prod1Id, 'deduction_amount' => 1.0],
                    ['product_id' => $prod2Id, 'deduction_amount' => 1.0],
                    ['product_id' => $prod3Id, 'deduction_amount' => 1.0]
                ],
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ];

        $inventoryCol->insertMany($seeds);
    }

    $cursor = $inventoryCol->find([])->toArray();
    $inventory = [];

    foreach ($cursor as $document) {
        $item = (array) $document;
        $item['_id'] = (string)$item['_id'];
        
        if (isset($item['updated_at']) && $item['updated_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['updated_at'] = $item['updated_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        // Add warning status
        $item['low_stock_warning'] = ($item['stock_level'] <= $item['low_stock_threshold']);
        $inventory[] = $item;
    }

    echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in inventory/list.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching inventory: ' . $e->getMessage()
    ]);
}
