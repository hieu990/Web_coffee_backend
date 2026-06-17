<?php
/**
 * MongoDB Menu Retrieval API
 * 
 * This API endpoint connects to the MongoDB database, fetches all documents
 * from the 'menu' collection, sanitizes the ObjectIDs to plain strings,
 * and outputs the result as a clean JSON array with proper CORS headers.
 */

// CORS Headers for React Frontend compatibility
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed on this endpoint.'
    ]);
    exit;
}

// Load database connection
require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    // Retrieve connection instance
    $db = DatabaseConnection::getConnection();
    
    // Select the 'menu' collection
    $collection = $db->selectCollection('menu');

    // If 'all=1' query parameter is present, show all products (used by Admin Dashboard).
    // Otherwise (public customer view), only retrieve products that are available (not explicitly set to false).
    $showAll = isset($_GET['all']) && $_GET['all'] === '1';
    $filter = $showAll ? [] : ['available' => ['$ne' => false]];

    // Retrieve documents
    $cursor = $collection->find($filter);

    $menuItems = [];
    foreach ($cursor as $document) {
        // Convert BSON Document to a standard PHP associative array
        $item = (array) $document;

        // CRITICAL BEST PRACTICE: Handle the _id Object ID
        // If we encode the raw BSON ObjectId directly to JSON, PHP will serialize it as {"_id": {"$oid": "xyz..."}}.
        // To make it easy to use in the React/Vite frontend (e.g. key={item._id}), we cast it to a plain string.
        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }

        $menuItems[] = $item;
    }

    // Send JSON Response
    echo json_encode($menuItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("API Error in get_menu.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching menu items: ' . $e->getMessage()
    ]);
}
