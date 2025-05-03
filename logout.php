<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/include/Logger.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get user ID from session
    $userId = $_SESSION['user_id'] ?? null;
    
    // Log the logout activity if user is logged in
    if ($userId) {
        $logger = Logger::getInstance();
        $logger->log('auth', 'logout', 'User logged out successfully');
    }
    
    // Destroy the session
    session_destroy();
    
    // Show success notification with SweetAlert2
    echo '<script>
        // Initialize SweetAlert2
        if (typeof Swal === "undefined") {
            const script = document.createElement("script");
            script.src = "https://cdn.jsdelivr.net/npm/sweetalert2@11";
            document.head.appendChild(script);
        }

        // Show success message
        Swal.fire({
            title: "Success!",
            text: "You have been logged out successfully!",
            icon: "success",
            timer: 3000,
            showConfirmButton: false,
            customClass: {
                popup: "swal-popup-logout"
            },
            willClose: () => {
                // Redirect after notification
                window.location.href = "/dutsca/index.php";
            }
        });
    </script>;
    exit();
} catch (Exception $e) {
    error_log("Error during logout: " . $e->getMessage());
    // Even if there's an error, still destroy the session and redirect
    session_destroy();
    header('Location: /dutsca/index.php');
    exit();
}
?>
