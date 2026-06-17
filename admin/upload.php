<?php
// CORS Headers for React Frontend compatibility
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

// Require session authentication helper
require_once __DIR__ . '/auth.php';
require_auth(); // Enforce that only authenticated admins can upload files

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed on this endpoint.'
    ]);
    exit;
}

// Check if file key is present in $_FILES
if (!isset($_FILES['image'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => 'No image file uploaded.'
    ]);
    exit;
}

$file = $_FILES['image'];

// Validate PHP Upload Error Codes
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('HTTP/1.1 400 Bad Request');
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
    ];
    $errorMsg = $messages[$file['error']] ?? 'Unknown upload error occurred.';
    echo json_encode([
        'success' => false,
        'message' => $errorMsg
    ]);
    exit;
}

// 1. Size Restriction (Limit to 5 Megabytes)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => 'File is too large. Maximum size allowed is 5MB.'
    ]);
    exit;
}

// 2. MIME & Extension Whitelist Check
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

// Extract extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file extension. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.'
    ]);
    exit;
}

// Check actual MIME-type using fileinfo extension (more secure than $_FILES['type'])
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file MIME type. The file content is not a valid image.'
    ]);
    exit;
}

// 3. Define upload directory inside Vite's React public/uploads folder for easy static file rendering
$uploadDir = __DIR__ . '/../../public/uploads/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create upload directory. Check file system permissions.'
        ]);
        exit;
    }
}

// 4. Secure Cryptographically Unique Renaming (to avoid conflicts and path traversal attacks)
$randomBytes = bin2hex(random_bytes(16));
$newFileName = $randomBytes . '_' . time() . '.' . $fileExtension;
$targetFilePath = $uploadDir . $newFileName;

// 5. Move file to destination
if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
    // Return React-friendly relative URL (Vite serves public files relative to root /)
    $relativeUrl = '/uploads/' . $newFileName;
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully.',
        'url' => $relativeUrl
    ]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to move uploaded file. Check directory permissions.'
    ]);
}
