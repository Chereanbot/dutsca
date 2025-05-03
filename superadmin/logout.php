<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? null;
    
    // Log the logout activity if user is logged in
    if ($userId) {
        $logger = new Logger($db);
        $logger->logActivity($userId, 'User logged out', 'logout');
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: /dutsca/index.php');
    exit();
} catch (Exception $e) {
    error_log("Error during logout: " . $e->getMessage());
    // Even if there's an error, still destroy the session and redirect
    session_destroy();
    header('Location: /dutsca/index.php');
    exit();
}
?>
