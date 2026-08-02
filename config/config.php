<?php
// =============================================
// Application Configuration
// =============================================

// Load constants FIRST
require_once __DIR__ . '/constants.php';

// Site Configuration
if (!defined('SITE_NAME')) define('SITE_NAME', 'ADPS - Automated Duty Processing System');
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost/duty-roster');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@adps.com');
if (!defined('TIMEZONE')) define('TIMEZONE', 'Africa/Lagos');

// Security Configuration
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('PASSWORD_MIN_LENGTH')) define('PASSWORD_MIN_LENGTH', 8);

// Password Requirements - ADD THIS
if (!defined('PASSWORD_REQUIREMENTS')) {
    define('PASSWORD_REQUIREMENTS', [
        'uppercase' => true,
        'lowercase' => true,
        'numbers' => true,
        'special' => true
    ]);
}


// Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'adps');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}