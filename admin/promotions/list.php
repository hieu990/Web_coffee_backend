<?php
/**
 * Retrieve All Promotions API — v2
 * Fix #6: Auto-deactivate expired Flash Sales when listing
 */

header('Access-Control-Allow-Origin: http://localhost:5173');
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
    $promotionsCol = $db->selectCollection('promotions');

    $now = new MongoDB\BSON\UTCDateTime();

    // Auto-deactivate Flash Sales that have passed their end_date
    $promotionsCol->updateMany(
        [
            'promotion_type' => 'FlashSale',
            'is_active'      => true,
            'flash_sale_details.end_date' => ['$lt' => $now]
        ],
        ['$set' => ['is_active' => false, 'auto_expired' => true]]
    );

    $cursor = $promotionsCol->find([], ['sort' => ['created_at' => -1]])->toArray();
    $promotions = [];

    foreach ($cursor as $document) {
        $item = (array) $document;
        $item['_id'] = (string)$item['_id'];

        if (isset($item['created_at']) && $item['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $item['created_at'] = $item['created_at']->toDateTime()->format('Y-m-d H:i:s');
        }

        if ($item['promotion_type'] === 'FlashSale' && isset($item['flash_sale_details'])) {
            $details = (array)$item['flash_sale_details'];
            $endDt = null;
            if (isset($details['start_date']) && $details['start_date'] instanceof MongoDB\BSON\UTCDateTime) {
                $startDt = $details['start_date']->toDateTime();
                $details['start_date'] = $startDt->format('Y-m-d H:i:s');
            }
            if (isset($details['end_date']) && $details['end_date'] instanceof MongoDB\BSON\UTCDateTime) {
                $endDt = $details['end_date']->toDateTime();
                $details['end_date'] = $endDt->format('Y-m-d H:i:s');
            }
            // Calculate remaining time
            $nowPhp = new DateTime();
            if ($endDt && $endDt > $nowPhp) {
                $diff = $nowPhp->diff($endDt);
                $details['time_remaining'] = $diff->days . 'n ' . $diff->h . 'g ' . $diff->i . 'p';
                $details['is_expired'] = false;
            } else {
                $details['time_remaining'] = 'Đã kết thúc';
                $details['is_expired'] = true;
            }
            $item['flash_sale_details'] = $details;
        }

        // Voucher expiry check
        if ($item['promotion_type'] === 'Voucher' && isset($item['usage_count']) && isset($item['usage_limit'])) {
            $item['usage_percent'] = $item['usage_limit'] > 0
                ? round(($item['usage_count'] / $item['usage_limit']) * 100)
                : 0;
        }

        $promotions[] = $item;
    }

    echo json_encode($promotions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("API Error in promotions/list.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
