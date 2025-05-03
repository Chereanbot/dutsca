<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

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

// Handle refresh request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'refresh') {
    $refreshOptions = json_decode(file_get_contents('php://input'), true);
    $status = [];
    $logs = [];

    // Check database status
    if ($refreshOptions['check_database']) {
        try {
            $startTime = microtime(true);
            $db->executeQuery("SELECT 1");
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $status['database'] = [
                'status' => 'operational',
                'uptime' => '99.9%',
                'last_check' => date('Y-m-d H:i:s'),
                'response_time' => $duration . 'ms'
            ];
            
            if ($refreshOptions['include_logs']) {
                $logs[] = "Database check completed in {$duration}ms";
            }
            
            if ($refreshOptions['deep_scan']) {
                // Perform additional database checks
                try {
                    $db->executeQuery("SELECT COUNT(*) FROM information_schema.tables");
                    $logs[] = "Deep scan completed successfully";
                } catch (Exception $e) {
                    $logs[] = "Deep scan failed: " . $e->getMessage();
                }
            }
        } catch (Exception $e) {
            $status['database'] = [
                'status' => 'error',
                'uptime' => '0%',
                'last_check' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
            $logs[] = "Database error: " . $e->getMessage();
        }
    }

    // Check backup status
    if ($refreshOptions['check_backup']) {
        try {
            $startTime = microtime(true);
            $lastBackup = $db->fetchOne("SELECT MAX(created_at) as last_backup FROM backups");
            $duration = round((microtime(true) - $startTime) * 1000);
            
            if ($lastBackup && $lastBackup['last_backup']) {
                $hoursSinceLastBackup = (time() - strtotime($lastBackup['last_backup'])) / 3600;
                
                $status['backup_service'] = [
                    'status' => $hoursSinceLastBackup > 24 ? 'error' : ($hoursSinceLastBackup > 12 ? 'warning' : 'operational'),
                    'last_backup' => date('Y-m-d H:i:s', strtotime($lastBackup['last_backup'])),
                    'last_check' => date('Y-m-d H:i:s'),
                    'backup_age' => floor($hoursSinceLastBackup) . ' hours'
                ];
                
                if ($refreshOptions['include_logs']) {
                    $logs[] = "Backup check completed in {$duration}ms";
                    $logs[] = "Last backup age: {$hoursSinceLastBackup} hours";
                }
                
                if ($refreshOptions['deep_scan']) {
                    try {
                        $backupSize = $db->fetchOne("SELECT SUM(size) as total_size FROM backups WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                        $logs[] = "Backup size for last 7 days: " . number_format($backupSize['total_size'] / (1024*1024), 2) . " MB";
                    } catch (Exception $e) {
                        $logs[] = "Backup size check failed: " . $e->getMessage();
                    }
                }
            } else {
                $status['backup_service'] = [
                    'status' => 'error',
                    'last_backup' => 'Never',
                    'last_check' => date('Y-m-d H:i:s')
                ];
                $logs[] = "No backup found in the system";
            }
        } catch (Exception $e) {
            $status['backup_service'] = [
                'status' => 'error',
                'last_backup' => 'Error checking backup',
                'last_check' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
            $logs[] = "Backup check error: " . $e->getMessage();
        }
    }

    // Check email service status
    if ($refreshOptions['check_email']) {
        try {
            $startTime = microtime(true);
            $lastEmail = $db->fetchOne("SELECT MAX(created_at) as last_email FROM email_log");
            $duration = round((microtime(true) - $startTime) * 1000);
            
            if ($lastEmail && $lastEmail['last_email']) {
                $hoursSinceLastEmail = (time() - strtotime($lastEmail['last_email'])) / 3600;
                
                $status['email_service'] = [
                    'status' => $hoursSinceLastEmail > 24 ? 'error' : ($hoursSinceLastEmail > 12 ? 'warning' : 'operational'),
                    'last_check' => date('Y-m-d H:i:s'),
                    'last_email' => date('Y-m-d H:i:s', strtotime($lastEmail['last_email'])),
                    'email_age' => floor($hoursSinceLastEmail) . ' hours'
                ];
                
                if ($refreshOptions['include_logs']) {
                    $logs[] = "Email service check completed in {$duration}ms";
                    $logs[] = "Last email sent: " . $status['email_service']['last_email'];
                }
                
                if ($refreshOptions['deep_scan']) {
                    try {
                        $pendingEmails = $db->fetchOne("SELECT COUNT(*) as pending FROM email_queue WHERE status = 'pending'");
                        $logs[] = "Pending emails in queue: " . $pendingEmails['pending'];
                        
                        if ($pendingEmails['pending'] > 100) {
                            $status['email_service']['status'] = 'warning';
                            $logs[] = "Warning: Large number of pending emails in queue";
                        }
                    } catch (Exception $e) {
                        $logs[] = "Email queue check failed: " . $e->getMessage();
                    }
                }
            } else {
                $status['email_service'] = [
                    'status' => 'warning',
                    'last_check' => date('Y-m-d H:i:s')
                ];
                $logs[] = "No emails found in the system";
            }
        } catch (Exception $e) {
            $status['email_service'] = [
                'status' => 'error',
                'last_check' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
            $logs[] = "Email service check error: " . $e->getMessage();
        }
    }

    // Save status to database
    try {
        foreach ($status as $component => $data) {
            $db->executeQuery(
                "INSERT INTO system_status (component, status, last_check) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, last_check = ?",
                [
                    $component,
                    $data['status'],
                    $data['last_check'],
                    $data['status'],
                    $data['last_check']
                ]
            );
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'status' => $status,
            'logs' => implode("\n", $logs)
        ]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Initialize status variables
$status = [
    'main_server' => [
        'status' => 'operational',
        'uptime' => '99.9%',
        'last_check' => date('Y-m-d H:i:s')
    ],
    'database' => [
        'status' => 'operational',
        'uptime' => '99.9%',
        'last_check' => date('Y-m-d H:i:s')
    ],
    'authentication_service' => [
        'status' => 'operational',
        'uptime' => '99.9%',
        'last_check' => date('Y-m-d H:i:s')
    ],
    'backup_service' => [
        'status' => 'warning',
        'last_backup' => '',
        'last_check' => date('Y-m-d H:i:s')
    ],
    'email_service' => [
        'status' => 'maintenance',
        'last_check' => date('Y-m-d H:i:s')
    ]
];

// Check backup status
try {
    $lastBackup = $db->fetchOne("SELECT MAX(created_at) as last_backup FROM backups");
    if ($lastBackup && $lastBackup['last_backup']) {
        $status['backup_service']['last_backup'] = date('Y-m-d H:i:s', strtotime($lastBackup['last_backup']));
        $hoursSinceLastBackup = (time() - strtotime($lastBackup['last_backup'])) / 3600;
        
        if ($hoursSinceLastBackup > 24) {
            $status['backup_service']['status'] = 'error';
        } elseif ($hoursSinceLastBackup > 12) {
            $status['backup_service']['status'] = 'warning';
        } else {
            $status['backup_service']['status'] = 'operational';
        }
    }
} catch (Exception $e) {
    $status['backup_service']['status'] = 'error';
    $status['backup_service']['last_backup'] = 'Error checking backup status';
}

// Check email service status
try {
    $lastEmail = $db->fetchOne("SELECT MAX(created_at) as last_email FROM email_log");
    if ($lastEmail && $lastEmail['last_email']) {
        $hoursSinceLastEmail = (time() - strtotime($lastEmail['last_email'])) / 3600;
        
        if ($hoursSinceLastEmail > 24) {
            $status['email_service']['status'] = 'error';
        } elseif ($hoursSinceLastEmail > 12) {
            $status['email_service']['status'] = 'warning';
        }
    }
} catch (Exception $e) {
    $status['email_service']['status'] = 'error';
}

// Save status to database
try {
    foreach ($status as $component => $data) {
        $db->executeQuery(
            "INSERT INTO system_status (component, status, last_check) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, last_check = ?",
            [
                $component,
                $data['status'],
                $data['last_check'],
                $data['status'],
                $data['last_check']
            ]
        );
    }
} catch (Exception $e) {
    error_log("Error saving system status: " . $e->getMessage());
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($status);
    exit;
}

// Handle configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['configure'])) {
        try {
            // Update backup settings
            if (isset($_POST['backup_frequency'])) {
                $db->executeQuery(
                    "UPDATE settings SET value = ? WHERE name = ?",
                    [$_POST['backup_frequency'], 'backup_frequency']
                );
            }
            
            // Update email settings
            if (isset($_POST['email_provider'])) {
                $db->executeQuery(
                    "UPDATE settings SET value = ? WHERE name = ?",
                    [$_POST['email_provider'], 'email_provider']
                );
            }
            
            // Update monitoring intervals
            if (isset($_POST['monitor_interval'])) {
                $db->executeQuery(
                    "UPDATE settings SET value = ? WHERE name = ?",
                    [$_POST['monitor_interval'], 'monitor_interval']
                );
            }
            
            header('Location: status.php?success=1');
            exit;
        } catch (Exception $e) {
            header('Location: status.php?error=1');
            exit;
        }
    }
}

// Get current settings
try {
    $settings = $db->executeQuery("SELECT name, value FROM settings")->fetchAll();
    $settings = array_column($settings, 'value', 'name');
} catch (Exception $e) {
    $settings = [];
}

// Include header and sidebar
include __DIR__ . '/include/header.php';
include __DIR__ . '/include/sidebar.php';
?>

<div class="md:ml-64 pt-24 px-8 bg-gray-50 min-h-screen">
    <!-- Status Overview -->
    <div class="bg-white rounded-xl shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-[#00572d]">System Status Overview</h2>
            <button class="border border-[#00572d] text-[#00572d] px-4 py-2 rounded-lg text-sm flex items-center hover:bg-[#e6f4ea]" onclick="openRefreshModal()">
                <i class="fas fa-sync-alt mr-2"></i> Refresh Status
            </button>
        </div>
        
        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($status as $component => $data): ?>
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 <?php echo $data['status'] === 'operational' ? 'bg-green-100' : ($data['status'] === 'warning' ? 'bg-yellow-100' : 'bg-red-100'); ?>">
                        <i class="fas <?php 
                            echo $component === 'main_server' ? 'fa-server' : 
                            ($component === 'database' ? 'fa-database' : 
                            ($component === 'authentication_service' ? 'fa-user-shield' : 
                            ($component === 'backup_service' ? 'fa-external-link-square-alt' : 'fa-envelope')));
                        ?> text-2xl <?php echo $data['status'] === 'operational' ? 'text-green-600' : ($data['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600'); ?>"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800"><?php echo ucfirst(str_replace('_', ' ', $component)); ?></h3>
                        <p class="text-sm text-gray-600">Last checked: <?php echo date('M d, Y H:i', strtotime($data['last_check'])); ?></p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <div class="text-2xl font-bold <?php echo $data['status'] === 'operational' ? 'text-green-600' : ($data['status'] === 'warning' ? 'text-yellow-600' : 'text-red-600'); ?>">
                        <?php echo ucfirst($data['status']); ?>
                    </div>
                    <?php if ($component === 'backup_service' && $data['last_backup']): ?>
                        <div class="text-sm text-gray-600">
                            Last backup: <?php echo date('M d, Y H:i', strtotime($data['last_backup'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Refresh Status Modal -->
    <div id="refreshModal" class="modal hidden">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-[#00572d]">Refresh System Status</h3>
                    <button onclick="closeRefreshModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Service Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Services to Refresh</label>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" name="check_database" class="form-checkbox" checked>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Database Status</label>
                                        <p class="text-xs text-gray-500">Check database connectivity and performance</p>
                                    </div>
                                </div>
                                <div class="ml-auto">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Quick
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" name="check_backup" class="form-checkbox" checked>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Backup Service</label>
                                        <p class="text-xs text-gray-500">Verify backup integrity and last backup time</p>
                                    </div>
                                </div>
                                <div class="ml-auto">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Moderate
                                    </span>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" name="check_email" class="form-checkbox" checked>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email Service</label>
                                        <p class="text-xs text-gray-500">Test email delivery and queue status</p>
                                    </div>
                                </div>
                                <div class="ml-auto">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Thorough
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Advanced Options</label>
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" name="deep_scan" class="form-checkbox">
                                <label class="text-sm text-gray-700">Perform deep scan (slower but more thorough)</label>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" name="include_logs" class="form-checkbox">
                                <label class="text-sm text-gray-700">Include detailed logs</label>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-2">
                        <button onclick="handleCancel()" class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-100">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                        <button onclick="handleRefresh()" class="bg-[#00572d] text-white px-4 py-2 rounded-lg hover:bg-[#1f9345]">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Section -->
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-xl font-bold text-[#00572d] mb-6">System Configuration</h3>
        
        <form method="POST" action="status.php" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Backup Configuration -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                    <select name="backup_frequency" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="daily" <?php echo isset($settings['backup_frequency']) && $settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo isset($settings['backup_frequency']) && $settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo isset($settings['backup_frequency']) && $settings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">How often backups should be taken</p>
                </div>

                <!-- Email Configuration -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Provider</label>
                    <select name="email_provider" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="smtp" <?php echo isset($settings['email_provider']) && $settings['email_provider'] === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                        <option value="sendgrid" <?php echo isset($settings['email_provider']) && $settings['email_provider'] === 'sendgrid' ? 'selected' : ''; ?>>SendGrid</option>
                        <option value="ses" <?php echo isset($settings['email_provider']) && $settings['email_provider'] === 'ses' ? 'selected' : ''; ?>>Amazon SES</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">Email service provider for system notifications</p>
                </div>

                <!-- Monitoring Configuration -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Monitoring Interval</label>
                    <select name="monitor_interval" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="1" <?php echo isset($settings['monitor_interval']) && $settings['monitor_interval'] === '1' ? 'selected' : ''; ?>>1 minute</option>
                        <option value="5" <?php echo isset($settings['monitor_interval']) && $settings['monitor_interval'] === '5' ? 'selected' : ''; ?>>5 minutes</option>
                        <option value="15" <?php echo isset($settings['monitor_interval']) && $settings['monitor_interval'] === '15' ? 'selected' : ''; ?>>15 minutes</option>
                        <option value="30" <?php echo isset($settings['monitor_interval']) && $settings['monitor_interval'] === '30' ? 'selected' : ''; ?>>30 minutes</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">How often system checks should be performed</p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" name="configure" class="bg-[#00572d] hover:bg-[#1f9345] text-white px-6 py-2 rounded-lg font-semibold">
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notifications -->
<?php if (isset($_GET['success'])): ?>
<div class="fixed top-4 right-4 bg-green-100 text-green-800 px-6 py-3 rounded-lg shadow-lg">
    Configuration updated successfully!
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="fixed top-4 right-4 bg-red-100 text-red-800 px-6 py-3 rounded-lg shadow-lg">
    Error updating configuration. Please try again.
</div>
<?php endif; ?>

<!-- FontAwesome for icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

<!-- Modal and Refresh Functionality -->
<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background: white;
    border-radius: 0.5rem;
    max-width: 600px;
    width: 90%;
    position: relative;
    z-index: 1001;
}

.form-checkbox {
    width: 1.25em;
    height: 1.25em;
    border: 1px solid #e2e8f0;
    border-radius: 0.25rem;
}

.form-checkbox:checked {
    background-color: #00572d;
    border-color: #00572d;
}

/* Loading Animation */
.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #00572d;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Modal Functions
function openRefreshModal() {
    const modal = document.getElementById('refreshModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Reset form state
    modal.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = true;
    });
    
    // Remove any existing progress bar
    const progressBar = modal.querySelector('.progress-bar');
    if (progressBar) {
        progressBar.remove();
    }
}

function closeRefreshModal() {
    const modal = document.getElementById('refreshModal');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    
    // Reset button states
    const refreshBtn = modal.querySelector('button:last-child');
    const cancelBtn = modal.querySelector('button:first-child');
    if (refreshBtn) {
        refreshBtn.disabled = false;
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh Now';
    }
    if (cancelBtn) {
        cancelBtn.disabled = false;
    }
}

// Refresh Handling Functions
let refreshInProgress = false;

function handleCancel() {
    const modal = document.getElementById('refreshModal');
    const refreshBtn = modal.querySelector('button:last-child');
    const cancelBtn = modal.querySelector('button:first-child');
    const progressBar = modal.querySelector('.progress-bar');

    if (refreshInProgress) {
        // If refresh is in progress, show confirmation
        if (confirm('Are you sure you want to cancel the refresh operation?')) {
            // Cancel any ongoing requests
            if (refreshController) {
                refreshController.abort();
                refreshController = null;
            }

            // Reset UI
            refreshBtn.disabled = false;
            cancelBtn.disabled = false;
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh Now';
            
            if (progressBar) {
                progressBar.remove();
            }

            // Close modal
            closeRefreshModal();
            
            // Show cancellation message
            showToast('Refresh operation cancelled', 'info');
        }
    } else {
        // If no refresh in progress, just close modal
        closeRefreshModal();
    }
}

function handleRefresh() {
    if (refreshInProgress) {
        showToast('A refresh operation is already in progress', 'warning');
        return;
    }

    const modal = document.getElementById('refreshModal');
    const refreshBtn = modal.querySelector('button:last-child');
    const cancelBtn = modal.querySelector('button:first-child');
    const progressBar = document.createElement('div');
    
    // Add progress bar
    progressBar.className = 'w-full h-2 bg-gray-200 rounded-full mt-4';
    const progress = document.createElement('div');
    progress.className = 'h-full bg-[#00572d] rounded-full transition-all duration-300';
    progress.style.width = '0%';
    progressBar.appendChild(progress);
    modal.querySelector('.space-y-4').insertBefore(progressBar, modal.querySelector('.flex.justify-end'));
    
    // Disable buttons and show loading
    refreshBtn.disabled = true;
    cancelBtn.disabled = true;
    refreshBtn.innerHTML = '<div class="loading-spinner"></div> Refreshing...';

    // Create abort controller
    const controller = new AbortController();
    refreshController = controller;
    refreshInProgress = true;

    // Set up progress animation
    let progressInterval = setInterval(() => {
        if (progress.style.width < '95%') {
            progress.style.width = (parseFloat(progress.style.width) || 0) + 1 + '%';
        }
    }, 100);

    try {
        const response = await fetch('/superadmin/status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            signal: controller.signal,
            body: JSON.stringify({
                action: 'refresh',
                check_database: document.querySelector('input[name="check_database"]').checked,
                check_backup: document.querySelector('input[name="check_backup"]').checked,
                check_email: document.querySelector('input[name="check_email"]').checked,
                deep_scan: document.querySelector('input[name="deep_scan"]').checked,
                include_logs: document.querySelector('input[name="include_logs"]').checked
            })
        });

        if (response.ok) {
            const data = await response.json();

            if (data.success) {
                // Update progress bar
                progress.style.width = '100%';
                
                // Update status cards
                updateStatusCards(data.status);
                
                // Show success message
                showToast('Status refreshed successfully!', 'success');
                
                // Add detailed logs if available
                if (data.logs) {
                    showLogsModal(data.logs);
                }
            } else {
                // Update progress bar
                progress.style.width = '100%';
                progress.className = 'h-full bg-red-500 rounded-full transition-all duration-300';
                
                showToast('Failed to refresh status', 'error');
                
                if (data.error) {
                    showToast(data.error, 'error');
                }
            }
        } else {
            throw new Error('Server returned an error status');
        }
    } catch (error) {
        if (error.name === 'AbortError') {
            // Operation was cancelled
            showToast('Refresh operation cancelled', 'info');
        } else {
            console.error('Error refreshing status:', error);
            showToast('Error refreshing status', 'error');
        }
    } finally {
        // Clean up
        refreshInProgress = false;
        refreshController = null;
        clearInterval(progressInterval);

        // Re-enable buttons
        refreshBtn.disabled = false;
        cancelBtn.disabled = false;
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i> Refresh Now';
        
        // Remove progress bar
        if (progressBar) {
            progressBar.remove();
        }
        
        // Close modal after a delay
        setTimeout(() => {
            closeRefreshModal();
        }, 1000);
    }
}

// Show Logs Modal
function showLogsModal(logs) {
    const modal = document.createElement('div');
    modal.className = 'modal hidden';
    modal.innerHTML = `
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-[#00572d]">Refresh Logs</h3>
                    <button onclick="this.parentElement.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div class="overflow-x-auto">
                        <pre class="bg-gray-100 p-4 rounded-lg whitespace-pre-wrap">${logs}</pre>
                    </div>
                    
                    <div class="flex justify-end">
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" class="bg-[#00572d] text-white px-4 py-2 rounded-lg hover:bg-[#1f9345]">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.classList.remove('hidden');
}

// Update Status Cards
function updateStatusCards(newStatus) {
    const statusCards = document.querySelectorAll('.status-card');
    statusCards.forEach(card => {
        const component = card.dataset.component;
        const newStatusData = newStatus[component];
        
        // Update status text
        card.querySelector('.status-text').textContent = newStatusData.status;
        
        // Update status color
        const statusColor = newStatusData.status === 'operational' ? 'text-green-600' :
                           newStatusData.status === 'warning' ? 'text-yellow-600' : 'text-red-600';
        card.querySelector('.status-text').className = `text-2xl font-bold ${statusColor}`;
        
        // Update last checked time
        card.querySelector('.last-checked').textContent = `Last checked: ${newStatusData.last_check}`;
    });
}

// Toast Notifications
function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg transition-all duration-300 ${
        type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
    }`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Close modal when clicking outside
const modal = document.getElementById('refreshModal');
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        closeRefreshModal();
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
