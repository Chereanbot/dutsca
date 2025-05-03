<?php
require_once '../config/database.php';
require_once '../config/config.php';


// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

// Get database connection
$db = getDB();

// Initialize variables
$success = false;
$error = '';
$logs = [];
$backups = [];
$backupConfig = [];

try {
    // Get backup statistics
    $stats = [
        'total' => $db->fetchOne("SELECT COUNT(*) as count FROM backups")['count'],
        'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM backups WHERE status = 'completed'")['count'],
        'pending' => $db->fetchOne("SELECT COUNT(*) as count FROM backups WHERE status = 'pending'")['count'],
        'failed' => $db->fetchOne("SELECT COUNT(*) as count FROM backups WHERE status = 'failed'")['count'],
        'total_size' => $db->fetchOne("SELECT SUM(size) as total FROM backups WHERE status = 'completed'")['total'] ?? 0
    ];

    // Get backup configuration
    $configResult = $db->fetchOne("SELECT value FROM settings WHERE name = 'backup_config'");
    if ($configResult) {
        $backupConfig = json_decode($configResult['value'], true) ?? [];
    }
    
    // Set defaults if not found
    $backupConfig = array_merge([
        'frequency' => 'daily',
        'retention' => 30,
        'compression' => 'gzip',
        'encryption' => true
    ], $backupConfig);

    // Get backup history
    $backups = $db->fetchAll("
        SELECT 
            b.*,
            u.name as creator_name
        FROM backups b
        LEFT JOIN users u ON b.created_by = u.id
        WHERE b.status != 'deleted'
        ORDER BY b.created_at DESC 
        LIMIT 50
    ");

} catch (Exception $e) {
    $error = 'Error loading backup configuration: ' . $e->getMessage();
}

// Get any flash messages
$message = $_SESSION['message'] ?? null;
$messageType = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

include 'include/header.php';
include 'include/sidebar.php';
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

.backup-table {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.backup-table th {
    background-color: #00572d;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem;
}

.backup-table td {
    padding: 1rem;
    vertical-align: middle;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-completed { background-color: #1f9345; color: white; }
.status-pending { background-color: #f3c300; color: black; }
.status-failed { background-color: #dc3545; color: white; }

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-2px);
}

.btn-download { background-color: #00572d; }
.btn-verify { background-color: #1f9345; }
.btn-delete { background-color: #dc3545; }

.config-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.config-card h5 {
    color: #00572d;
    font-weight: 700;
    margin-bottom: 1.5rem;
}

.form-label {
    color: #333333;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    padding: 0.625rem;
}

.form-control:focus, .form-select:focus {
    border-color: #1f9345;
    box-shadow: 0 0 0 0.25rem rgba(31, 147, 69, 0.25);
}

.btn-primary {
    background-color: #00572d;
    border: none;
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: #1f9345;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.alert {
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border: none;
}

.alert-success {
    background-color: #d1e7dd;
    color: #0f5132;
}

.alert-danger {
    background-color: #f8d7da;
    color: #842029;
}
</style>

<div class="main-content">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: #00572d; font-weight: 700;">Backup Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: #1f9345;">Dashboard</a></li>
                    <li class="breadcrumb-item active">Backup Management</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" onclick="showScheduleModal()">
                <i class="fas fa-clock"></i>
                Schedule Backup
            </button>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 create-backup-btn">
                <i class="fas fa-plus"></i>
                Create Backup
            </button>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> d-flex align-items-center">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-primary">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stats-label">Total Backups</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['completed']); ?></div>
                <div class="stats-label">Completed</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-value"><?php echo number_format($stats['pending']); ?></div>
                <div class="stats-label">Pending</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon bg-primary">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stats-value"><?php echo formatSize($stats['total_size']); ?></div>
                <div class="stats-label">Total Size</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Backup List -->
        <div class="col-md-8">
            <div class="backup-table table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Backup ID</th>
                            <th>Created At</th>
                            <th>Size</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-database mb-2" style="font-size: 24px;"></i>
                                    <p class="mb-0">No backups available</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td class="fw-medium"><?php echo htmlspecialchars($backup['id']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($backup['created_at'])); ?></td>
                            <td><?php echo formatSize($backup['size']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $backup['status']; ?>">
                                    <?php echo ucfirst($backup['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($backup['creator_name'] ?? 'System'); ?></td>
                            <td class="text-end">
                                <?php if ($backup['status'] === 'completed'): ?>
                                <button type="button" class="action-btn btn-download" onclick="downloadBackup('<?php echo $backup['id']; ?>')" title="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button type="button" class="action-btn btn-verify" onclick="verifyBackup('<?php echo $backup['id']; ?>')" title="Verify">
                                    <i class="fas fa-check"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="action-btn btn-delete" onclick="deleteBackup('<?php echo $backup['id']; ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Configuration -->
        <div class="col-md-4">
            <div class="config-card">
                <h5>Backup Configuration</h5>
                <form method="POST" class="config-form">
                    <input type="hidden" name="action" value="save_config">
                    
                    <div class="mb-3">
                        <label class="form-label">Backup Frequency</label>
                        <select name="frequency" class="form-select">
                            <option value="hourly" <?php echo $backupConfig['frequency'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                            <option value="daily" <?php echo $backupConfig['frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $backupConfig['frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $backupConfig['frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Retention Period (days)</label>
                        <input type="number" name="retention" class="form-control" 
                               value="<?php echo htmlspecialchars($backupConfig['retention']); ?>" 
                               min="1" max="365">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Compression Method</label>
                        <select name="compression" class="form-select">
                            <option value="none" <?php echo $backupConfig['compression'] === 'none' ? 'selected' : ''; ?>>None</option>
                            <option value="gzip" <?php echo $backupConfig['compression'] === 'gzip' ? 'selected' : ''; ?>>GZip</option>
                            <option value="zip" <?php echo $backupConfig['compression'] === 'zip' ? 'selected' : ''; ?>>ZIP</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="encryption" class="form-check-input" 
                                   id="encryption" <?php echo $backupConfig['encryption'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="encryption">Enable Encryption</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-save"></i>
                        Save Configuration
                    </button>
                </form>
            </div>

            <?php if (!empty($logs)): ?>
            <div class="config-card mt-4">
                <h5>Operation Logs</h5>
                <div class="logs-container" style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($logs as $log): ?>
                    <div class="log-entry d-flex align-items-center gap-2 mb-2">
                        <i class="fas fa-info-circle text-primary"></i>
                        <small class="text-muted"><?php echo htmlspecialchars($log); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Modals -->
<?php include 'templates/backup_schedule_modal.php'; ?>

<script>
// Debug logging
console.log('Script loaded');

function createBackup() {
    console.log('createBackup function called');
    
    // Show loading state
    Swal.fire({
        title: 'Creating Backup',
        html: 'Please wait while we create your backup...',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    // Make AJAX request
    $.ajax({
        url: 'backup_actions.php',
        type: 'POST',
        data: {
            action: 'create_backup'
        },
        dataType: 'json',
        success: function(response) {
            console.log('Backup response:', response);
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
                    text: response.error || 'Failed to create backup'
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'A network error occurred. Please try again.'
            });
        }
    });
}

function downloadBackup(backupId) {
    window.location.href = `backup_actions.php?action=download&id=${backupId}`;
}

function verifyBackup(backupId) {
    Swal.fire({
        title: 'Verify Backup',
        text: 'Do you want to verify this backup?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#00572d',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, verify it'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'backup_actions.php',
                type: 'POST',
                data: {
                    action: 'verify_backup',
                    backup_id: backupId
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
                            title: 'Error!',
                            text: response.error
                        });
                    }
                }
            });
        }
    });
}

function deleteBackup(backupId) {
    Swal.fire({
        title: 'Delete Backup',
        text: 'Are you sure you want to delete this backup?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'backup_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_backup',
                    backup_id: backupId
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
                            title: 'Error!',
                            text: response.error
                        });
                    }
                }
            });
        }
    });
}

function showScheduleModal() {
    $('#scheduleBackupModal').modal('show');
}

<?php
function formatSize($bytes) {
    if ($bytes === 0 || $bytes === null) return '0 Bytes';
    $k = 1024;
    $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>

// Make sure jQuery and SweetAlert2 are loaded
$(document).ready(function() {
    console.log('Document ready');
    
    // Check if required libraries are loaded
    console.log('jQuery version:', $.fn.jquery);
    console.log('SweetAlert2 version:', Swal.version);
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Add click handler for create backup button
    $('.create-backup-btn').on('click', function() {
        console.log('Create backup button clicked');
        createBackup();
    });
    
    // Log if button exists
    console.log('Create backup button found:', $('.create-backup-btn').length);
});
</script>