<?php
/**
 * Moderate Testimonial API (Admin)
 * File Path: backend/admin/update_testimonial.php
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
    $action = $data['action'] ?? ''; // 'approve' or 'delete'

    if (empty($id) || empty($action)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Testimonial ID and Action ('approve'/'delete') are required.']);
        exit;
    }

    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('testimonials');

    if ($action === 'approve') {
        $result = $collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => ['status' => 'Approved']]
        );
        echo json_encode(['success' => true, 'message' => 'Testimonial successfully approved.']);
    } elseif ($action === 'delete') {
        $result = $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        echo json_encode(['success' => true, 'message' => 'Testimonial successfully deleted.']);
    } else {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Unsupported moderation action.']);
    }
} catch (Exception $e) {
    error_log("API Error in update_testimonial.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during moderation: ' . $e->getMessage()
    ]);
}
