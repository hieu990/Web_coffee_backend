<?php
/**
 * Add Event API (Admin)
 * File Path: backend/admin/add_event.php
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
require_once __DIR__ . '/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed on this endpoint.'
    ]);
    exit;
}

// Load database connection
require_once dirname(__DIR__) . '/config/database.php';

// Retrieve request body (support both JSON payload and standard Form POST)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$title = '';
$description = '';
$date = '';
$image_url = '';

if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    $date = $data['date'] ?? '';
    $image_url = $data['image_url'] ?? '';
} else {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? '';
    $image_url = $_POST['image_url'] ?? '';
}

// 1. Input Sanitization & Validation
$title = trim(filter_var($title, FILTER_DEFAULT));
$description = trim(filter_var($description, FILTER_DEFAULT));
$date = trim(filter_var($date, FILTER_DEFAULT));
$image_url = trim(filter_var($image_url, FILTER_DEFAULT));

$errors = [];

if (empty($title)) {
    $errors[] = 'Tiêu đề sự kiện là bắt buộc.';
}
if (empty($description)) {
    $errors[] = 'Mô tả sự kiện là bắt buộc.';
}
if (empty($date)) {
    $errors[] = 'Ngày diễn ra sự kiện là bắt buộc.';
} else {
    // Basic date format validation (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'Định dạng ngày không hợp lệ. Vui lòng dùng định dạng YYYY-MM-DD.';
    }
}

if (!empty($errors)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'errors' => $errors
    ]);
    exit;
}

// HTML encode inputs to prevent XSS when displaying them later
$sanitizedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
$sanitizedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
$sanitizedImageUrl = htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8');

try {
    // Connect to database
    $db = (new Config\Database())->getConnection();

    // Insert statement
    $stmt = $db->prepare('INSERT INTO events (title, description, date, image_url) VALUES (:title, :description, :date, :image_url)');
    $stmt->execute([
        ':title' => $sanitizedTitle,
        ':description' => $sanitizedDescription,
        ':date' => $date,
        ':image_url' => !empty($sanitizedImageUrl) ? $sanitizedImageUrl : null
    ]);

    $insertedId = $db->lastInsertId();

    // Respond with success
    echo json_encode([
        'success' => true,
        'message' => 'Sự kiện khuyến mãi đã được đăng thành công.',
        'data' => [
            'id' => $insertedId,
            'title' => $sanitizedTitle,
            'description' => $sanitizedDescription,
            'date' => $date,
            'image_url' => $sanitizedImageUrl
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in add_event.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi lưu sự kiện: ' . $e->getMessage()
    ]);
}
