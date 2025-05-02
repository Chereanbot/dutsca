<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="fixed left-0 top-0 z-40 h-screen pt-16 transition-transform -translate-x-full bg-[#00572d] border-r border-gray-200 md:translate-x-0 w-64">
    <div class="h-full px-3 pb-4 overflow-y-auto">
        <div class="space-y-2 font-medium text-white">
            <div class="flex items-center p-2 mb-6">
                <img src="/assets/images/default-avatar.png" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <p class="text-sm font-semibold"><?php echo $_SESSION['name'] ?? 'Chairman Name'; ?></p>
                    <p class="text-xs opacity-75">Chairman</p>
                </div>
            </div>

            <nav>
                <ul class="space-y-1">
                    <li>
                        <a href="/chairman/dashboard.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'dashboard.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-chart-line w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="/chairman/approve-members.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'approve-members.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-user-check w-6"></i>
                            <span>Approve Members</span>
                        </a>
                    </li>
                    <li>
                        <a href="/chairman/member-requests.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'member-requests.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-user-plus w-6"></i>
                            <span>Member Requests</span>
                        </a>
                    </li>
                    <li>
                        <a href="/chairman/system-overview.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'system-overview.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-desktop w-6"></i>
                            <span>System Activities</span>
                        </a>
                    </li>
                    <li>
                        <a href="/chairman/notices.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'notices.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-bullhorn w-6"></i>
                            <span>Broadcast Notices</span>
                        </a>
                    </li>
                    <li>
                        <a href="/chairman/reports.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'reports.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-file-alt w-6"></i>
                            <span>View Reports</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="pt-8">
                <a href="/logout.php" class="flex items-center p-2 text-white hover:bg-[#1f9345] rounded-lg">
                    <i class="fas fa-sign-out-alt w-6"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</aside>

<div class="md:ml-64">
    <!-- Main content goes here -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.querySelector('[data-drawer-toggle="sidebar"]');
    const sidebar = document.querySelector('aside');
    
    menuButton?.addEventListener('click', function() {
        sidebar.classList.toggle('-translate-x-full');
    });
});
</script>
