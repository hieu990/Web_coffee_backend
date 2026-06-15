<?php
/**
 * Update Reservation Status API (Admin)
 * File Path: backend/admin/update_reservation.php
 */

header('Access-Control-Allow-Origin: http://localhost:5173');
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
    $status = $data['status'] ?? '';

    if (empty($id) || empty($status)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Reservation ID and Status are required.']);
        exit;
    }

    // Validate input status
    $allowedStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    if (!in_array($status, $allowedStatuses)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Trạng thái đặt bàn không hợp lệ.']);
        exit;
    }

    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('reservations');

    $result = $collection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($id)],
        ['$set' => ['status' => $status]]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Reservation status successfully updated.',
        'data' => [
            'id' => $id,
            'status' => $status
        ]
    ]);
} catch (Exception $e) {
    error_log("API Error in update_reservation.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating status: ' . $e->getMessage()
    ]);
}
