<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has superadmin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: dashboard.php');
    exit();
}

require_once '../config/config.php';
require_once '../config/database.php';

// Initialize variables
$success = $error = '';
$db = getDB();

// Initialize arrays with default empty values
$settings = [];
$securityLogs = [];
$blockedIPs = [];
$loginAttempts = [];

// Function to check if an IP is likely a VPN/Proxy
function isVPN($ip) {
    // Convert IP to long integer
    $ipLong = ip2long($ip);
    
    // List of common VPN/Proxy IP ranges
    $vpnRanges = [
        ['91.189.89.0', '91.189.89.255'],    // Tor exit nodes
        ['103.28.248.0', '103.28.251.255'],  // Known VPN ranges
        ['103.28.252.0', '103.28.255.255'],
        ['103.28.256.0', '103.28.259.255'],
        ['103.28.260.0', '103.28.263.255'],
        ['103.28.264.0', '103.28.267.255'],
        ['103.28.268.0', '103.28.271.255'],
        ['103.28.272.0', '103.28.275.255'],
        ['103.28.276.0', '103.28.279.255'],
        ['103.28.280.0', '103.28.283.255']
    ];

    // Check if IP is in any VPN range
    foreach ($vpnRanges as $range) {
        $start = ip2long($range[0]);
        $end = ip2long($range[1]);
        
        if ($ipLong >= $start && $ipLong <= $end) {
            return true;
        }
    }
    
    // Additional checks for common VPN/Proxy characteristics
    $ipParts = explode('.', $ip);
    if (count($ipParts) !== 4) {
        return false;
    }
    
    // Check for common VPN/Proxy patterns
    if (in_array($ipParts[0], ['10', '172', '192'])) {
        return true;
    }
    
    return false;
}

// Function to check if an IP is from Ethiopia
function isEthiopianIP($ip) {
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode");
    $data = json_decode($response, true);
    return ($data && isset($data['countryCode']) && $data['countryCode'] === 'ET');
}

// Add VPN blocking settings to settings table if not exists
try {
    $vpnBlockingEnabled = $db->fetchOne("SELECT value FROM settings WHERE name = 'vpn_blocking_enabled'");
    if ($vpnBlockingEnabled === false) {
        $db->executeQuery("INSERT INTO settings (name, value, description) VALUES ('vpn_blocking_enabled', '0', 'Enable/Disable VPN Blocking')");
    }
} catch (Exception $e) {
    error_log("Error setting up VPN blocking: " . $e->getMessage());
}

// Add Ethiopian IP blocking status to settings table if not exists
try {
    $ethiopiaOnlyEnabled = $db->fetchOne("SELECT value FROM settings WHERE name = 'ethiopia_only_access'");
    if ($ethiopiaOnlyEnabled === false) {
        $db->executeQuery("INSERT INTO settings (name, value, description) VALUES ('ethiopia_only_access', '0', 'Enable/Disable Ethiopia Only Access')");
    }
} catch (Exception $e) {
    error_log("Error setting up Ethiopia Only Access: " . $e->getMessage());
}

