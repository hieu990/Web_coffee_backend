<?php
/**
 * index.php — Health Check & Connection Tester
 * LAB COFFEE Backend — MongoDB Atlas + MySQL
 *
 * Truy cập: https://your-backend.onrender.com/
 * Mục đích: Xác nhận server và database đã kết nối thành công.
 */

// ── 1. Load Composer autoloader ─────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;

// ── 2. Cấu hình ─────────────────────────────────────────────
// Đọc từ biến môi trường — KHÔNG hardcode password vào file
$mongoUri    = getenv('MONGODB_URI')     ?: 'mongodb://localhost:27017';
$mongoDbName = getenv('MONGODB_DB_NAME') ?: 'coffee_shop';

// Ẩn credentials trong log (bảo mật)
$safeUri = preg_replace('/\/\/([^:]+):([^@]+)@/', '//***:***@', $mongoUri);

// ── 3. Header ────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LAB COFFEE — Server Health Check</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #0f1117;
      color: #e2e8f0;
      font-family: 'Segoe UI', monospace, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: #1a1d24;
      border: 1px solid #2a2f3a;
      border-radius: 16px;
      padding: 40px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 25px 60px rgba(0,0,0,0.6);
    }
    .logo {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #0f1117;
      font-weight: 900;
      font-size: 13px;
      letter-spacing: 0.15em;
      padding: 6px 14px;
      border-radius: 8px;
      display: inline-block;
      margin-bottom: 20px;
    }
    h1 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
    .subtitle { font-size: 11px; color: #64748b; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 28px; }
    .item {
      background: #13151c;
      border: 1px solid #2a2f3a;
      border-radius: 10px;
      padding: 14px 18px;
      margin-bottom: 10px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }
    .icon { font-size: 20px; flex-shrink: 0; margin-top: 2px; }
    .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
    .value { font-size: 13px; font-weight: 600; margin-top: 2px; }
    .ok    { color: #34d399; }
    .fail  { color: #f87171; }
    .warn  { color: #fbbf24; }
    .meta  { color: #94a3b8; font-size: 11px; font-family: monospace; margin-top: 4px; }
    .footer { text-align: center; font-size: 10px; color: #334155; margin-top: 24px; letter-spacing: 0.1em; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">LAB COFFEE</div>
  <h1>Server Health Check</h1>
  <p class="subtitle">Backend API &nbsp;//&nbsp; PHP <?= PHP_VERSION ?></p>

  <?php

  // ── Check 1: PHP Version ──────────────────────────────────
  $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
  ?>
  <div class="item">
    <div class="icon"><?= $phpOk ? '✅' : '⚠️' ?></div>
    <div>
      <div class="label">PHP Runtime</div>
      <div class="value <?= $phpOk ? 'ok' : 'warn' ?>">PHP <?= PHP_VERSION ?></div>
      <div class="meta"><?= PHP_OS_FAMILY ?> — <?= PHP_SAPI ?></div>
    </div>
  </div>

  <?php

  // ── Check 2: MongoDB Extension ────────────────────────────
  $mongoExtOk = extension_loaded('mongodb');
  ?>
  <div class="item">
    <div class="icon"><?= $mongoExtOk ? '✅' : '❌' ?></div>
    <div>
      <div class="label">MongoDB Extension</div>
      <?php if ($mongoExtOk): ?>
        <div class="value ok">ext-mongodb đã được cài đặt</div>
        <div class="meta">Phiên bản: <?= phpversion('mongodb') ?></div>
      <?php else: ?>
        <div class="value fail">ext-mongodb CHƯA được cài đặt</div>
        <div class="meta">Chạy: pecl install mongodb && docker-php-ext-enable mongodb</div>
      <?php endif; ?>
    </div>
  </div>

  <?php

  // ── Check 3: MongoDB Atlas Connection ─────────────────────
  $mongoStatus  = '';
  $mongoMessage = '';
  $mongoOk      = false;

  if (!$mongoExtOk) {
    $mongoStatus  = 'skip';
    $mongoMessage = 'Bỏ qua — ext-mongodb chưa được bật.';
  } else {
    try {
      $client = new Client($mongoUri, [
        'serverSelectionTimeoutMS' => 5000,
        'connectTimeoutMS'         => 5000,
      ]);

      // Ping để xác nhận kết nối thực sự thành suốt
      $result = $client->selectDatabase($mongoDbName)->command(['ping' => 1]);
      $pingOk = isset($result->toArray()[0]->ok) && $result->toArray()[0]->ok == 1;

      if ($pingOk) {
        $mongoOk      = true;
        $mongoStatus  = 'ok';
        $mongoMessage = 'Kết nối Database thành công! 🎉';
      } else {
        $mongoStatus  = 'fail';
        $mongoMessage = 'Ping trả về không hợp lệ.';
      }

    } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
      $mongoStatus  = 'fail';
      $mongoMessage = 'Timeout: Không thể kết nối tới MongoDB. Kiểm tra MONGODB_URI và Network Access trên Atlas.';
    } catch (\MongoDB\Driver\Exception\AuthenticationException $e) {
      $mongoStatus  = 'fail';
      $mongoMessage = 'Xác thực thất bại: Sai username/password trong MONGODB_URI.';
    } catch (\Exception $e) {
      $mongoStatus  = 'fail';
      $mongoMessage = 'Lỗi: ' . $e->getMessage();
    }
  }
  ?>
  <div class="item">
    <div class="icon"><?= $mongoOk ? '✅' : ($mongoStatus === 'skip' ? '⏭️' : '❌') ?></div>
    <div>
      <div class="label">MongoDB Atlas</div>
      <div class="value <?= $mongoOk ? 'ok' : ($mongoStatus === 'skip' ? 'warn' : 'fail') ?>">
        <?= htmlspecialchars($mongoMessage) ?>
      </div>
      <div class="meta">URI: <?= htmlspecialchars($safeUri) ?> / DB: <?= htmlspecialchars($mongoDbName) ?></div>
    </div>
  </div>

  <?php

  // ── Check 4: Environment Variables ────────────────────────
  $envSet = !empty(getenv('MONGODB_URI'));
  ?>
  <div class="item">
    <div class="icon"><?= $envSet ? '✅' : '⚠️' ?></div>
    <div>
      <div class="label">Environment Variables</div>
      <?php if ($envSet): ?>
        <div class="value ok">MONGODB_URI đã được thiết lập</div>
        <div class="meta">
          MONGODB_DB_NAME: <?= htmlspecialchars(getenv('MONGODB_DB_NAME') ?: '(chưa set — dùng mặc định: coffee_shop)') ?>
        </div>
      <?php else: ?>
        <div class="value warn">MONGODB_URI chưa được thiết lập</div>
        <div class="meta">Đang dùng fallback: mongodb://localhost:27017</div>
      <?php endif; ?>
    </div>
  </div>

  <?php

  // ── Check 5: Composer autoloader ─────────────────────────
  $composerOk = file_exists(__DIR__ . '/vendor/autoload.php');
  ?>
  <div class="item">
    <div class="icon"><?= $composerOk ? '✅' : '❌' ?></div>
    <div>
      <div class="label">Composer Autoloader</div>
      <div class="value <?= $composerOk ? 'ok' : 'fail' ?>">
        <?= $composerOk ? 'vendor/autoload.php tồn tại' : 'Chưa chạy composer install!' ?>
      </div>
      <?php if ($composerOk): ?>
        <div class="meta"><?= realpath(__DIR__ . '/vendor/autoload.php') ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="footer">
    &copy; <?= date('Y') ?> LAB COFFEE BACKEND &nbsp;//&nbsp;
    <?= date('d/m/Y H:i:s T') ?>
  </div>
</div>
</body>
</html>
