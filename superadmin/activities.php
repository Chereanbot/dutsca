<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/include/sidebar.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

// Initialize variables
$activities = [];
$totalActivities = 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;
$error = null;

// Get database connection and logger instance
$db = getDB();
$logger = Logger::getInstance();

try {
    // Get filter parameters
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $category = $_GET['category'] ?? '';
    $action = $_GET['action'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $searchTerm = $_GET['search'] ?? '';

    // Get system statistics
    $stats = $logger->getSystemStats();

    // Get activity summary
    $activitySummary = $logger->getActivitySummary($startDate ?: null, $endDate ?: null);

    // Build the base query
    $query = "
        SELECT 
            al.*,
            u.name as user_name,
            u.email as user_email
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1
    ";
    $params = [];

    // Add filters
    if ($startDate) {
        $query .= " AND al.created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $query .= " AND al.created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }
    if ($category) {
        $query .= " AND al.category = ?";
        $params[] = $category;
    }
    if ($action) {
        $query .= " AND al.action = ?";
        $params[] = $action;
    }
    if ($userId) {
        $query .= " AND al.user_id = ?";
        $params[] = $userId;
    }
    if ($searchTerm) {
        $query .= " AND (al.message LIKE ? OR al.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Get total count for pagination
    $countQuery = str_replace("SELECT al.*, u.name as user_name, u.email as user_email", "SELECT COUNT(*) as count", $query);
    $totalActivities = intval($db->fetchOne($countQuery, $params)['count'] ?? 0);

    // Add sorting and pagination
    $query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    // Get activities
    $activities = $db->fetchAll($query, $params) ?? [];

    // Get categories and actions for filters
    $categories = $db->fetchAll("SELECT DISTINCT category FROM activity_logs ORDER BY category") ?? [];
    $actions = $db->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action") ?? [];
    
    // Get users for filter
    $users = $db->fetchAll("SELECT id, name FROM users ORDER BY name") ?? [];

} catch (Exception $e) {
    $error = 'Error loading activities: ' . $e->getMessage();
    $activities = [];
    $categories = [];
    $actions = [];
    $users = [];
    $totalActivities = 0;
    $stats = [
        'total_users' => 0,
        'active_users_today' => 0,
        'total_activities' => 0,
        'total_errors' => 0
    ];
    $activitySummary = [];
}
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
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
}

.stats-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 1rem;
}

.stats-icon.bg-primary { background-color: #00572d; color: white; }
.stats-icon.bg-success { background-color: #1f9345; color: white; }
.stats-icon.bg-warning { background-color: #f3c300; color: black; }
.stats-icon.bg-danger { background-color: #dc3545; color: white; }

.stats-value {
    font-size: 24px;
    font-weight: 700;
    color: #333333;
    margin-bottom: 0.5rem;
}

.stats-label {
    color: #666666;
    font-size: 0.875rem;
}

.activity-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.activity-table th {
    background-color: #00572d;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem;
}

.activity-table td {
    padding: 1rem;
    vertical-align: middle;
}

.activity-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: capitalize;
}

.activity-badge-auth { background-color: #cfe2ff; color: #084298; }
.activity-badge-data { background-color: #d1e7dd; color: #0f5132; }
.activity-badge-system { background-color: #fff3cd; color: #664d03; }
.activity-badge-user { background-color: #f8d7da; color: #842029; }

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.summary-title {
    color: #00572d;
    font-weight: 600;
    margin-bottom: 1rem;
}

.summary-item {
    padding: 0.5rem;
    border-bottom: 1px solid #e9ecef;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-count {
    font-weight: 600;
    color: #1f9345;
}
</style>

<div class="main-content">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: #00572d; font-weight: 700;">User Activities</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: #1f9345;">Dashboard</a></li>
                    <li class="breadcrumb-item active">User Activities</li>
                </ol>
            </nav>
        </div>
        <div>
            <button type="button" class="btn btn-export" onclick="exportActivities()">
                <i class="fas fa-download me-2"></i>Export Activities
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stats-label">Total Users</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-success">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['active_users_today']); ?></div>
                <div class="stats-label">Active Users Today</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-warning">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['total_activities']); ?></div>
                <div class="stats-label">Total Activities</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['total_errors']); ?></div>
                <div class="stats-label">System Errors</div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Filters -->
        <div class="col-md-9">
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($cat['category'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Action</label>
                        <select class="form-select" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                            <option value="<?php echo htmlspecialchars($act['action']); ?>" 
                                    <?php echo $action === $act['action'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($act['action'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">User</label>
                        <select class="form-select" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                   placeholder="Search...">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Summary -->
        <div class="col-md-3">
            <div class="summary-card">
                <h6 class="summary-title">Activity Summary</h6>
                <?php foreach ($activitySummary as $summary): ?>
                <div class="summary-item d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-medium"><?php echo ucfirst($summary['category']); ?></div>
                        <small class="text-muted"><?php echo ucfirst($summary['action']); ?></small>
                    </div>
                    <span class="summary-count"><?php echo number_format($summary['count']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Activities Table -->
    <div class="activity-table table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Category</th>
                    <th>Action</th>
                    <th>Message</th>
                    <th>IP Address</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activities)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search mb-2" style="font-size: 24px;"></i>
                            <p class="mb-0">No activities found</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($activity['created_at'])); ?></td>
                    <td>
                        <?php if ($activity['user_id']): ?>
                        <div class="d-flex flex-column">
                            <span class="fw-medium"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                            <small class="text-muted"><?php echo htmlspecialchars($activity['user_email']); ?></small>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">System</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="activity-badge activity-badge-<?php echo $activity['category']; ?>">
                            <?php echo ucfirst($activity['category']); ?>
                        </span>
                    </td>
                    <td><?php echo ucfirst($activity['action']); ?></td>
                    <td><?php echo htmlspecialchars($activity['message']); ?></td>
                    <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick="viewActivityDetails(<?php echo $activity['id']; ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalActivities > $perPage): ?>
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-muted">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalActivities); ?> 
            of <?php echo number_format($totalActivities); ?> entries
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php
                $totalPages = ceil($totalActivities / $perPage);
                $maxPages = 5;
                $startPage = max(1, min($page - floor($maxPages / 2), $totalPages - $maxPages + 1));
                $endPage = min($startPage + $maxPages - 1, $totalPages);
                ?>

                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="First">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Previous">
                        <i class="fas fa-angle-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Next">
                        <i class="fas fa-angle-right"></i>
                    </a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>" aria-label="Last">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Activity Details Modal -->
<div class="modal fade" id="activityDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="activityDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function viewActivityDetails(activityId) {
    // Show loading state
    $('#activityDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#activityDetailsModal').modal('show');

    // Fetch activity details
    $.ajax({
        url: 'activity_actions.php',
        type: 'GET',
        data: {
            action: 'get_details',
            activity_id: activityId
        },
        success: function(response) {
            if (response.success) {
                $('#activityDetailsContent').html(response.html);
            } else {
                $('#activityDetailsContent').html('<div class="alert alert-danger">' + response.error + '</div>');
            }
        },
        error: function() {
            $('#activityDetailsContent').html('<div class="alert alert-danger">Failed to load activity details</div>');
        }
    });
}

function exportActivities() {
    Swal.fire({
        title: 'Export Activities',
        text: 'Do you want to export the filtered activities?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1f9345',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, export'
    }).then((result) => {
        if (result.isConfirmed) {
            // Get current filter parameters
            const params = new URLSearchParams(window.location.search);
            params.set('action', 'export');
            
            // Redirect to export endpoint
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