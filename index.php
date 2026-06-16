<?php
/**
 * index.php — Health Check & Connection Tester
 * LAB COFFEE Backend — MongoDB Atlas
 */

// ── CORS Headers ──────────────────────────────────────────────
header("Access-Control-Allow-Origin: https://lab-coffee.netlify.app");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("HTTP/1.1 204 No Content");
    exit(0);
}

// ── 1. Load Composer autoloader ─────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;

// ── 2. Configuration ─────────────────────────────────────────
$mongoUri    = getenv('MONGODB_URI')     ?: 'mongodb://localhost:27017';
$mongoDbName = getenv('MONGODB_DB_NAME') ?: 'coffee_shop';

// ── 3. Connection Try-Catch ──────────────────────────────────
try {
    $client = new Client($mongoUri, [
        'serverSelectionTimeoutMS' => 5000,
        'connectTimeoutMS'         => 5000,
    ]);

    // Ping to verify connection
    $cursor = $client->selectDatabase($mongoDbName)->command(['ping' => 1]);
    $resultArray = $cursor->toArray();
    $pingOk = isset($resultArray[0]->ok) && $resultArray[0]->ok == 1;

    if ($pingOk) {
        echo "Kết nối Database thành công";
    } else {
        throw new Exception("Ping Database không phản hồi thành công.");
    }

} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage();
}
