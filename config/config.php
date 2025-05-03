<?php
// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    // Set session configuration before starting the session
    ini_set('session.cookie_lifetime', 86400); // 24 hours
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    
    // Start the session after configuration
    session_start();
}

// Other configurations
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'dutsca');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_URL', 'http://localhost/dutsca');
define('SITE_NAME', 'DUTSCA Management System');
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    switch ($errno) {
        case E_USER_ERROR:
            echo "<b>Error:</b> [$errno] $errstr<br>";
            echo "Fatal error on line $errline in file $errfile";
            exit(1);
            break;
            
        case E_USER_WARNING:
            echo "<b>Warning:</b> [$errno] $errstr<br>";
            break;
            
        case E_USER_NOTICE:
            echo "<b>Notice:</b> [$errno] $errstr<br>";
            break;
            
        default:
            echo "Unknown error type: [$errno] $errstr<br>";
            break;
    }
    
    return true;
});

// Application Configuration
define('APP_NAME', 'DUTSCA');
define('APP_VERSION', '1.0.0');

// Define paths
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'app' . DIRECTORY_SEPARATOR);
define('CONFIG_PATH', ROOT_PATH . 'config' . DIRECTORY_SEPARATOR);
define('PUBLIC_PATH', ROOT_PATH . 'public' . DIRECTORY_SEPARATOR);

// Other configurations can be added here

// Security Configuration
define('HASH_COST', 10); // For password hashing
?> 