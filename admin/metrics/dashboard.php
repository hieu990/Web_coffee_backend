<?php
/**
 * Financial Metrics & Business Analytics API — v2
 * Fix #3: Hỗ trợ lọc theo khoảng ngày (?from=YYYY-MM-DD&to=YYYY-MM-DD)
 * Fix #7: COGS tính theo cost_price thực từng sản phẩm (fallback 35% nếu chưa nhập)
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

require_once dirname(__DIR__) . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only GET requests are allowed.']);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

try {
    $db = DatabaseConnection::getConnection();
    $ordersCol = $db->selectCollection('orders');
    $metricsCol = $db->selectCollection('metrics');
    $productsCol = $db->selectCollection('products');

    // --- Date range filter ---
    $fromDate = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : null;
    $toDate   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : null;

    $matchFilter = ['status' => 'Completed'];
    if ($fromDate || $toDate) {
        $dateRange = [];
        if ($fromDate) {
            $dt = new DateTime($fromDate . ' 00:00:00');
            $dateRange['$gte'] = new MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000);
        }
        if ($toDate) {
            $dt = new DateTime($toDate . ' 23:59:59');
            $dateRange['$lte'] = new MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000);
        }
        $matchFilter['created_at'] = $dateRange;
    }

    // 1. Aggregate Total Revenue
    $revenuePipeline = [
        ['$match' => $matchFilter],
        ['$group' => ['_id' => null, 'total' => ['$sum' => '$totalPrice']]]
    ];
    $revenueResult = $ordersCol->aggregate($revenuePipeline)->toArray();
    $totalRevenue = !empty($revenueResult) ? (float)$revenueResult[0]['total'] : 0.0;

    // 2. COGS — try to calculate from actual cost_price per item, fallback to 35%
    $cogsPipeline = [
        ['$match' => $matchFilter],
        ['$unwind' => '$items'],
        ['$group' => [
            '_id' => '$items.name',
            'quantity' => ['$sum' => '$items.quantity'],
            'revenue'  => ['$sum' => ['$multiply' => ['$items.price', '$items.quantity']]]
        ]]
    ];
    $cogsItems = $ordersCol->aggregate($cogsPipeline)->toArray();

    // Build a product cost_price lookup map
    $productCosts = [];
    $allProducts = $productsCol->find([], ['projection' => ['name' => 1, 'cost_price' => 1]])->toArray();
    foreach ($allProducts as $p) {
        $productCosts[(string)($p['name'] ?? '')] = isset($p['cost_price']) ? (float)$p['cost_price'] : null;
    }

    $totalCostOfGoods = 0.0;
    foreach ($cogsItems as $ci) {
        $itemName = $ci['_id'];
        $qty = (int)($ci['quantity'] ?? 0);
        $costPerUnit = $productCosts[$itemName] ?? null;
        if ($costPerUnit !== null && $costPerUnit > 0) {
            $totalCostOfGoods += $costPerUnit * $qty;
        } else {
            // fallback: 35% of that item's revenue
            $totalCostOfGoods += ((float)($ci['revenue'] ?? 0)) * 0.35;
        }
    }
    $profitMargin = $totalRevenue - $totalCostOfGoods;

    // 3. Top 5 Best-Selling Items
    $topItemsPipeline = [
        ['$match' => $matchFilter],
        ['$unwind' => '$items'],
        ['$group' => [
            '_id' => '$items.name',
            'quantity' => ['$sum' => '$items.quantity'],
            'revenue'  => ['$sum' => ['$multiply' => ['$items.price', '$items.quantity']]]
        ]],
        ['$sort' => ['quantity' => -1]],
        ['$limit' => 5]
    ];
    $topItemsCursor = $ordersCol->aggregate($topItemsPipeline)->toArray();
    $topItems = [];
    foreach ($topItemsCursor as $doc) {
        $topItems[] = [
            'name'     => $doc['_id'],
            'quantity' => $doc['quantity'],
            'revenue'  => (float)$doc['revenue']
        ];
    }

    // 4. Revenue by day (for mini chart) — last 30 days or within range
    $chartPipeline = [
        ['$match' => $matchFilter],
        ['$group' => [
            '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at']],
            'revenue' => ['$sum' => '$totalPrice'],
            'orders'  => ['$sum' => 1]
        ]],
        ['$sort' => ['_id' => 1]],
        ['$limit' => 30]
    ];
    $chartCursor = $ordersCol->aggregate($chartPipeline)->toArray();
    $chartData = [];
    foreach ($chartCursor as $cd) {
        $chartData[] = [
            'date'    => $cd['_id'],
            'revenue' => (float)$cd['revenue'],
            'orders'  => (int)$cd['orders']
        ];
    }

    // 5. Quick counts
    $resCol   = $db->selectCollection('reservations');
    $totalReservations = $resCol->countDocuments([]);
    $totalOrders       = $ordersCol->countDocuments($matchFilter);

    // 6. Store/update metrics snapshot for current month (only when no filter)
    if (!$fromDate && !$toDate) {
        $currentMonth = date('Y-m');
        $metricsCol->updateOne(
            ['date' => $currentMonth],
            ['$set' => [
                'total_revenue'       => $totalRevenue,
                'total_cost_of_goods' => $totalCostOfGoods,
                'profit_margin'       => $profitMargin,
                'top_selling_items'   => $topItems,
                'updated_at'          => new MongoDB\BSON\UTCDateTime()
            ]],
            ['upsert' => true]
        );
    }

    echo json_encode([
        'success' => true,
        'filter'  => ['from' => $fromDate, 'to' => $toDate],
        'current_metrics' => [
            'total_revenue'       => $totalRevenue,
            'total_cost_of_goods' => $totalCostOfGoods,
            'profit_margin'       => $profitMargin,
            'top_selling_items'   => $topItems
        ],
        'chart_data'         => $chartData,
        'totalOrders'        => $totalOrders,
        'totalReservations'  => $totalReservations
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in metrics/dashboard.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
