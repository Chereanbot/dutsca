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
                    <p class="text-sm font-semibold"><?php echo $_SESSION['name'] ?? 'System Admin'; ?></p>
                    <p class="text-xs opacity-75">Super Admin</p>
                </div>
            </div>

            <nav>
                <ul class="space-y-1">
                    <li>
                        <a href="/sysadmin/dashboard.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'dashboard.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-chart-line w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sysadmin/users.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'users.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-users w-6"></i>
                            <span>Manage Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sysadmin/security.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'security.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-shield-alt w-6"></i>
                            <span>System Security</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sysadmin/roles.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'roles.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-user-lock w-6"></i>
                            <span>Role Settings</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sysadmin/backup.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'backup.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-database w-6"></i>
                            <span>Backup & Restore</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sysadmin/logs.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'logs.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-history w-6"></i>
                            <span>Activity Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="/sysadmin/settings.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'settings.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-cog w-6"></i>
                            <span>System Settings</span>
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
