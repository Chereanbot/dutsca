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

<aside class="sidebar fixed left-0 top-0 z-40 h-screen pt-16 transition-transform -translate-x-full md:translate-x-0" id="teacherSidebar">
    <div class="h-full overflow-y-auto">
        <!-- Main Menu Section -->
        <div class="sidebar-section">
            <h3 class="sidebar-section-title">MAIN MENU</h3>
            <nav>
                <?php foreach ($menuItems as $item): ?>
                    <a href="<?php echo $item['url']; ?>" 
                       class="sidebar-link <?php echo basename($item['url']) === $currentPage ? 'active' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?> sidebar-icon"></i>
                        <span><?php echo $item['text']; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Quick Actions Section -->
        <div class="sidebar-section">
            <h3 class="sidebar-section-title">QUICK ACTIONS</h3>
            <nav>
                <a href="/teacher/clock.php" 
                   class="sidebar-link <?php echo $currentPage === 'clock.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clock sidebar-icon"></i>
                    <span>Clock In/Out</span>
                </a>
                <a href="/teacher/request-leave.php" 
                   class="sidebar-link <?php echo $currentPage === 'request-leave.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-plus sidebar-icon"></i>
                    <span>Request Leave</span>
                </a>
                <a href="/teacher/report-issue.php" 
                   class="sidebar-link <?php echo $currentPage === 'report-issue.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-circle sidebar-icon"></i>
                    <span>Report Issue</span>
                </a>
            </nav>
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
