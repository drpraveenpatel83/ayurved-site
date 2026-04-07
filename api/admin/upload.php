<?php
/**
 * POST /api/admin/upload.php
 * Upload an image file for banners / posts.
 * Field name: image
 * Returns: { url: "/uploads/banners/filename.jpg" }
 */
require_once dirname(__DIR__).'/helpers.php';
setCorsHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST only', 405);

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $codes = [1=>'File too large (php.ini)',2=>'File too large (form)',3=>'Partial upload',4=>'No file',6=>'No tmp dir',7=>'Cannot write'];
    jsonError('Upload error: ' . ($codes[$file['error'] ?? 4] ?? 'Unknown'));
}

// Verify it is actually an image using mime detection (not just extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
if (!in_array($mime, $allowed)) {
    jsonError('Only image files allowed: jpg, png, webp, gif');
}

if ($file['size'] > 5 * 1024 * 1024) {
    jsonError('File too large. Maximum 5 MB allowed.');
}

$extMap = ['image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
$ext    = $extMap[$mime] ?? 'jpg';
$name   = 'banner_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

// Upload directory: /uploads/banners/ (relative to web root)
$uploadDir = dirname(__DIR__, 2) . '/uploads/banners/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        jsonError('Cannot create upload directory. Check server permissions.');
    }
}

$dest = $uploadDir . $name;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonError('Failed to save file. Check directory permissions.');
}

jsonSuccess(['url' => '/uploads/banners/' . $name], 'Image uploaded successfully');
