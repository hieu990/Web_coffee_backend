<?php
/**
 * Retrieve Business Analytics & Statistics (Admin)
 * File Path: backend/admin/get_stats.php
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
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
    
    // 1. Total Orders count
    $ordersCol = $db->selectCollection('orders');
    $totalOrders = $ordersCol->countDocuments([]);

    // 2. Total Revenue (where status is Completed)
    $revenuePipeline = [
        ['$match' => ['status' => 'Completed']],
        ['$group' => ['_id' => null, 'total' => ['$sum' => '$totalPrice']]]
    ];
    $revenueResult = $ordersCol->aggregate($revenuePipeline)->toArray();
    $totalRevenue = !empty($revenueResult) ? (float)$revenueResult[0]['total'] : 0.0;

    // 3. Total Reservations count
    $resCol = $db->selectCollection('reservations');
    $totalReservations = $resCol->countDocuments([]);

    // 4. Top 5 Best-Selling items
    $topItemsPipeline = [
        ['$unwind' => '$items'],
        ['$group' => [
            '_id' => '$items.name',
            'quantity' => ['$sum' => '$items.quantity'],
            'revenue' => ['$sum' => ['$multiply' => ['$items.price', '$items.quantity']]]
        ]],
        ['$sort' => ['quantity' => -1]],
        ['$limit' => 5]
    ];
    $topItemsCursor = $ordersCol->aggregate($topItemsPipeline)->toArray();
    
    $topItems = [];
    foreach ($topItemsCursor as $doc) {
        $topItems[] = [
            'name' => $doc['_id'],
            'quantity' => $doc['quantity'],
            'revenue' => (float)$doc['revenue']
        ];
    }

    echo json_encode([
        'success' => true,
        'totalOrders' => $totalOrders,
        'totalRevenue' => $totalRevenue,
        'totalReservations' => $totalReservations,
        'topItems' => $topItems
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in get_stats.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while calculating statistics: ' . $e->getMessage()
    ]);
}
