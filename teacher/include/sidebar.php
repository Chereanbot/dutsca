<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: /index.php');
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$userName = $_SESSION['name'] ?? 'Teacher';
$userRole = $_SESSION['role'] ?? 'teacher';

// Define menu items
$menuItems = [
    [
        'url' => '/teacher/dashboard.php',
        'icon' => 'fas fa-chart-line',
        'text' => 'Dashboard'
    ],
    [
        'url' => '/teacher/attendance.php',
        'icon' => 'fas fa-calendar-check',
        'text' => 'Attendance'
    ],
    [
        'url' => '/teacher/work-schedule.php',
        'icon' => 'fas fa-calendar-alt',
        'text' => 'Work Schedule'
    ],
    [
        'url' => '/teacher/leave-requests.php',
        'icon' => 'fas fa-calendar-plus',
        'text' => 'Leave Requests'
    ],
    [
        'url' => '/teacher/profile.php',
        'icon' => 'fas fa-user',
        'text' => 'My Profile'
    ]
];
?>

<aside class="fixed left-0 top-0 z-40 h-screen pt-16 transition-transform -translate-x-full bg-white border-r border-gray-200 md:translate-x-0 w-64" id="teacherSidebar">
    <div class="h-full px-3 pb-4 overflow-y-auto">
        <div class="space-y-2 font-medium text-white">
            <!-- User Profile Section -->
            <div class="flex items-center p-2 mb-6">
                <img src="/assets/images/default-avatar.png" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($userName); ?></p>
                    <p class="text-xs opacity-75"><?php echo ucfirst($userRole); ?></p>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="space-y-1 mt-4">
                <?php foreach ($menuItems as $item): ?>
                    <a href="<?php echo $item['url']; ?>" 
                       class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors <?php echo basename($item['url']) === $currentPage ? 'bg-gray-100' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> w-5 h-5 text-[#00572d]"></i>
                        <span class="ml-3"><?php echo $item['text']; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Quick Actions -->
            <div class="mt-8">
                <h3 class="px-4 text-xs font-semibold text-gray-400 uppercase tracking-wider">Quick Actions</h3>
                <div class="mt-4 space-y-1">
                    <a href="/teacher/clock.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-clock w-5 h-5 text-[#00572d]"></i>
                        <span class="ml-3">Clock In/Out</span>
                    </a>
                    <a href="/teacher/request-leave.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-calendar-plus w-5 h-5 text-[#00572d]"></i>
                        <span class="ml-3">Request Leave</span>
                    </a>
                    <a href="/teacher/report-issue.php" class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-exclamation-circle w-5 h-5 text-[#00572d]"></i>
                        <span class="ml-3">Report Issue</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile Overlay -->
<div class="fixed inset-0 bg-gray-800 bg-opacity-50 z-30 hidden" id="sidebarOverlay"></div>

<div class="md:ml-64">
    <!-- Main content goes here -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.querySelector('[data-drawer-toggle="sidebar"]');
    const sidebar = document.getElementById('teacherSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    menuButton?.addEventListener('click', toggleSidebar);
    overlay?.addEventListener('click', toggleSidebar);

    // Close sidebar on window resize if mobile view
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768 && !sidebar.classList.contains('-translate-x-full')) {
            toggleSidebar();
        }
    });
});
</script>
