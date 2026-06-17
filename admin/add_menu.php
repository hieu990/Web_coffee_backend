<?php
/**
 * MongoDB Add Menu API
 * 
 * This endpoint processes POST requests from the client, validates and
 * sanitizes the inputs (name, price, category), and saves the new product
 * into the MongoDB 'menu' collection.
 */

// CORS Headers for React Frontend compatibility
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
require_once dirname(__DIR__) . '/config/db_connect.php';

// Retrieve request body (support both JSON payload and standard Form POST)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$name = '';
$price = 0.0;
$category = '';

if (strpos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $name = $data['name'] ?? '';
    $price = $data['price'] ?? 0.0;
    $category = $data['category'] ?? '';
} else {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0.0;
    $category = $_POST['category'] ?? '';
}

// 1. Inputs Sanitization & Validation
$name = trim(filter_var($name, FILTER_DEFAULT));
$category = trim(filter_var($category, FILTER_DEFAULT));
$price = filter_var($price, FILTER_VALIDATE_FLOAT);

$errors = [];

if (empty($name)) {
    $errors[] = 'Product name is required.';
}
if ($price === false || $price <= 0) {
    $errors[] = 'A valid positive price is required.';
}
if (empty($category)) {
    $errors[] = 'Category is required.';
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
$sanitizedName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$sanitizedCategory = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');

try {
    // Connect to database
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('menu');

    // Create the document to insert
    $document = [
        'name' => $sanitizedName,
        'price' => (float)$price,
        'category' => $sanitizedCategory,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ];

    // Insert document
    $result = $collection->insertOne($document);

    // Get the inserted ID as a plain string representation
    $insertedId = (string)$result->getInsertedId();

    // Respond with success
    echo json_encode([
        'success' => true,
        'message' => 'Product successfully added to the menu.',
        'data' => [
            'id' => $insertedId,
            'name' => $sanitizedName,
            'price' => (float)$price,
            'category' => $sanitizedCategory
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in add_menu.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while adding the product: ' . $e->getMessage()
    ]);
}
