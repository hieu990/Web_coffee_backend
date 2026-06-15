<?php
/**
 * Create Reservation API
 * File Path: backend/api/add_reservation.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed on this endpoint.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (empty($data)) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid reservation details.']);
        exit;
    }

    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('reservations');

    $document = [
        'name' => htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8'),
        'phone' => htmlspecialchars($data['phone'] ?? '', ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars($data['email'] ?? '', ENT_QUOTES, 'UTF-8'),
        'telegram' => htmlspecialchars($data['telegram'] ?? '', ENT_QUOTES, 'UTF-8'),
        'seat' => htmlspecialchars($data['seat'] ?? '', ENT_QUOTES, 'UTF-8'),
        'stationType' => htmlspecialchars($data['stationType'] ?? '', ENT_QUOTES, 'UTF-8'),
        'date' => htmlspecialchars($data['date'] ?? '', ENT_QUOTES, 'UTF-8'),
        'time' => htmlspecialchars($data['time'] ?? '', ENT_QUOTES, 'UTF-8'),
        'duration' => (int)($data['duration'] ?? 1),
        'guests' => htmlspecialchars($data['guests'] ?? '', ENT_QUOTES, 'UTF-8'),
        'payMethod' => htmlspecialchars($data['payMethod'] ?? '', ENT_QUOTES, 'UTF-8'),
        'specialRequest' => htmlspecialchars($data['specialRequest'] ?? '', ENT_QUOTES, 'UTF-8'),
        'anonMode' => (bool)($data['anonMode'] ?? false),
        'totalVND' => (float)($data['totalVND'] ?? 0.0),
        'status' => 'Pending',
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $collection->insertOne($document);
    $insertedId = (string)$result->getInsertedId();

    echo json_encode([
        'success' => true,
        'message' => 'Reservation successfully processed.',
        'reservation_id' => $insertedId
    ]);
} catch (Exception $e) {
    error_log("API Error in add_reservation.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while creating reservation: ' . $e->getMessage()
    ]);
}
