<?php
// Copy this file to config.php and fill in your Hostinger DB credentials
// NEVER commit config.php to GitHub (it's in .gitignore)

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// Site URL (no trailing slash)
define('SITE_URL', 'https://yourdomain.com');

// Upload paths
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Session settings
define('SESSION_LIFETIME', 86400 * 30); // 30 days

// Admin email
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// Environment: 'development' or 'production'
define('APP_ENV', 'production');
