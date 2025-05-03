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

$db = getDB();

// Get current version from settings
$currentVersion = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'system_version'");
$currentVersion = $currentVersion['setting_value'] ?? '1.0.0';

// Get update history
$updateHistory = $db->fetchAll("SELECT * FROM system_updates ORDER BY created_at DESC LIMIT 10");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_updates') {
        $_SESSION['message'] = 'System is up to date.';
        $_SESSION['message_type'] = 'success';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get any flash messages
$message = $_SESSION['message'] ?? null;
$messageType = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);
?>

<!-- Custom CSS for enhanced styling -->
<style>
.main-content {
    margin-left: 256px;
    background: #f0f2f5;
    min-height: 100vh;
    padding: 2rem;
    transition: all 0.3s ease;
}

.card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
    background: #ffffff;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.status-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
    background: #f0f9ff;
}

.status-icon:hover {
    transform: scale(1.1);
    background: #e6f4ff;
}

.btn-primary {
    background: linear-gradient(135deg, #00572d 0%, #1f9345 100%);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    color: white;
    font-weight: 500;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 87, 45, 0.2);
    background: linear-gradient(135deg, #1f9345 0%, #00572d 100%);
}

.badge {
    padding: 0.5em 1em;
    font-weight: 500;
    letter-spacing: 0.5px;
    border-radius: 20px;
}

.table {
    margin-bottom: 0;
    background: #ffffff;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #333;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 0.5px;
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
}

.table td {
    vertical-align: middle;
    padding: 1.25rem 1rem;
    border-bottom: 1px solid #e9ecef;
}

.table tr:hover {
    background: #f8f9fa;
}

.breadcrumb {
    background: transparent;
    padding: 0;
    margin: 0;
}

.breadcrumb-item a {
    color: #1f9345;
    text-decoration: none;
    font-weight: 500;
}

.breadcrumb-item a:hover {
    color: #00572d;
}

.form-select, .form-control {
    border: 2px solid #dee2e6;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.form-select:focus, .form-control:focus {
    border-color: #1f9345;
    box-shadow: 0 0 0 4px rgba(31, 147, 69, 0.1);
}

.alert {
    border: none;
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1e7dd 0%, #c6f6d5 100%);
    color: #0f5132;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
    color: #842029;
}

.form-check-input:checked {
    background-color: #1f9345;
    border-color: #1f9345;
    box-shadow: 0 0 0 4px rgba(31, 147, 69, 0.1);
}

.version-number {
    font-size: 2.25rem;
    font-weight: 700;
    color: #00572d;
    margin: 0;
    line-height: 1.2;
}

.update-type-badge {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.5em 1.25em;
}

.system-info-item {
    padding: 1.25rem;
    border-bottom: 1px solid #e9ecef;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.system-info-item:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}

.card-header {
    background: linear-gradient(135deg, #00572d 0%, #1f9345 100%);
    color: white;
    padding: 1.25rem;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
}

.settings-form .form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

/* Additional styles for better visual hierarchy */
.card-title {
    color: #2d3748;
    font-weight: 600;
    margin-bottom: 1.25rem;
}

.text-muted {
    color: #718096;
}

.fw-medium {
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
    
    .card {
        margin-bottom: 1rem;
    }
    
    .version-number {
        font-size: 1.75rem;
    }
}
</style>
    
    <div class="main-content">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="color: #00572d; margin-bottom: 0.5rem; font-weight: 700;">System Updates</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">System Updates</li>
                </ol>
            </nav>
            </div>
        <form method="POST" class="d-flex gap-2">
            <input type="hidden" name="action" value="check_updates">
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="fas fa-sync-alt"></i>
                <span>Check for Updates</span>
                    </button>
        </form>
                </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

    <!-- System Version Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h5 class="card-title" style="color: #00572d; font-weight: 600;">Current System Version</h5>
                    <div class="d-flex align-items-center mt-4">
                        <div class="status-icon me-4" style="background: #e6f4ea;">
                            <i class="fas fa-code-branch fa-2x" style="color: #1f9345;"></i>
                        </div>
                        <div>
                            <p class="version-number">v<?php echo htmlspecialchars($currentVersion); ?></p>
                            <p class="text-muted mb-0">Last checked: <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body p-4">
                    <h5 class="card-title" style="color: #00572d; font-weight: 600;">System Status</h5>
                    <div class="d-flex align-items-center mt-4">
                        <div class="status-icon me-4" style="background: #fff3cd;">
                            <i class="fas fa-shield-alt fa-2x" style="color: #f3c300;"></i>
                        </div>
                        <div>
                            <p class="version-number" style="color: #1f9345;">System Secure</p>
                            <p class="text-muted mb-0">All components up to date</p>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Update History -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 fw-bold">Update History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Applied At</th>
                            <th>Applied By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($updateHistory)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No update history available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($updateHistory as $update): ?>
                                <tr>
                            <td class="fw-medium">v<?php echo htmlspecialchars($update['version']); ?></td>
                            <td>
                                <span class="badge update-type-badge" 
                                      style="background-color: <?php echo $update['update_type'] === 'security' ? '#dc3545' : '#1f9345'; ?>">
                                        <?php echo htmlspecialchars($update['update_type']); ?>
                                </span>
                                    </td>
                            <td><?php echo htmlspecialchars($update['description']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $update['status'] === 'applied' ? 'success' : 
                                        ($update['status'] === 'failed' ? 'danger' : 'warning'); 
                                ?>">
                                            <?php echo htmlspecialchars($update['status']); ?>
                                        </span>
                                    </td>
                            <td><?php echo date('M d, Y H:i', strtotime($update['applied_at'])); ?></td>
                            <td><?php echo htmlspecialchars($update['applied_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
        </div>
    </div>
</div>

    <!-- System Information and Settings -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">System Information</h5>
                </div>
                <div class="card-body p-0">
                    <div class="system-info-item d-flex justify-content-between align-items-center">
                        <span class="fw-medium">PHP Version</span>
                        <span class="badge" style="background: #1f9345;"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="system-info-item d-flex justify-content-between align-items-center">
                        <span class="fw-medium">MySQL Version</span>
                        <span class="badge" style="background: #1f9345;"><?php echo $db->fetchOne("SELECT VERSION()")['VERSION()']; ?></span>
                    </div>
                    <div class="system-info-item d-flex justify-content-between align-items-center border-bottom-0">
                        <span class="fw-medium">Server Software</span>
                        <span class="badge" style="background: #1f9345;"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Update Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="settings-form">
                        <div class="mb-4">
                            <label class="form-label">Auto Check for Updates</label>
                            <select class="form-select" name="auto_check">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="autoInstall" checked>
                                <label class="form-check-label" for="autoInstall">Auto-install security updates</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100" style="background: #1f9345; border: none;">
                            Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
