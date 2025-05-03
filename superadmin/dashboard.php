<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';
require_once __DIR__ . '/include/sidebar.php';
require_once __DIR__ . '/include/header.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

// Initialize default values
$stats = [
    'total_users' => 0,
    'total_activities' => 0,
    'total_errors' => 0,
    'active_users' => 0,
    'inactive_users' => 0,
    'pending_users' => 0
];

$todayStats = [
    'total_activities' => 0,
    'unique_users' => 0,
    'unique_categories' => 0
];

$weeklyTrend = [];
$topUsers = [];
$recentActivities = [];
$activityChange = 0;

// Add new initializations
$usersByRole = [];
$activityByCategory = [];
$systemHealth = [
    'cpu_usage' => 0,
    'memory_usage' => 0,
    'disk_usage' => 0,
    'last_backup' => null
];
$pendingApprovals = [];
$recentLogins = [];

try {
    $db = getDB();
    $logger = Logger::getInstance();
    
    // Get overall statistics
    $systemStats = $logger->getSystemStats();
    if ($systemStats) {
        $stats = array_merge($stats, $systemStats);
    }
    
    // Get today's activity count
    $todayQuery = $db->fetchOne("
        SELECT 
            COALESCE(COUNT(*), 0) as total_activities,
            COALESCE(COUNT(DISTINCT user_id), 0) as unique_users,
            COALESCE(COUNT(DISTINCT category), 0) as unique_categories
        FROM activity_logs 
        WHERE DATE(created_at) = CURDATE()
    ");
    
    if ($todayQuery) {
        $todayStats = $todayQuery;
    }

    // Get activity trend for last 7 days
    $weeklyTrendQuery = $db->fetchAll("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    
    if ($weeklyTrendQuery) {
        $weeklyTrend = $weeklyTrendQuery;
    }

    // Get top users this week
    $topUsersQuery = $db->fetchAll("
        SELECT 
            u.name,
            COUNT(*) as activity_count
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY u.id, u.name
        ORDER BY activity_count DESC
        LIMIT 5
    ");
    
    if ($topUsersQuery) {
        $topUsers = $topUsersQuery;
    }

    // Get recent activities
    $recentActivitiesQuery = $db->fetchAll("
        SELECT 
            al.*,
            u.name as user_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    
    if ($recentActivitiesQuery) {
        $recentActivities = $recentActivitiesQuery;
    }

    // Calculate percentage changes
    $yesterdayStats = $db->fetchOne("
        SELECT COALESCE(COUNT(*), 0) as total
        FROM activity_logs 
        WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ");
    
    if ($yesterdayStats && isset($yesterdayStats['total']) && $yesterdayStats['total'] > 0) {
        $activityChange = (($todayStats['total_activities'] - $yesterdayStats['total']) / $yesterdayStats['total']) * 100;
    }

    // Get users by role
    $usersByRoleQuery = $db->fetchAll("
        SELECT 
            role,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
        FROM users 
        GROUP BY role
    ");
    if ($usersByRoleQuery) {
        $usersByRole = $usersByRoleQuery;
    }

    // Get activity distribution by category
    $activityByCategoryQuery = $db->fetchAll("
        SELECT 
            category,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY category
        ORDER BY count DESC
        LIMIT 5
    ");
    if ($activityByCategoryQuery) {
        $activityByCategory = $activityByCategoryQuery;
    }

    // Get pending user approvals
    $pendingApprovalsQuery = $db->fetchAll("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            u.created_at
        FROM users u
        WHERE u.status = 'pending'
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    if ($pendingApprovalsQuery) {
        $pendingApprovals = $pendingApprovalsQuery;
    }

    // Get recent logins
    $recentLoginsQuery = $db->fetchAll("
        SELECT 
            u.name,
            al.created_at,
            al.ip_address,
            SUBSTRING_INDEX(al.user_agent, ' ', 1) as browser
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.category = 'auth' 
        AND al.action = 'login'
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    if ($recentLoginsQuery) {
        $recentLogins = $recentLoginsQuery;
    }

    // Get system health metrics
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $systemHealth['cpu_usage'] = $load[0] * 100;
    }
    $systemHealth['memory_usage'] = memory_get_usage(true) / memory_get_peak_usage(true) * 100;
    $systemHealth['disk_usage'] = round((disk_total_space("/") - disk_free_space("/")) / disk_total_space("/") * 100, 2);
    
    // Get last backup time from settings or dedicated table
    $lastBackupQuery = $db->fetchOne("
        SELECT value 
        FROM settings 
        WHERE name = 'last_backup_time'
    ");
    if ($lastBackupQuery) {
        $systemHealth['last_backup'] = $lastBackupQuery['value'];
    }

} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
}

// Ensure numeric values for stats
$todayStats['total_activities'] = intval($todayStats['total_activities']);
$todayStats['unique_users'] = intval($todayStats['unique_users']);
$todayStats['unique_categories'] = intval($todayStats['unique_categories']);
$stats['total_errors'] = intval($stats['total_errors']);
$stats['total_users'] = max(1, intval($stats['total_users'])); // Prevent division by zero
$activityChange = floatval($activityChange);

?>

<style>
.dashboard-container {
    margin-left: 256px;
    padding: 2rem;
    background: #f4f4f4;
    min-height: 100vh;
}

.stats-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-card .icon {
    width: 48px;
    height: 48px;
    background-color: #00572d;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.trend-up {
    color: #1f9345;
}

.trend-down {
    color: #dc3545;
}

.activity-item {
    padding: 1rem;
    border-left: 4px solid #00572d;
    margin-bottom: 1rem;
    background: white;
    border-radius: 0 8px 8px 0;
    transition: all 0.2s;
}

.activity-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.chart-container {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.user-rank {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 8px;
    background: white;
    margin-bottom: 0.5rem;
    border-left: 4px solid #f3c300;
}

.rank-number {
    width: 24px;
    height: 24px;
    background: #00572d;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.progress {
    height: 8px;
    border-radius: 4px;
}

.progress-bar {
    background-color: #00572d;
}

.health-indicator {
    width: 100%;
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    margin-top: 8px;
}

.health-indicator-bar {
    height: 100%;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.health-good {
    background-color: #1f9345;
}

.health-warning {
    background-color: #f3c300;
}

.health-critical {
    background-color: #dc3545;
}

.quick-action-card {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid #00572d;
    transition: transform 0.2s;
    cursor: pointer;
}

.quick-action-card:hover {
    transform: translateX(5px);
}

.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    font-size: 0.875rem;
    font-weight: 500;
}

.role-admin {
    background-color: rgba(0, 87, 45, 0.1);
    color: #00572d;
}

.role-user {
    background-color: rgba(31, 147, 69, 0.1);
    color: #1f9345;
}
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="color: #00572d;">Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></p>
            </div>
            <div>
            <a href="activity_reports.php" class="btn btn-primary" style="background-color: #00572d; border-color: #00572d;">
                <i class="fas fa-chart-line me-2"></i>View Reports
            </a>
        </div>
    </div>

    <!-- System Health Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">System Health</h5>
                    <button class="btn btn-sm btn-light" onclick="refreshSystemHealth()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>CPU Usage</span>
                                    <span><?php echo round($systemHealth['cpu_usage'], 1); ?>%</span>
                                </div>
                                <div class="health-indicator">
                                    <div class="health-indicator-bar <?php 
                                        echo $systemHealth['cpu_usage'] > 90 ? 'health-critical' : 
                                            ($systemHealth['cpu_usage'] > 70 ? 'health-warning' : 'health-good'); 
                                    ?>" style="width: <?php echo $systemHealth['cpu_usage']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Memory Usage</span>
                                    <span><?php echo round($systemHealth['memory_usage'], 1); ?>%</span>
                                </div>
                                <div class="health-indicator">
                                    <div class="health-indicator-bar <?php 
                                        echo $systemHealth['memory_usage'] > 90 ? 'health-critical' : 
                                            ($systemHealth['memory_usage'] > 70 ? 'health-warning' : 'health-good'); 
                                    ?>" style="width: <?php echo $systemHealth['memory_usage']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Disk Usage</span>
                                    <span><?php echo $systemHealth['disk_usage']; ?>%</span>
                                </div>
                                <div class="health-indicator">
                                    <div class="health-indicator-bar <?php 
                                        echo $systemHealth['disk_usage'] > 90 ? 'health-critical' : 
                                            ($systemHealth['disk_usage'] > 70 ? 'health-warning' : 'health-good'); 
                                    ?>" style="width: <?php echo $systemHealth['disk_usage']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Last Backup</span>
                                    <span><?php echo $systemHealth['last_backup'] ? date('M d, H:i', strtotime($systemHealth['last_backup'])) : 'Never'; ?></span>
                                </div>
                                <button class="btn btn-sm btn-success w-100 mt-2" onclick="initiateBackup()">
                                    <i class="fas fa-database"></i> Backup Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1"><?php echo number_format($todayStats['total_activities']); ?></h3>
                        <p class="text-muted mb-0">Today's Activities</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="<?php echo $activityChange >= 0 ? 'trend-up' : 'trend-down'; ?>">
                        <i class="fas fa-<?php echo $activityChange >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                        <?php echo abs(round($activityChange, 1)); ?>%
                    </span>
                    <span class="text-muted ms-2">vs yesterday</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
            <div>
                        <h3 class="mb-1"><?php echo number_format($todayStats['unique_users']); ?></h3>
                        <p class="text-muted mb-0">Active Users</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo ($todayStats['unique_users'] / $stats['total_users']) * 100; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1"><?php echo number_format($stats['total_errors']); ?></h3>
                        <p class="text-muted mb-0">Total Errors</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted">Error Rate: </span>
                    <span class="<?php echo ($stats['total_errors'] / $stats['total_activities']) * 100 < 5 ? 'trend-up' : 'trend-down'; ?>">
                        <?php echo number_format(($stats['total_errors'] / $stats['total_activities']) * 100, 1); ?>%
                    </span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
            <div>
                        <h3 class="mb-1"><?php echo number_format($todayStats['unique_categories']); ?></h3>
                        <p class="text-muted mb-0">Active Categories</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted">Categories active today</span>
                </div>
            </div>
        </div>
            </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="chart-container">
                <h5 class="mb-4" style="color: #00572d;">Activity Trend</h5>
                <canvas id="activityTrend" height="300"></canvas>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="chart-container">
                <h5 class="mb-4" style="color: #00572d;">Top Users This Week</h5>
                <?php foreach ($topUsers as $index => $user): ?>
                <div class="user-rank">
                    <div class="rank-number"><?php echo $index + 1; ?></div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                            <span class="badge bg-success"><?php echo number_format($user['activity_count']); ?></span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar" style="width: <?php 
                                echo ($user['activity_count'] / $topUsers[0]['activity_count']) * 100;
                            ?>%"></div>
                        </div>
                    </div>
            </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- User Management Overview -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">User Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($usersByRole as $roleData): ?>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded">
                                <h6><?php echo ucfirst($roleData['role']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h4 mb-0"><?php echo $roleData['count']; ?></span>
                                    <span class="text-success">
                                        <?php echo round(($roleData['active_count'] / $roleData['count']) * 100); ?>% Active
                                    </span>
                                </div>
                                <div class="progress mt-2" style="height: 4px;">
                                    <div class="progress-bar bg-success" style="width: <?php 
                                        echo ($roleData['active_count'] / $roleData['count']) * 100;
                                    ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
            </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Pending Approvals</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingApprovals)): ?>
                        <p class="text-muted text-center mb-0">No pending approvals</p>
                    <?php else: ?>
                        <?php foreach ($pendingApprovals as $pending): ?>
                        <div class="quick-action-card">
                            <div class="d-flex justify-content-between align-items-center">
                <div>
                                    <strong><?php echo htmlspecialchars($pending['name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($pending['email']); ?></small>
                                </div>
                                <span class="role-badge role-<?php echo strtolower($pending['role']); ?>">
                                    <?php echo ucfirst($pending['role']); ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-success" onclick="approveUser(<?php echo $pending['id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="rejectUser(<?php echo $pending['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                </div>
            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity and Login History -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Recent Logins</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($recentLogins as $login): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-light rounded-circle p-2 me-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($login['name']); ?></strong>
                                <small class="text-muted"><?php echo date('H:i', strtotime($login['created_at'])); ?></small>
                            </div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($login['browser']); ?> • <?php echo htmlspecialchars($login['ip_address']); ?>
                            </small>
                </div>
            </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Recent Activities</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
                                <span class="text-muted mx-2">•</span>
                                <span class="badge" style="background-color: #1f9345;">
                                    <?php echo htmlspecialchars($activity['category']); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                            </small>
            </div>
                        <p class="mb-0 mt-2"><?php echo htmlspecialchars($activity['message']); ?></p>
                                </div>
                        <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <a href="activity_reports.php" class="btn btn-primary" style="background-color: #00572d; border-color: #00572d;">
                            View All Activities
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activity Trend Chart
    const trendData = <?php echo json_encode($weeklyTrend); ?>;
    const ctx = document.getElementById('activityTrend').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(item => new Date(item.date).toLocaleDateString()),
            datasets: [{
                label: 'Activities',
                data: trendData.map(item => item.count),
                borderColor: '#00572d',
                backgroundColor: 'rgba(0, 87, 45, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#00572d',
                pointBorderColor: '#fff',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});

function refreshSystemHealth() {
    fetch('ajax/system_health.php')
        .then(response => response.json())
        .then(data => {
            // Update health indicators
            updateHealthIndicator('cpu', data.cpu_usage);
            updateHealthIndicator('memory', data.memory_usage);
            updateHealthIndicator('disk', data.disk_usage);
        })
        .catch(error => console.error('Error:', error));
}

function updateHealthIndicator(type, value) {
    const indicator = document.querySelector(`.health-indicator-${type}`);
    if (indicator) {
        indicator.style.width = `${value}%`;
        indicator.className = `health-indicator-bar ${getHealthClass(value)}`;
    }
}

function getHealthClass(value) {
    if (value > 90) return 'health-critical';
    if (value > 70) return 'health-warning';
    return 'health-good';
}

function initiateBackup() {
    if (confirm('Are you sure you want to initiate a system backup?')) {
        fetch('ajax/backup.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup initiated successfully!');
                } else {
                    alert('Backup failed: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to initiate backup');
        });
    }
}

function approveUser(userId) {
    if (confirm('Are you sure you want to approve this user?')) {
        fetch('ajax/approve_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to approve user: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to approve user');
        });
    }
}

function rejectUser(userId) {
    if (confirm('Are you sure you want to reject this user?')) {
        fetch('ajax/reject_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: userId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to reject user: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to reject user');
        });
    }
}

// Refresh system health every 5 minutes
setInterval(refreshSystemHealth, 300000);
</script>

<?php include '../include/footer.php'; ?>