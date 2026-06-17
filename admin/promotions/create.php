<?php
/**
 * Create Promotion API (Admin)
 * File Path: backend/admin/promotions/create.php
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
require_once dirname(__DIR__) . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

// Parse raw JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$promotion_type = trim($data['promotion_type'] ?? '');
$title = trim($data['title'] ?? '');

$errors = [];

if (empty($promotion_type) || !in_array($promotion_type, ['FlashSale', 'Voucher'])) {
    $errors[] = 'Loại khuyến mãi không hợp lệ (FlashSale hoặc Voucher).';
}
if (empty($title)) {
    $errors[] = 'Tiêu đề khuyến mãi là bắt buộc.';
}

$document = [
    'promotion_type' => $promotion_type,
    'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
    'is_active' => true,
    'created_at' => new MongoDB\BSON\UTCDateTime()
];

if ($promotion_type === 'FlashSale') {
    $start_date_str = trim($data['start_date'] ?? '');
    $end_date_str = trim($data['end_date'] ?? '');
    $discount_percentage = (float)($data['discount_percentage'] ?? 0.0);

    if (empty($start_date_str) || empty($end_date_str)) {
        $errors[] = 'Thời gian bắt đầu và kết thúc Flash Sale là bắt buộc.';
    }
    if ($discount_percentage <= 0 || $discount_percentage > 100) {
        $errors[] = 'Phần trăm giảm giá phải nằm trong khoảng (0, 100].';
    }

    if (empty($errors)) {
        try {
            $start_dt = new DateTime($start_date_str);
            $end_dt = new DateTime($end_date_str);
            $document['flash_sale_details'] = [
                'start_date' => new MongoDB\BSON\UTCDateTime($start_dt->getTimestamp() * 1000),
                'end_date' => new MongoDB\BSON\UTCDateTime($end_dt->getTimestamp() * 1000),
                'discount_percentage' => $discount_percentage
            ];
        } catch (Exception $e) {
            $errors[] = 'Thời gian bắt đầu hoặc kết thúc không đúng định dạng: ' . $e->getMessage();
        }
    }
} else if ($promotion_type === 'Voucher') {
    $code = strtoupper(trim($data['code'] ?? ''));
    $discount_percentage = (float)($data['discount_percentage'] ?? 0.0);
    $usage_limit = (int)($data['usage_limit'] ?? 1);

    if (empty($code)) {
        $errors[] = 'Mã code voucher là bắt buộc.';
    }
    if ($discount_percentage <= 0 || $discount_percentage > 100) {
        $errors[] = 'Phần trăm giảm giá phải nằm trong khoảng (0, 100].';
    }
    if ($usage_limit <= 0) {
        $errors[] = 'Giới hạn số lần sử dụng phải lớn hơn 0.';
    }

    if (empty($errors)) {
        $document['voucher_details'] = [
            'code' => htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            'discount_percentage' => $discount_percentage,
            'usage_limit' => $usage_limit,
            'usage_count' => 0
        ];
    }
}

if (!empty($errors)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('promotions');

    // For Voucher, make sure code is unique among active ones (optional but good practice)
    if ($promotion_type === 'Voucher') {
        $existing = $collection->findOne([
            'promotion_type' => 'Voucher',
            'voucher_details.code' => $document['voucher_details']['code'],
            'is_active' => true
        ]);
        if ($existing) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['success' => false, 'message' => 'Mã Voucher này hiện đang kích hoạt và tồn tại trên hệ thống.']);
            exit;
        }
    }

    $result = $collection->insertOne($document);
    $insertedId = (string)$result->getInsertedId();

    echo json_encode([
        'success' => true,
        'message' => 'Tạo chương trình khuyến mãi / sự kiện thành công.',
        'data' => [
            'id' => $insertedId,
            'title' => $title,
            'promotion_type' => $promotion_type
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in promotions/create.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi lưu chương trình khuyến mãi: ' . $e->getMessage()
    ]);
}