// Fetch VPN blocking setting
try {
    $vpnBlockingEnabled = $db->fetchOne("SELECT value FROM settings WHERE name = 'vpn_blocking_enabled'");
    $vpnBlockingEnabled = $vpnBlockingEnabled === '1';
} catch (Exception $e) {
    error_log("Error fetching VPN blocking setting: " . $e->getMessage());
    $vpnBlockingEnabled = false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'update_security_settings':
                $db->beginTransaction();

                // Update password policy
                $db->executeQuery("
                    INSERT INTO settings (name, value, description) 
                    VALUES 
                        ('min_password_length', ?, 'Minimum password length'),
                        ('require_special_chars', ?, 'Require special characters in password'),
                        ('require_numbers', ?, 'Require numbers in password'),
                        ('require_uppercase', ?, 'Require uppercase letters in password'),
                        ('password_expiry_days', ?, 'Password expiry in days'),
                        ('max_login_attempts', ?, 'Maximum login attempts before lockout'),
                        ('lockout_duration_minutes', ?, 'Account lockout duration in minutes'),
                        ('session_timeout_minutes', ?, 'Session timeout in minutes'),
                        ('require_2fa', ?, 'Require two-factor authentication'),
                        ('ip_whitelist', ?, 'Whitelisted IP addresses')
                    ON DUPLICATE KEY UPDATE value = VALUES(value)
                ", [
                    $_POST['min_password_length'],
                    isset($_POST['require_special_chars']) ? '1' : '0',
                    isset($_POST['require_numbers']) ? '1' : '0',
                    isset($_POST['require_uppercase']) ? '1' : '0',
                    $_POST['password_expiry_days'],
                    $_POST['max_login_attempts'],
                    $_POST['lockout_duration_minutes'],
                    $_POST['session_timeout_minutes'],
                    isset($_POST['require_2fa']) ? '1' : '0',
                    $_POST['ip_whitelist']
                ]);

                // Log the security settings update
                $db->executeQuery("
                    INSERT INTO security_logs (user_id, action, description, ip_address)
                    VALUES (?, 'security_settings_update', 'Security settings updated', ?)
                ", [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

                $db->commit();
                $success = 'Security settings updated successfully!';
                break;

            case 'block_ip':
                $db->executeQuery("
                    INSERT INTO blocked_ips (ip_address, reason, blocked_by)
                    VALUES (?, ?, ?)
                ", [
                    $_POST['ip_address'],
                    $_POST['reason'],
                    $_SESSION['user_id']
                ]);
                $success = 'IP address blocked successfully!';
                break;

            case 'unblock_ip':
                $db->executeQuery("DELETE FROM blocked_ips WHERE id = ?", [$_POST['block_id']]);
                $success = 'IP address unblocked successfully!';
                break;

            case 'clear_logs':
                if ($_POST['log_type'] === 'security') {
                    $db->executeQuery("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$_POST['days_to_keep']]);
                } else {
                    $db->executeQuery("DELETE FROM user_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)", [$_POST['days_to_keep']]);
                }
                $success = 'Logs cleared successfully!';
                break;

            case 'toggle_vpn_blocking':
                $newStatus = $_POST['status'] === '1' ? '1' : '0';
                $db->executeQuery("
                    UPDATE settings 
                    SET value = ? 
                    WHERE name = 'block_vpn'
                ", [$newStatus]);
                
                $db->executeQuery("
                    INSERT INTO security_logs (user_id, action, description, ip_address)
                    VALUES (?, 'vpn_blocking_update', ?, ?)
                ", [
                    $_SESSION['user_id'],
                    'VPN blocking ' . ($newStatus === '1' ? 'enabled' : 'disabled'),
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $success = 'VPN blocking settings updated successfully!';
                break;

            case 'toggle_ethiopia_only':
                $newStatus = $_POST['status'] === '1' ? '1' : '0';
                $db->executeQuery("
                    UPDATE settings 
                    SET value = ? 
                    WHERE name = 'ethiopia_only_access'
                ", [$newStatus]);
                
                $db->executeQuery("
                    INSERT INTO security_logs (user_id, action, description, ip_address)
                    VALUES (?, 'ethiopia_only_update', ?, ?)
                ", [
                    $_SESSION['user_id'],
                    'Ethiopian IP restriction ' . ($newStatus === '1' ? 'enabled' : 'disabled'),
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $success = 'Ethiopian IP restriction settings updated successfully!';
                break;

            case 'scan_ip':
                $ipToScan = $_POST['ip_address'];
                if (filter_var($ipToScan, FILTER_VALIDATE_IP)) {
                    $isEthiopian = isEthiopianIP($ipToScan);
                    if (!$isEthiopian) {
                        // If it's not Ethiopian IP, block it
                        $db->executeQuery("
                            INSERT INTO blocked_ips (ip_address, reason, blocked_by)
                            VALUES (?, ?, ?)
                        ", [
                            $ipToScan,
                            'Non-Ethiopian IP address blocked automatically',
                            $_SESSION['user_id']
                        ]);
                        $success = "IP {$ipToScan} detected as non-Ethiopian IP and has been blocked.";
                    } else {
                        $success = "IP {$ipToScan} is an Ethiopian IP address.";
                    }
                } else {
                    $error = 'Invalid IP address format.';
                }
                break;
        }
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->rollback();
        }
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch current security settings
try {
    $settingsData = $db->fetchAll("SELECT name, value FROM settings WHERE name LIKE 'min_password_length%' OR name LIKE 'require_%' OR name LIKE 'password_%' OR name LIKE 'max_login_%' OR name LIKE 'lockout_%' OR name LIKE 'session_%' OR name LIKE 'ip_%'");
    foreach ($settingsData as $row) {
        $settings[$row['name']] = $row['value'];
    }
} catch (Exception $e) {
    $error = 'Error fetching settings: ' . $e->getMessage();
}

// Fetch recent security logs
try {
    $securityLogs = $db->fetchAll("
        SELECT sl.*, u.name as user_name
        FROM security_logs sl
        LEFT JOIN users u ON sl.user_id = u.id
        ORDER BY sl.created_at DESC
        LIMIT 100
    ");
} catch (Exception $e) {
    $error = 'Error fetching security logs: ' . $e->getMessage();
}

// Fetch blocked IPs
try {
    $blockedIPs = $db->fetchAll("
        SELECT bi.*, u.name as blocked_by_name
        FROM blocked_ips bi
        LEFT JOIN users u ON bi.blocked_by = u.id
        ORDER BY bi.created_at DESC
    ");
} catch (Exception $e) {
    $error = 'Error fetching blocked IPs: ' . $e->getMessage();
}

// Fetch recent login attempts
try {
    $loginAttempts = $db->fetchAll("
        SELECT la.*, u.name as user_name
        FROM login_attempts la
        LEFT JOIN users u ON la.user_id = u.id
        ORDER BY la.attempted_at DESC
        LIMIT 50
    ");
} catch (Exception $e) {
    $error = 'Error fetching login attempts: ' . $e->getMessage();
}

require_once 'include/header.php';
require_once 'include/sidebar.php';
?>

<!-- Custom CSS for Security Page -->
<style>
.card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    background: white;
    border-bottom: 1px solid #eee;
    padding: 15px 20px;
}

.card-title {
    color: #00572d;
    margin: 0;
    font-weight: 600;
}

.small-box {
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}

.small-box .inner {
    padding: 20px;
}

.small-box .inner h3 {
    font-size: 2.2rem;
    font-weight: 700;
    margin: 0;
    white-space: nowrap;
}

.small-box .icon {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 70px;
    opacity: 0.2;
}

.nav-tabs .nav-link {
    color: #666;
    border: none;
    padding: 10px 20px;
    border-radius: 4px 4px 0 0;
}

.nav-tabs .nav-link:hover {
    border: none;
    color: #00572d;
}

.nav-tabs .nav-link.active {
    color: #00572d;
    background: white;
    border: none;
    border-bottom: 3px solid #00572d;
}

.btn-primary {
    background: #00572d;
    border-color: #00572d;
}

.btn-primary:hover {
    background: #1f9345;
    border-color: #1f9345;
}

.badge-success {
    background: #1f9345;
}

.badge-danger {
    background: #dc3545;
}

.datatable th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
}

.form-control:focus {
    border-color: #1f9345;
    box-shadow: 0 0 0 0.2rem rgba(31, 147, 69, 0.25);
}
</style>

<!-- Main Content -->
<div class="content-wrapper">
    <div class="content-header">
    <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0" style="color: #00572d;">Security Management</h1>
            </div>
            </div>
            </div>
        </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $success; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
            <?php endif; ?>

            <!-- Security Dashboard -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo count($loginAttempts); ?></h3>
                            <p>Recent Login Attempts</p>
                            </div>
                        <div class="icon">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo count($blockedIPs); ?></h3>
                            <p>Blocked IP Addresses</p>
            </div>
                        <div class="icon">
                            <i class="fas fa-ban"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box" style="background: #1f9345; color: white;">
                        <div class="inner">
                            <h3><?php echo $settings['session_timeout_minutes'] ?? 30; ?></h3>
                            <p>Session Timeout (mins)</p>
            </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box" style="background: #00572d; color: white;">
                        <div class="inner">
                            <h3><?php echo $settings['max_login_attempts'] ?? 5; ?></h3>
                            <p>Max Login Attempts</p>
            </div>
                        <div class="icon">
                            <i class="fas fa-shield-alt"></i>
                    </div>
                </div>
               
        </div>

                </div>
            </div>
        </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#settings" role="tab">
                        <i class="fas fa-cogs"></i> Security Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#logs" role="tab">
                        <i class="fas fa-list"></i> Security Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#ip-blocking" role="tab">
                        <i class="fas fa-ban"></i> IP Blocking
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#login-attempts" role="tab">
                        <i class="fas fa-sign-in-alt"></i> Login Attempts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#vpn-protection" role="tab">
                        <i class="fas fa-shield-alt"></i> VPN Protection
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#ip-protection" role="tab">
                        <i class="fas fa-globe-africa"></i> IP Protection
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Security Settings Tab -->
                <div class="tab-pane fade show active" id="settings" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Security Settings</h3>
            </div>
            <div class="card-body">
                            <form action="" method="POST">
                                <input type="hidden" name="action" value="update_security_settings">
                                
                                <h5 class="mb-3" style="color: #00572d;">Password Policy</h5>
                <div class="row">
                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Minimum Password Length</label>
                                            <input type="number" class="form-control" name="min_password_length" 
                                                   value="<?php echo $settings['min_password_length'] ?? 8; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Password Expiry (days)</label>
                                            <input type="number" class="form-control" name="password_expiry_days"
                                                   value="<?php echo $settings['password_expiry_days'] ?? 90; ?>" required>
                            </div>
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="require_special_chars" id="require_special_chars"
                                                   <?php echo ($settings['require_special_chars'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_special_chars">Require Special Characters</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="require_numbers" id="require_numbers"
                                                   <?php echo ($settings['require_numbers'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_numbers">Require Numbers</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="require_uppercase" id="require_uppercase"
                                                   <?php echo ($settings['require_uppercase'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_uppercase">Require Uppercase Letters</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="require_2fa" id="require_2fa"
                                                   <?php echo ($settings['require_2fa'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="require_2fa">Require 2FA</label>
                                        </div>
                                    </div>
                                </div>

                                <h5 class="mb-3" style="color: #00572d;">Login Security</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Maximum Login Attempts</label>
                                            <input type="number" class="form-control" name="max_login_attempts"
                                                   value="<?php echo $settings['max_login_attempts'] ?? 5; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Lockout Duration (minutes)</label>
                                            <input type="number" class="form-control" name="lockout_duration_minutes"
                                                   value="<?php echo $settings['lockout_duration_minutes'] ?? 30; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" name="session_timeout_minutes"
                                                   value="<?php echo $settings['session_timeout_minutes'] ?? 30; ?>" required>
                            </div>
                        </div>
                    </div>

                                <h5 class="mb-3" style="color: #00572d;">IP Security</h5>
                                <div class="form-group">
                                    <label>IP Whitelist (comma-separated)</label>
                                    <textarea class="form-control" name="ip_whitelist" rows="3" 
                                              placeholder="Enter whitelisted IP addresses"><?php echo $settings['ip_whitelist'] ?? ''; ?></textarea>
                                    <small class="form-text text-muted">Leave empty to allow all IPs</small>
                                </div>

                                <div class="text-right mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Logs Tab -->
                <div class="tab-pane fade" id="logs" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Security Logs</h3>
                            <button class="btn btn-danger" data-toggle="modal" data-target="#clearLogsModal">
                                <i class="fas fa-trash"></i> Clear Old Logs
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($securityLogs as $log): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                    </div>
                </div>
            </div>
        </div>

                <!-- IP Blocking Tab -->
                <div class="tab-pane fade" id="ip-blocking" role="tabpanel">
        <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">IP Blocking</h3>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#blockIPModal">
                                <i class="fas fa-plus"></i> Block IP
                            </button>
            </div>
                        <div class="card-body">
                <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                        <thead>
                            <tr>
                                            <th>IP Address</th>
                                            <th>Reason</th>
                                            <th>Blocked By</th>
                                            <th>Blocked At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                        <?php foreach ($blockedIPs as $ip): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                                <td><?php echo htmlspecialchars($ip['reason']); ?></td>
                                                <td><?php echo htmlspecialchars($ip['blocked_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($ip['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="unblockIP(<?php echo $ip['id']; ?>)">
                                                        <i class="fas fa-unlock"></i> Unblock
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

                <!-- Login Attempts Tab -->
                <div class="tab-pane fade" id="login-attempts" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Login Attempts</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Username</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                            <th>User Agent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loginAttempts as $attempt): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($attempt['attempted_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($attempt['username']); ?></td>
                                                <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $attempt['success'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $attempt['success'] ? 'Success' : 'Failed'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($attempt['user_agent']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VPN Protection Tab -->
                <div class="tab-pane fade" id="vpn-protection" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">VPN Protection Settings</h3>
                            <div>
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_vpn_blocking">
                                    <input type="hidden" name="status" value="<?php echo $vpnBlockingEnabled === '1' ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo $vpnBlockingEnabled === '1' ? 'btn-danger' : 'btn-success'; ?>">
                                        <i class="fas <?php echo $vpnBlockingEnabled === '1' ? 'fa-shield-alt' : 'fa-shield-alt'; ?>"></i>
                                        <?php echo $vpnBlockingEnabled === '1' ? 'Disable VPN Protection' : 'Enable VPN Protection'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card" style="background: #f8f9fa;">
                                        <div class="card-body">
                                            <h5 class="card-title" style="color: #00572d;">
                                                <i class="fas fa-info-circle"></i> VPN Protection Status
                                            </h5>
                                            <p>Current Status: 
                                                <span class="badge badge-<?php echo $vpnBlockingEnabled === '1' ? 'success' : 'danger'; ?>">
                                                    <?php echo $vpnBlockingEnabled === '1' ? 'Enabled' : 'Disabled'; ?>
                                                </span>
                                            </p>
                                            <p class="mb-0">When enabled, the system will automatically detect and block VPN/Proxy connections.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card" style="background: #f8f9fa;">
                                        <div class="card-body">
                                            <h5 class="card-title" style="color: #00572d;">
                                                <i class="fas fa-search"></i> IP Scanner
                                            </h5>
                                            <form action="" method="POST">
                                                <input type="hidden" name="action" value="scan_ip">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="ip_address" 
                                                           placeholder="Enter IP address to scan"
                                                           pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                                                           required>
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-search"></i> Scan IP
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Type</th>
                                            <th>Blocked At</th>
                                            <th>Blocked By</th>
                                            <th>Reason</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($blockedIPs as $ip): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                                <td>
                                                    <?php 
                                                        $isVpn = isVPN($ip['ip_address']);
                                                        echo $isVpn ? 
                                                            '<span class="badge badge-warning">VPN/Proxy</span>' : 
                                                            '<span class="badge badge-info">Manual Block</span>';
                                                    ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($ip['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($ip['blocked_by_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ip['reason']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="unblockIP(<?php echo $ip['id']; ?>)">
                                                        <i class="fas fa-unlock"></i> Unblock
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IP Protection Tab -->
                <div class="tab-pane fade" id="ip-protection" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Ethiopian IP Protection</h3>
                            <div>
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_ethiopia_only">
                                    <input type="hidden" name="status" value="<?php echo $ethiopiaOnlyEnabled === '1' ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo $ethiopiaOnlyEnabled === '1' ? 'btn-danger' : 'btn-success'; ?>">
                                        <i class="fas fa-globe-africa"></i>
                                        <?php echo $ethiopiaOnlyEnabled === '1' ? 'Disable Ethiopian IP Only' : 'Enable Ethiopian IP Only'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert <?php echo $ethiopiaOnlyEnabled === '1' ? 'alert-success' : 'alert-warning'; ?> mb-4">
                                <i class="fas <?php echo $ethiopiaOnlyEnabled === '1' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                                <?php if ($ethiopiaOnlyEnabled === '1'): ?>
                                    Only IP addresses from Ethiopia are allowed to access the system. All other IPs will be automatically blocked.
                                <?php else: ?>
                                    IP restriction is currently disabled. The system can be accessed from any location.
                                <?php endif; ?>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Location</th>
                                            <th>Blocked At</th>
                                            <th>Blocked By</th>
                                            <th>Reason</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($blockedIPs as $ip): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                                <td>
                                                    <?php 
                                                        $isEthiopian = isEthiopianIP($ip['ip_address']);
                                                        echo $isEthiopian ? 
                                                            '<span class="badge badge-success">Ethiopian IP</span>' : 
                                                            '<span class="badge badge-danger">Foreign IP</span>';
                                                    ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($ip['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($ip['blocked_by_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ip['reason']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="unblockIP(<?php echo $ip['id']; ?>)">
                                                        <i class="fas fa-unlock"></i> Unblock
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Block IP Modal -->
<div class="modal fade" id="blockIPModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Block IP Address</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="block_ip">
                    <div class="form-group">
                        <label>IP Address</label>
                        <input type="text" class="form-control" name="ip_address" required 
                               pattern="^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$"
                               placeholder="Enter IP address (e.g., 192.168.1.1)">
                    </div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea class="form-control" name="reason" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Block IP</button>
                </div>
            </form>
            </div>
        </div>
    </div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">Clear Old Logs</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="clear_logs">
                    <div class="form-group">
                        <label>Log Type</label>
                        <select class="form-control" name="log_type" required>
                            <option value="security">Security Logs</option>
                            <option value="activity">Activity Logs</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Keep Logs From Last (days)</label>
                        <input type="number" class="form-control" name="days_to_keep" value="30" required>
                        <small class="form-text text-muted">Logs older than this many days will be deleted</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Clear Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('.datatable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "buttons": ["copy", "csv", "excel", "pdf", "print"]
    });

    // Initialize Tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Show toast notifications
    function showToast(message, type = 'success') {
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: 'right',
            backgroundColor: type === 'success' ? "#1f9345" : "#dc3545",
            stopOnFocus: true
        }).showToast();
    }

    <?php if ($success): ?>
        showToast("<?php echo addslashes($success); ?>", 'success');
    <?php endif; ?>
    
    <?php if ($error): ?>
        showToast("<?php echo addslashes($error); ?>", 'error');
    <?php endif; ?>
});

function unblockIP(blockId) {
    if (confirm('Are you sure you want to unblock this IP address?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="unblock_ip">
            <input type="hidden" name="block_id" value="${blockId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Add VPN-specific JavaScript functions
function confirmVPNAction(action) {
    return confirm('Are you sure you want to ' + action + ' VPN protection?');
}
</script>

<?php require_once 'include/footer.php'; ?>
