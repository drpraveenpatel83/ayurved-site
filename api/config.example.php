<?php
// Hostinger pe yeh file copy karke config.php naam do
// File Manager → api/ → config.php banao

define('DB_HOST',    'localhost');
define('DB_NAME',    'u123456_ayurveda');   // Hostinger database name
define('DB_USER',    'u123456_admin');       // Hostinger DB username
define('DB_PASS',    'YourPassword');         // Hostinger DB password
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL',   'https://quiz.yourdomain.com'); // Apna subdomain
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('SESSION_LIFETIME', 86400 * 30);
define('ADMIN_EMAIL', 'admin@yourdomain.com');
define('APP_ENV', 'production');
