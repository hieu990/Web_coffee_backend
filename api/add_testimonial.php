<?php
/**
 * Submit Testimonial API (Customer)
 * File Path: backend/api/add_testimonial.php
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

    if (empty($data) || empty($data['author']) || empty($data['text'])) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Author name and review text are required.']);
        exit;
    }

    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('testimonials');

    $document = [
        'author' => htmlspecialchars($data['author'], ENT_QUOTES, 'UTF-8'),
        'role' => htmlspecialchars($data['role'] ?? 'Khách hàng', ENT_QUOTES, 'UTF-8'),
        'text' => htmlspecialchars($data['text'], ENT_QUOTES, 'UTF-8'),
        'rating' => (int)($data['rating'] ?? 5),
        'status' => 'Pending', // Pending, Approved
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $collection->insertOne($document);
    $insertedId = (string)$result->getInsertedId();

    echo json_encode([
        'success' => true,
        'message' => 'Cảm ơn đánh giá của bạn! Đánh giá đang được gửi duyệt bởi quản trị viên.',
        'testimonial_id' => $insertedId
    ]);
} catch (Exception $e) {
    error_log("API Error in add_testimonial.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving feedback: ' . $e->getMessage()
    ]);
}
