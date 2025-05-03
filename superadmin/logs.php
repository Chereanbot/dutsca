<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
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
$logs = [];
$totalLogs = 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;
$error = null;
$logTypes = [];
$users = [];

// Initialize stats with default values
$stats = [
    'total' => 0,
    'today' => 0,
    'errors' => 0,
    'warnings' => 0
];

try {
    // Get database connection
    $db = getDB();

    // Get statistics first
    $stats['total'] = intval($db->fetchOne("SELECT COUNT(*) as count FROM system_logs")['count'] ?? 0);
    $stats['today'] = intval($db->fetchOne("SELECT COUNT(*) as count FROM system_logs WHERE DATE(created_at) = CURDATE()")['count'] ?? 0);
    $stats['errors'] = intval($db->fetchOne("SELECT COUNT(*) as count FROM system_logs WHERE log_type = 'error'")['count'] ?? 0);
    $stats['warnings'] = intval($db->fetchOne("SELECT COUNT(*) as count FROM system_logs WHERE log_type = 'warning'")['count'] ?? 0);

    // Get filter parameters
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $logType = $_GET['log_type'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $searchTerm = $_GET['search'] ?? '';

    // Build the base query
    $query = "SELECT 
                l.*,
                u.name as user_name,
                u.email as user_email
              FROM system_logs l
              LEFT JOIN users u ON l.user_id = u.id
              WHERE 1=1";
    $params = [];

    // Add filters
    if ($startDate) {
        $query .= " AND l.created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $query .= " AND l.created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }
    if ($logType) {
        $query .= " AND l.log_type = ?";
        $params[] = $logType;
    }
    if ($userId) {
        $query .= " AND l.user_id = ?";
        $params[] = $userId;
    }
    if ($searchTerm) {
        $query .= " AND (l.message LIKE ? OR l.ip_address LIKE ? OR u.name LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // Get total count for pagination
    $countQuery = str_replace("SELECT l.*, u.name as user_name, u.email as user_email", "SELECT COUNT(*) as count", $query);
    $totalLogs = intval($db->fetchOne($countQuery, $params)['count'] ?? 0);

    // Add sorting and pagination
    $query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    // Get logs
    $logs = $db->fetchAll($query, $params) ?? [];

    // Get log types for filter
    $logTypes = $db->fetchAll("SELECT DISTINCT log_type FROM system_logs ORDER BY log_type") ?? [];

    // Get users for filter
    $users = $db->fetchAll("SELECT id, name FROM users ORDER BY name") ?? [];

} catch (Exception $e) {
    $error = 'Error loading logs: ' . $e->getMessage();
    // Initialize empty arrays if there was an error
    $logs = [];
    $logTypes = [];
    $users = [];
    $totalLogs = 0;
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

.logs-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.logs-table th {
    background-color: #00572d;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem;
}

.logs-table td {
    padding: 1rem;
    vertical-align: middle;
}

.log-type {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: capitalize;
}

.log-type-info { background-color: #cfe2ff; color: #084298; }
.log-type-success { background-color: #d1e7dd; color: #0f5132; }
.log-type-warning { background-color: #fff3cd; color: #664d03; }
.log-type-error { background-color: #f8d7da; color: #842029; }

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.pagination {
    margin: 0;
}

.page-link {
    color: #00572d;
    border: none;
    padding: 0.5rem 1rem;
    margin: 0 0.25rem;
    border-radius: 8px;
}

.page-link:hover {
    background-color: #e9ecef;
    color: #00572d;
}

.page-item.active .page-link {
    background-color: #00572d;
    color: white;
}

.btn-export {
    background-color: #1f9345;
    color: white;
    border: none;
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-export:hover {
    background-color: #167c35;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    color: white;
}

.btn-clear {
    background-color: #dc3545;
    color: white;
}

.btn-clear:hover {
    background-color: #bb2d3b;
    color: white;
}
</style>

<div class="main-content">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: #00572d; font-weight: 700;">System Logs</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: #1f9345;">Dashboard</a></li>
                    <li class="breadcrumb-item active">System Logs</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-export" onclick="exportLogs()">
                <i class="fas fa-download me-2"></i>Export Logs
            </button>
            <button type="button" class="btn btn-clear" onclick="clearLogs()">
                <i class="fas fa-trash me-2"></i>Clear Logs
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-primary">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stats-label">Total Logs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-success">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['today']); ?></div>
                <div class="stats-label">Today's Logs</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['warnings']); ?></div>
                <div class="stats-label">Warnings</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['errors']); ?></div>
                <div class="stats-label">Errors</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
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
                <label class="form-label">Log Type</label>
                <select class="form-select" name="log_type">
                    <option value="">All Types</option>
                    <?php foreach ($logTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type['log_type']); ?>" 
                            <?php echo $logType === $type['log_type'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(htmlspecialchars($type['log_type'])); ?>
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
                <input type="text" class="form-control" name="search" 
                       value="<?php echo htmlspecialchars($searchTerm); ?>" 
                       placeholder="Search logs...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="logs-table table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Type</th>
                    <th>User</th>
                    <th>Message</th>
                    <th>IP Address</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-search mb-2" style="font-size: 24px;"></i>
                            <p class="mb-0">No logs found</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                    <td>
                        <span class="log-type log-type-<?php echo $log['log_type']; ?>">
                            <?php echo ucfirst($log['log_type']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($log['user_id']): ?>
                        <div class="d-flex flex-column">
                            <span class="fw-medium"><?php echo htmlspecialchars($log['user_name']); ?></span>
                            <small class="text-muted"><?php echo htmlspecialchars($log['user_email']); ?></small>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">System</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($log['message']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick="viewLogDetails(<?php echo $log['id']; ?>)">
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
    <?php if ($totalLogs > $perPage): ?>
    <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-muted">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalLogs); ?> 
            of <?php echo number_format($totalLogs); ?> entries
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination">
                <?php
                $totalPages = ceil($totalLogs / $perPage);
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

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function viewLogDetails(logId) {
    // Show loading state
    $('#logDetailsContent').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#logDetailsModal').modal('show');

    // Fetch log details
    $.ajax({
        url: 'log_actions.php',
        type: 'GET',
        data: {
            action: 'get_details',
            log_id: logId
        },
        success: function(response) {
            if (response.success) {
                $('#logDetailsContent').html(response.html);
            } else {
                $('#logDetailsContent').html('<div class="alert alert-danger">' + response.error + '</div>');
            }
        },
        error: function() {
            $('#logDetailsContent').html('<div class="alert alert-danger">Failed to load log details</div>');
        }
    });
}

function exportLogs() {
    Swal.fire({
        title: 'Export Logs',
        text: 'Do you want to export the filtered logs?',
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
            window.location.href = 'log_actions.php?' + params.toString();
        }
    });
}

function clearLogs() {
    Swal.fire({
        title: 'Clear Logs',
        text: 'Are you sure you want to clear all logs? This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, clear logs'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'log_actions.php',
                type: 'POST',
                data: {
                    action: 'clear_logs'
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.error
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'A network error occurred'
                    });
                }
            });
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