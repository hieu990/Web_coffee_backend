<?php
/**
 * admin/index.php — Simple Admin Control Panel
 * LAB COFFEE Backend — MongoDB
 */

// ── 1. Require authentication ────────────────────────────────
require_once __DIR__ . '/auth.php';
require_auth();

// ── 2. Load MongoDB connection manager ───────────────────────
require_once dirname(__DIR__) . '/config/db_connect.php';

try {
    $db = DatabaseConnection::getConnection();
    $collection = $db->selectCollection('products');

    // Get active products
    $filter = ['$or' => [['is_deleted' => false], ['is_deleted' => ['$exists' => false]]]];
    $cursor = $collection->find($filter, ['sort' => ['created_at' => -1]]);
    $products = [];

    foreach ($cursor as $document) {
        $item = (array) $document;
        if (isset($item['_id'])) {
            $item['_id'] = (string)$item['_id'];
        }
        $products[] = $item;
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="vi" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAB COFFEE | Quản Lý Thực Đơn</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Plus+Jakarta+Sans:wght@200..800&family=JetBrains+Mono:wght@300..700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        body {
            background-color: #0c0f0f;
            color: #e2e2e2;
            min-height: 100vh;
            padding: 40px 24px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 183, 123, 0.15);
            padding-bottom: 20px;
            margin-bottom: 32px;
        }
        .logo-group h1 {
            color: #ffb77b;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }
        .logo-group p {
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
            font-family: 'JetBrains Mono', monospace;
        }
        .user-group {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .username {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: #ffb77b;
            background: rgba(255, 183, 123, 0.08);
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid rgba(255, 183, 123, 0.15);
        }
        .btn-logout {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 11px;
            font-weight: bold;
            color: #f87171;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 8px 16px;
            border: 1px solid rgba(248, 113, 113, 0.2);
            border-radius: 4px;
            background: rgba(248, 113, 113, 0.05);
            transition: all 0.3s;
        }
        .btn-logout:hover {
            background: #f87171;
            color: #0c0f0f;
            border-color: #f87171;
        }
        .card {
            background: rgba(26, 26, 26, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 183, 123, 0.1);
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .card h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            letter-spacing: 0.05em;
            color: #ffffff;
            margin-bottom: 24px;
            text-transform: uppercase;
            border-left: 3px solid #ffb77b;
            padding-left: 12px;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #ffb77b;
            border-bottom: 1px solid rgba(255, 183, 123, 0.15);
            padding: 14px 16px;
            font-weight: 700;
        }
        td {
            font-size: 13px;
            padding: 16px;
            border-bottom: 1px solid rgba(255, 183, 123, 0.05);
            color: #c4c7c7;
        }
        tr:hover td {
            background: rgba(255, 183, 123, 0.02);
            color: #ffffff;
        }
        .badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-success {
            background: rgba(52, 211, 153, 0.1);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.2);
        }
        .badge-danger {
            background: rgba(248, 113, 113, 0.1);
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.2);
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #64748b;
            font-size: 14px;
        }
        .price {
            font-family: 'JetBrains Mono', monospace;
            color: #ffb77b;
        }
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .tag {
            font-size: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2px 6px;
            border-radius: 3px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-group">
                <h1>LAB COFFEE</h1>
                <p>Simple Admin Control Panel // Menu Manager</p>
            </div>
            <div class="user-group">
                <span class="username">👤 <?= htmlspecialchars($adminUsername) ?></span>
                <a href="logout.php" class="btn-logout">Đăng Xuất</a>
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div style="background: rgba(248, 113, 113, 0.1); border: 1px solid #f87171; color: #fca5a5; padding: 16px; border-radius: 8px; margin-bottom: 24px; font-size: 14px;">
                Lỗi hệ thống: <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Danh sách sản phẩm trong Menu</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tên món</th>
                            <th>Danh mục</th>
                            <th>Giá bán</th>
                            <th>Tùy chọn (Size/Topping)</th>
                            <th>Trạng thái kho</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">Chưa có sản phẩm nào được thiết lập trong Menu.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td style="font-weight: bold; color: #ffffff;"><?= htmlspecialchars($p['name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($p['category'] ?? 'Chưa phân loại') ?></td>
                                    <td class="price"><?= number_format($p['price'] ?? 0) ?>đ</td>
                                    <td>
                                        <div class="tag-list">
                                            <?php if (!empty($p['sizes'])): ?>
                                                <?php foreach ($p['sizes'] as $size): ?>
                                                    <span class="tag">Size: <?= htmlspecialchars($size) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($p['toppings'])): ?>
                                                <?php foreach ($p['toppings'] as $top): ?>
                                                    <span class="tag">+<?= htmlspecialchars($top) ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (($p['is_in_stock'] ?? true)): ?>
                                            <span class="badge badge-success">Còn hàng</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Hết hàng</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
