<?php
/**
 * Update Shift API (Admin)
 * File Path: backend/admin/shifts/update.php
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

if (!$data || empty($data['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Shift ID is required.']);
    exit;
}

$id = $data['id'];

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('shifts');

    $updateFields = [];

    if (isset($data['shift_type'])) {
        $st = trim($data['shift_type']);
        if (in_array($st, ['morning', 'afternoon', 'night'])) {
            $updateFields['shift_type'] = $st;
        }
    }
    if (isset($data['status'])) {
        $status = trim($data['status']);
        if (in_array($status, ['Scheduled', 'Completed', 'Swapped', 'Cancelled'])) {
            $updateFields['status'] = $status;
        }
    }
    if (isset($data['notes'])) {
        $updateFields['notes'] = htmlspecialchars(trim($data['notes']), ENT_QUOTES, 'UTF-8');
    }
    if (isset($data['hourly_rate'])) {
        $updateFields['hourly_rate'] = (float)$data['hourly_rate'];
    }
    if (isset($data['start_time'])) {
        $start_dt = new DateTime(trim($data['start_time']));
        $updateFields['start_time'] = new MongoDB\BSON\UTCDateTime($start_dt->getTimestamp() * 1000);
    }
    if (isset($data['end_time'])) {
        $end_dt = new DateTime(trim($data['end_time']));
        $updateFields['end_time'] = new MongoDB\BSON\UTCDateTime($end_dt->getTimestamp() * 1000);
    }

    $updateFields['updated_at'] = new MongoDB\BSON\UTCDateTime();

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        ['$set' => $updateFields]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Lịch làm việc đã được cập nhật thành công.',
        'matched_count' => $result->getMatchedCount(),
        'modified_count' => $result->getModifiedCount()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in shifts/update.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi cập nhật ca làm việc: ' . $e->getMessage()
    ]);
}
