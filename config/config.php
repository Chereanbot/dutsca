<?php
// Session Configuration - All session settings must be set before session_start()
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Start the session
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dutsca');

// Application Configuration
define('APP_NAME', 'DUTSCA');
define('APP_URL', 'http://localhost/dutsca');
define('APP_VERSION', '1.0.0');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');

// Define paths
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'app' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', ROOT_PATH . 'config' . DIRECTORY_SEPARATOR);
define('PUBLIC_PATH', ROOT_PATH . 'public' . DIRECTORY_SEPARATOR);

// Other configurations can be added here

// Security Configuration
define('HASH_COST', 10); // For password hashing
?> 