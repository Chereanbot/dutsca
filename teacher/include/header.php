<?php
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Strict role checking
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: /index.php');
    exit();
}

// Get user data from session
$userName = $_SESSION['name'] ?? 'Teacher';
$userRole = $_SESSION['role'] ?? 'teacher';
$userId = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - DUTSCA</title>
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Custom CSS -->
    <link href="/teacher/css/style.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="header fixed w-full top-0 z-50 text-white">
        <div class="flex justify-between items-center px-4 py-2">
            <div class="flex items-center space-x-4">
                <button data-drawer-toggle="sidebar" class="header-button p-2 rounded-lg transition-colors md:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-bold">Employee Portal</h1>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- Clock In Button -->
                <button class="clock-button px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Clock In</span>
                </button>

                <!-- Notifications -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="header-button relative p-2 rounded-lg">
                        <i class="fas fa-bell text-xl"></i>
                        <span class="notification-badge absolute -top-1 -right-1 rounded-full w-5 h-5 text-xs flex items-center justify-center font-bold">3</span>
                    </button>
                </div>

                <!-- Profile -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="header-button flex items-center space-x-3 p-2 rounded-lg">
                        <img src="/assets/images/default-avatar.png" alt="Profile" class="w-8 h-8 rounded-full">
                        <div class="hidden md:block text-left">
                            <p class="text-sm font-semibold"><?php echo htmlspecialchars($userName); ?></p>
                            <p class="text-xs text-gray-300">IT Department</p>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-4"></div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 z-50 bg-white bg-opacity-90 flex items-center justify-center hidden">
        <div class="loading-spinner rounded-full h-16 w-16 border-4 border-solid"></div>
    </div>

    <!-- Custom JavaScript -->
    <script src="/teacher/js/main.js" defer></script>
</body>
</html>
