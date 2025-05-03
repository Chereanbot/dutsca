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

// Initialize variables
$db = getDB();
$error = null;
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Initialize arrays with default empty values
$dailyActivities = [];
$userActivities = [];
$categoryDistribution = [];
$actionDistribution = [];
$hourlyDistribution = [];
$browserStats = [];
$locationStats = [];

try {
    $logger = Logger::getInstance();
    $stats = $logger->getSystemStats();

    // Get daily activity summary
    $dailyActivityQuery = "
        SELECT 
            DATE(created_at) as date,
            category,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at), category
        ORDER BY date ASC
    ";
    $dailyActivities = $db->fetchAll($dailyActivityQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

    // Get user activity summary
    $userActivityQuery = "
        SELECT 
            u.name as user_name,
            COUNT(*) as total_activities,
            COUNT(DISTINCT DATE(al.created_at)) as active_days
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.created_at BETWEEN ? AND ?
        GROUP BY al.user_id, u.name
        ORDER BY total_activities DESC
        LIMIT 10
    ";
    $userActivities = $db->fetchAll($userActivityQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

    // Get activity distribution by category
    $categoryQuery = "
        SELECT 
            category,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at BETWEEN ? AND ?
        GROUP BY category
        ORDER BY count DESC
    ";
    $categoryDistribution = $db->fetchAll($categoryQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

    // Get activity distribution by action
    $actionQuery = "
        SELECT 
            action,
            COUNT(*) as count,
            COUNT(DISTINCT user_id) as unique_users
        FROM activity_logs
        WHERE created_at BETWEEN ? AND ?
        GROUP BY action
        ORDER BY count DESC
        LIMIT 10
    ";
    $actionDistribution = $db->fetchAll($actionQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

    // Fetch hourly distribution
    $hourlyQuery = "
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at BETWEEN ? AND ?
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ";
    $hourlyDistribution = $db->fetchAll($hourlyQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

    // Fetch browser statistics from additional_data
    $browserQuery = "
        SELECT 
            JSON_EXTRACT(additional_data, '$.user_agent') as browser,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at BETWEEN ? AND ?
        AND additional_data IS NOT NULL
        GROUP BY JSON_EXTRACT(additional_data, '$.user_agent')
        ORDER BY count DESC
        LIMIT 5
    ";
    $browserStats = $db->fetchAll($browserQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

    // Fetch location statistics from IP
    $locationQuery = "
        SELECT 
            ip_address,
            COUNT(*) as count
        FROM activity_logs
        WHERE created_at BETWEEN ? AND ?
        AND ip_address IS NOT NULL
        GROUP BY ip_address
        ORDER BY count DESC
        LIMIT 10
    ";
    $locationStats = $db->fetchAll($locationQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']) ?? [];

} catch (Exception $e) {
    $error = 'Error loading activity reports: ' . $e->getMessage();
    error_log('Activity Reports Error: ' . $e->getMessage());
}

// Calculate totals with error handling
$totalActivities = empty($categoryDistribution) ? 0 : array_sum(array_column($categoryDistribution, 'count'));
$totalUsers = empty($userActivities) ? 0 : count(array_unique(array_column($userActivities, 'user_name')));
$totalDays = empty($dailyActivities) ? 0 : count(array_unique(array_column($dailyActivities, 'date')));
$totalCategories = empty($categoryDistribution) ? 0 : count($categoryDistribution);
?>

<style>
.main-content {
    margin-left: 256px;
    padding: 2rem;
    background: #f8f9fa;
    min-height: 100vh;
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
    height: 100%;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.chart-container {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.activity-table th {
    background-color: #00572d;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.btn-primary {
    background-color: #00572d;
    border-color: #00572d;
}

.btn-primary:hover {
    background-color: #1f9345;
    border-color: #1f9345;
}

.badge-category {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 500;
}

.badge-auth { background-color: #cfe2ff; color: #084298; }
.badge-data { background-color: #d1e7dd; color: #0f5132; }
.badge-system { background-color: #fff3cd; color: #664d03; }
.badge-user { background-color: #f8d7da; color: #842029; }
</style>

<div class="main-content">
    <?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: #00572d; font-weight: 700;">Activity Reports</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: #1f9345;">Dashboard</a></li>
                    <li class="breadcrumb-item active">Activity Reports</li>
                </ol>
            </nav>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="exportReport()">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="chart-container mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter me-2"></i>Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Activity Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-primary p-3 me-3">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                    <h6 class="mb-0">Total Activities</h6>
                </div>
                <h3 class="mb-0"><?php echo number_format($totalActivities); ?></h3>
                <small class="text-muted">During selected period</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-success p-3 me-3">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <h6 class="mb-0">Active Users</h6>
                </div>
                <h3 class="mb-0"><?php echo number_format($totalUsers); ?></h3>
                <small class="text-muted">Users with activities</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-warning p-3 me-3">
                        <i class="fas fa-calendar-check text-white"></i>
                    </div>
                    <h6 class="mb-0">Active Days</h6>
                </div>
                <h3 class="mb-0"><?php echo number_format($totalDays); ?></h3>
                <small class="text-muted">Days with activities</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="d-flex align-items-center mb-3">
                    <div class="rounded-circle bg-danger p-3 me-3">
                        <i class="fas fa-chart-pie text-white"></i>
                    </div>
                    <h6 class="mb-0">Categories</h6>
                </div>
                <h3 class="mb-0"><?php echo number_format($totalCategories); ?></h3>
                <small class="text-muted">Activity categories</small>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <?php if (!$error): ?>
    <div class="row mb-4">
        <!-- Daily Activity Trend -->
        <div class="col-md-8">
            <div class="chart-container">
                <h5 class="mb-4">Daily Activity Trend</h5>
                <canvas id="dailyActivityChart"></canvas>
            </div>
        </div>

        <!-- Category Distribution -->
        <div class="col-md-4">
            <div class="chart-container">
                <h5 class="mb-4">Category Distribution</h5>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Top Actions -->
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="mb-4">Top Actions</h5>
                <div class="table-responsive">
                    <?php if (empty($actionDistribution)): ?>
                    <p class="text-muted text-center py-4">No actions recorded in the selected period</p>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">Unique Users</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actionDistribution as $action): ?>
                            <tr>
                                <td><?php echo ucfirst(htmlspecialchars($action['action'])); ?></td>
                                <td class="text-end"><?php echo number_format($action['count']); ?></td>
                                <td class="text-end"><?php echo number_format($action['unique_users']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Most Active Users -->
        <div class="col-md-6">
            <div class="chart-container">
                <h5 class="mb-4">Most Active Users</h5>
                <div class="table-responsive">
                    <?php if (empty($userActivities)): ?>
                    <p class="text-muted text-center py-4">No user activities recorded in the selected period</p>
                    <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th class="text-end">Activities</th>
                                <th class="text-end">Active Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userActivities as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-medium"><?php echo htmlspecialchars($user['user_name']); ?></span>
                                    </div>
                                </td>
                                <td class="text-end"><?php echo number_format($user['total_activities']); ?></td>
                                <td class="text-end"><?php echo number_format($user['active_days']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if (!$error): ?>
// Prepare data for daily activity chart
const dailyData = <?php echo json_encode($dailyActivities); ?>;
const dates = [...new Set(dailyData.map(item => item.date))];
const categories = [...new Set(dailyData.map(item => item.category))];

const datasets = categories.map(category => {
    return {
        label: category,
        data: dates.map(date => {
            const entry = dailyData.find(item => item.date === date && item.category === category);
            return entry ? entry.count : 0;
        }),
        fill: false
    };
});

// Create daily activity chart
if (document.getElementById('dailyActivityChart')) {
    new Chart(document.getElementById('dailyActivityChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: datasets
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

// Create category distribution chart
if (document.getElementById('categoryChart')) {
    new Chart(document.getElementById('categoryChart'), {
        type: 'pie',
        data: {
            labels: categoryData.map(item => item.category),
            datasets: [{
                data: categoryData.map(item => item.count),
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Hourly Activity Chart
const hourlyData = <?php echo json_encode($hourlyDistribution); ?>;
if (document.getElementById('hourlyChart')) {
    new Chart(document.getElementById('hourlyChart'), {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => `${i}:00`),
            datasets: [{
                label: 'Activities',
                data: Array.from({length: 24}, (_, hour) => {
                    const entry = hourlyData.find(item => parseInt(item.hour) === hour);
                    return entry ? entry.count : 0;
                }),
                backgroundColor: colors.primary
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
                    beginAtZero: true
                }
            }
        }
    });
}

// Browser Distribution Chart
const browserData = <?php echo json_encode($browserStats); ?>;
if (document.getElementById('browserChart')) {
    new Chart(document.getElementById('browserChart'), {
        type: 'pie',
        data: {
            labels: browserData.map(item => item.browser),
            datasets: [{
                data: browserData.map(item => item.count),
                backgroundColor: [
                    colors.primary,
                    colors.secondary,
                    colors.accent,
                    '#4e73df',
                    '#1cc88a'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Initialize Location Map
if (document.getElementById('locationMap')) {
    const map = L.map('locationMap').setView([0, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Add markers for each location
    <?php foreach ($locationStats as $location): ?>
    L.marker([0, 0]) // You'll need to get actual coordinates from IP
        .bindPopup('IP: <?php echo htmlspecialchars($location['ip_address']); ?><br>Count: <?php echo number_format($location['count']); ?>')
        .addTo(map);
    <?php endforeach; ?>
}
<?php endif; ?>

function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'export_report');
    
    Swal.fire({
        title: 'Export Report',
        text: 'Do you want to export this activity report?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1f9345',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, export'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'activity_actions.php?' + params.toString();
        }
    });
}

// Initialize date range picker if available
$(document).ready(function() {
    if ($.fn.daterangepicker) {
        $('input[name="start_date"], input[name="end_date"]').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            locale: {
                format: 'YYYY-MM-DD'
            }
        });
    }
});
</script>

<!-- Include Leaflet for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

<?php include '../include/footer.php'; ?> 