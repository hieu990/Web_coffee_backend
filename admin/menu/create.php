<?php
/**
 * Create Product API (Admin)
 * File Path: backend/admin/menu/create.php
 */

header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed on this endpoint.'
    ]);
    exit;
}

// Load MongoDB connection manager
require_once dirname(dirname(__DIR__)) . '/config/db_connect.php';

// Parse raw JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

// Extract fields
$name = trim($data['name'] ?? '');
$price = (float)($data['price'] ?? 0.0);
$description = trim($data['description'] ?? '');
$category = trim($data['category'] ?? '');
$image_url = trim($data['image_url'] ?? '');
$is_in_stock = (bool)($data['is_in_stock'] ?? true);

// Customizations (Arrays)
$sizes = $data['sizes'] ?? []; // Expected: [ { "size": "S", "upcharge": 0 }, ... ]
$ice_levels = $data['ice_levels'] ?? []; // Expected: ["0%", "50%", "100%"]
$toppings = $data['toppings'] ?? []; // Expected: [ { "name": "Pearl", "price": 5000 }, ... ]

// Validations
$errors = [];
if (empty($name)) {
    $errors[] = 'Tên sản phẩm là bắt buộc.';
}
if ($price <= 0) {
    $errors[] = 'Đơn giá cơ bản phải lớn hơn 0.';
}
if (empty($category)) {
    $errors[] = 'Danh mục sản phẩm là bắt buộc.';
}

if (!empty($errors)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('products');

    // Build product document
    $document = [
      'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
      'price' => (float)$price,
      'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
      'category' => htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
      'image_url' => htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8'),
      'sizes' => array_map(function($sz) {
          return [
              'size' => htmlspecialchars($sz['size'] ?? '', ENT_QUOTES, 'UTF-8'),
              'upcharge' => (float)($sz['upcharge'] ?? 0.0)
          ];
      }, $sizes),
      'ice_levels' => array_map(function($ice) {
          return htmlspecialchars($ice, ENT_QUOTES, 'UTF-8');
      }, $ice_levels),
      'toppings' => array_map(function($top) {
          return [
              'name' => htmlspecialchars($top['name'] ?? '', ENT_QUOTES, 'UTF-8'),
              'price' => (float)($top['price'] ?? 0.0)
          ];
      }, $toppings),
      'is_in_stock' => (bool)$is_in_stock,
      'created_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $collection->insertOne($document);
    $insertedId = (string)$result->getInsertedId();

    echo json_encode([
        'success' => true,
        'message' => 'Sản phẩm đã được tạo thành công.',
        'data' => array_merge(['id' => $insertedId], $document)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in backend/admin/menu/create.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi tạo sản phẩm: ' . $e->getMessage()
    ]);
}
