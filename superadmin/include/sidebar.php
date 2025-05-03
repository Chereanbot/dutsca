<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--primary-color);
    color: white;
    z-index: 1040;
  display: flex;
  flex-direction: column;
}

.sidebar-header {
    height: var(--header-height);
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    background: rgba(0, 0, 0, 0.1);
}

.sidebar-brand {
    color: white;
  font-size: 1.25rem;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sidebar-brand:hover {
    color: var(--accent-color);
}

.sidebar-brand img {
    height: 32px;
    width: auto;
}

.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
}

.nav-section {
    padding: 0.75rem 1.5rem 0.5rem;
  font-size: 0.75rem;
  text-transform: uppercase;
    color: var(--accent-color);
    font-weight: 600;
    letter-spacing: 0.5px;
}

.nav-item {
    padding: 0.25rem 1rem;
}

.nav-link {
  display: flex;
  align-items: center;
    padding: 0.75rem 1rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
  border-radius: 0.5rem;
    transition: all 0.3s ease;
}

.nav-link i {
    width: 1.5rem;
    font-size: 1.1rem;
    margin-right: 0.75rem;
    text-align: center;
}

.nav-link:hover {
    background: var(--secondary-color);
    color: white;
}

.nav-link.active {
    background: var(--accent-color);
    color: var(--primary-color);
}

.nav-link.active i {
    color: var(--primary-color);
}

.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-footer .nav-link {
    color: rgba(255, 255, 255, 0.8);
}

.sidebar-footer .nav-link:hover {
    background: var(--secondary-color);
    color: white;
}
</style>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="/dutsca/superadmin/dashboard.php" class="sidebar-brand">
            <img src="/dutsca/assets/images/logo-white.png" alt="DUTSCA Logo" onerror="this.src='/dutsca/assets/images/default-logo-white.png'">
            <span>DUTSCA</span>
        </a>
    </div>
    
    <div class="sidebar-menu">
        <div class="nav-section">Main Menu</div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/users.php" class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/departments.php" class="nav-link <?php echo $currentPage === 'departments.php' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/user_permissions.php" class="nav-link <?php echo $currentPage === 'user_permissions.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                <span>User Permissions</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/security.php" class="nav-link <?php echo $currentPage === 'security.php' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span>Security</span>
            </a>
        </div>

        <div class="nav-section">System</div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/updates.php" class="nav-link <?php echo $currentPage === 'updates.php' ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i>
                <span>Updates</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/status.php" class="nav-link <?php echo $currentPage === 'status.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>System Status</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/logs.php" class="nav-link <?php echo $currentPage === 'logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>System Logs</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/backup.php" class="nav-link <?php echo $currentPage === 'backup.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>Backup & Recovery</span>
            </a>
        </div>
        <div class="nav-section">Reports</div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/activity_reports.php" class="nav-link <?php echo $currentPage === 'activity_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>User Activities</span>
            </a>
        </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/system_reports.php" class="nav-link <?php echo $currentPage === 'system_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>System Reports</span>
            </a>
        </div>
        <div class="nav-section">Settings</div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/settings.php" class="nav-link <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
            </div>
        <div class="nav-item">
            <a href="/dutsca/superadmin/profile.php" class="nav-link <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
                </a>
            </div>
    </div>

    <div class="sidebar-footer">
        <a href="/dutsca/logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
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
