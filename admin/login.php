<?php
// Handle CORS for React Frontend integration
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], ['http://localhost:5173', 'https://lab-coffee.netlify.app', 'https://nimble-bonbon-edf285.netlify.app']) ? $_SERVER['HTTP_ORIGIN'] : 'http://localhost:5173'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

use Config\Database;

$error = '';
$isJsonRequest = false;

// Check if request is JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $isJsonRequest = true;
}

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = '';
    $password = '';

    if ($isJsonRequest) {
        // Parse raw JSON body
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $username = trim($jsonInput['username'] ?? '');
        $password = trim($jsonInput['password'] ?? '');
    } else {
        // Standard form submit
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
    }

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        $db = (new Database())->getConnection();
        
        // Secure prepared statement to prevent SQL Injection
        $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regeneration of session ID to prevent Session Fixation attacks
            session_regenerate_id(true);
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];

            if ($isJsonRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Đăng nhập thành công.',
                    'user' => [
                        'username' => $user['username']
                    ]
                ]);
                exit;
            } else {
                header('Location: http://localhost:5173/admin/dashboard'); // or your admin dashboard index page
                exit;
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        }
    }

    if ($isJsonRequest && !empty($error)) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $error
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB COFFEE | Đăng Nhập Quản Trị Viên</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        body {
            background-color: #0c0f0f;
            color: #e2e2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }
        .login-card {
            background: rgba(26, 26, 26, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 123, 0.15);
            border-radius: 4px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .header h2 {
            color: #ffb77b;
            font-size: 20px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .header p {
            color: #c4c7c7;
            font-size: 13px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #c4c7c7;
            margin-bottom: 8px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(12, 15, 15, 0.6);
            border: 1px solid rgba(68, 71, 72, 0.4);
            border-radius: 2px;
            color: #e2e2e2;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #ffb77b;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background-color: #ffb77b;
            color: #2e1500;
            border: none;
            border-radius: 2px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        .btn-submit:hover {
            opacity: 0.9;
        }
        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 12px;
            border-radius: 2px;
            font-size: 12px;
            margin-bottom: 24px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="header">
            <h2>LAB COFFEE</h2>
            <p>BẢNG ĐIỀU KHIỂN QUẢN TRỊ</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Tên Đăng Nhập // Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Mật Khẩu // Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn-submit">Xác Thực Lệnh // Login</button>
        </form>
    </div>
</body>
</html>
