<?php
/**
 * Create Shift API (Admin)
 * File Path: backend/admin/shifts/create.php
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

$employee_name = trim($data['employee_name'] ?? '');
$shift_type = trim($data['shift_type'] ?? 'morning');
$start_time_str = trim($data['start_time'] ?? '');
$end_time_str = trim($data['end_time'] ?? '');
$hourly_rate = (float)($data['hourly_rate'] ?? 20000.0);
$notes = trim($data['notes'] ?? '');

$errors = [];
if (empty($employee_name)) {
    $errors[] = 'Tên nhân viên là bắt buộc.';
}
if (!in_array($shift_type, ['morning', 'afternoon', 'night'])) {
    $errors[] = 'Ca làm việc không hợp lệ ( morning, afternoon, night ).';
}
if (empty($start_time_str) || empty($end_time_str)) {
    $errors[] = 'Thời gian bắt đầu và kết thúc ca là bắt buộc.';
}

if (!empty($errors)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('shifts');

    // Parse datetimes to Mongo UTCDateTime
    $start_dt = new DateTime($start_time_str);
    $end_dt = new DateTime($end_time_str);
    
    $document = [
        'employee_id' => bin2hex(random_bytes(8)), // Simulate an employee ID or use mock
        'employee_name' => htmlspecialchars($employee_name, ENT_QUOTES, 'UTF-8'),
        'shift_type' => $shift_type,
        'start_time' => new MongoDB\BSON\UTCDateTime($start_dt->getTimestamp() * 1000),
        'end_time' => new MongoDB\BSON\UTCDateTime($end_dt->getTimestamp() * 1000),
        'hourly_rate' => $hourly_rate,
        'status' => 'Scheduled', // Scheduled, Completed, Swapped, Cancelled
        'notes' => htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'),
        'updated_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $collection->insertOne($document);
    $insertedId = (string)$result->getInsertedId();

    echo json_encode([
        'success' => true,
        'message' => 'Ca làm việc đã được thêm thành công.',
        'data' => [
            'id' => $insertedId,
            'employee_name' => $employee_name,
            'shift_type' => $shift_type,
            'start_time' => $start_time_str,
            'end_time' => $end_time_str,
            'hourly_rate' => $hourly_rate
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in shifts/create.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra khi tạo ca làm việc: ' . $e->getMessage()
    ]);
}
