<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sysadmin') {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUTSCA - System Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <header class="fixed w-full top-0 z-50 bg-[#00572d] text-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <button data-drawer-toggle="sidebar" class="p-2 hover:bg-[#1f9345] rounded-lg transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-2xl font-bold">DUTSCA Admin</h1>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- System Status -->
                    <div class="hidden md:flex items-center space-x-2 px-3 py-1 bg-green-500 bg-opacity-20 rounded-full">
                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        <span class="text-sm">System Online</span>
                    </div>

                    <!-- Alerts -->
                    <div class="relative">
                        <button class="p-2 hover:bg-[#1f9345] rounded-lg transition-colors">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute top-0 right-0 bg-red-500 rounded-full w-4 h-4 text-xs flex items-center justify-center">5</span>
                        </button>
                    </div>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-3 hover:bg-[#1f9345] rounded-lg p-2 transition-colors">
                            <img src="/assets/images/default-avatar.png" alt="Profile" class="w-8 h-8 rounded-full">
                            <div class="hidden md:block text-left">
                                <p class="text-sm font-semibold"><?php echo $_SESSION['name'] ?? 'System Admin'; ?></p>
                                <p class="text-xs opacity-75">Super Admin</p>
                            </div>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white text-gray-800 rounded-lg shadow-lg py-2">
                            <a href="/sysadmin/profile.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-user w-5"></i> Profile
                            </a>
                            <a href="/sysadmin/settings.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-cog w-5"></i> Settings
                            </a>
                            <hr class="my-2">
                            <a href="/logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600">
                                <i class="fas fa-sign-out-alt w-5"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Alpine.js for dropdown functionality -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
