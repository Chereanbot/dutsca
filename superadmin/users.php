<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has superadmin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../login.php');
    exit();
}

// Add this at the top of the file, after session checks
define('INCLUDED_FROM_USERS', true);

require_once '../config/config.php';
require_once '../config/database.php';

try {
    $db = getDB();
    $success = $error = '';

    // Fetch all users with their departments, roles, and additional info
    $users = $db->fetchAll("
        SELECT 
            u.*,
            d.name as department_name,
            (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id) as permissions_count,
            (SELECT COUNT(*) FROM user_activity_logs WHERE user_id = u.id) as activity_count,
            COALESCE(
                (SELECT MAX(created_at) 
                FROM user_activity_logs 
                WHERE user_id = u.id AND action = 'login'), 
                u.last_login,
                '0000-00-00 00:00:00'
            ) as last_activity
        FROM users u
        LEFT JOIN departments d ON u.department = d.name
        ORDER BY u.created_at DESC
    ");

    // Calculate user statistics
    $userStats = [
        'total' => count($users),
        'active' => 0,
        'pending' => 0,
        'suspended' => 0,
        'recent' => 0 // Users active in last 24 hours
    ];

    $yesterday = strtotime('-24 hours');
    foreach ($users as $user) {
        // Handle status count
        $status = $user['status'] ?? 'inactive';
        $userStats[$status] = ($userStats[$status] ?? 0) + 1;

        // Handle last activity check
        $lastActivity = $user['last_activity'] ?? '0000-00-00 00:00:00';
        if ($lastActivity && $lastActivity !== '0000-00-00 00:00:00') {
            $lastActivityTime = strtotime($lastActivity);
            if ($lastActivityTime && $lastActivityTime > $yesterday) {
                $userStats['recent']++;
            }
        }
    }

    // Fetch all departments for the dropdown
    $departments = $db->fetchAll("SELECT name, description FROM departments ORDER BY name");

    // Define roles with their descriptions
    $roles = [
        'superadmin' => 'Full system access and control',
        'chairman' => 'Organization leadership and oversight',
        'manager' => 'Department management and reporting',
        'finance' => 'Financial operations and reporting',
        'teacher' => 'Educational content and student management',
        'servant' => 'Basic system access and tasks'
    ];

    // Fetch all permissions with categories
    $permissions = $db->fetchAll("
        SELECT 
            p.*,
            pc.name as category_name,
            pc.description as category_description,
            pc.icon as category_icon
        FROM permissions p
        LEFT JOIN permission_categories pc ON p.category_id = pc.id
        ORDER BY pc.display_order, p.display_name
    ");

} catch (Exception $e) {
    error_log("Database Error in users.php: " . $e->getMessage());
    $error = "We encountered a temporary issue. Please try again in a few moments.";
    $users = [];
    $departments = [];
    $permissions = [];
}
include 'include/header.php';
include 'include/sidebar.php';

?>

<!-- Custom CSS -->
<style>
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 1.5rem;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.stat-card {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.stat-card .icon {
    position: absolute;
    right: -10px;
    bottom: -10px;
    font-size: 5rem;
    opacity: 0.2;
    transform: rotate(-15deg);
}

.stat-card .stat-title {
    font-size: 1.1rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-card .stat-desc {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Color variables */
:root {
    --primary-color: #00572d;
    --secondary-color: #1f9345;
    --accent-color: #f3c300;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
}

/* Stat card variations */
.stat-card.total-users {
    --primary-color: #00572d;
    --secondary-color: #1f9345;
}

.stat-card.active-users {
    --primary-color: #1f9345;
    --secondary-color: #2d7b48;
}

.stat-card.pending-users {
    --primary-color: #f3c300;
    --secondary-color: #d4aa00;
}

.stat-card.recent-activity {
    --primary-color: #3498db;
    --secondary-color: #2980b9;
}

/* Enhanced table styles */
.table {
    margin-bottom: 0;
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,87,45,0.05);
}

/* Badge styles */
.badge-role {
    padding: 0.5em 1em;
    font-size: 0.85em;
    font-weight: 500;
    border-radius: 50rem;
}

/* Modal enhancements */
.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.modal-header {
    background: linear-gradient(135deg, #00572d 0%, #1f9345 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 1.5rem;
}

.modal-title {
    font-weight: 600;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid #eee;
    padding: 1rem 1.5rem;
}

/* Button enhancements */
.btn {
    border-radius: 50rem;
    padding: 0.5rem 1.25rem;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-icon {
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.btn-group .btn {
    border-radius: 0;
}

.btn-group > :first-child {
    border-top-left-radius: 50rem;
    border-bottom-left-radius: 50rem;
}

.btn-group > :last-child {
    border-top-right-radius: 50rem;
    border-bottom-right-radius: 50rem;
}
</style>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0" style="color: #00572d;">User Management</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <!-- User Statistics -->
            <div class="row">
                <div class="col-lg-3 col-sm-6">
                    <div class="card stat-card total-users">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-title">Total Users</div>
                        <div class="stat-value"><?php echo $userStats['total']; ?></div>
                        <div class="stat-desc">Registered accounts</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="card stat-card active-users">
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-title">Active Users</div>
                        <div class="stat-value"><?php echo $userStats['active']; ?></div>
                        <div class="stat-desc">Currently active accounts</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="card stat-card pending-users">
                        <div class="icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-title">Pending Approval</div>
                        <div class="stat-value"><?php echo $userStats['pending']; ?></div>
                        <div class="stat-desc">Awaiting activation</div>
                    </div>
                    </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="card stat-card recent-activity">
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                    </div>
                        <div class="stat-title">Recent Activity</div>
                        <div class="stat-value"><?php echo $userStats['recent']; ?></div>
                        <div class="stat-desc">Active in last 24 hours</div>
                    </div>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">User Management</h3>
                    <div class="btn-group">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">
                            <i class="fas fa-user-plus mr-2"></i>Add User
                        </button>
                        <button class="btn btn-success" onclick="exportUsers()">
                            <i class="fas fa-file-export mr-2"></i>Export
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                <i class="fas fa-cog mr-2"></i>Bulk Actions
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="#" onclick="bulkAssignPermissions()">
                                    <i class="fas fa-shield-alt fa-fw mr-2"></i>Assign Permissions
                                </a>
                                <a class="dropdown-item" href="#" onclick="bulkUpdateStatus('active')">
                                    <i class="fas fa-user-check fa-fw mr-2"></i>Activate Selected
                                </a>
                                <a class="dropdown-item" href="#" onclick="bulkUpdateStatus('suspended')">
                                    <i class="fas fa-user-slash fa-fw mr-2"></i>Suspend Selected
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger" href="#" onclick="bulkForcePasswordReset()">
                                    <i class="fas fa-key fa-fw mr-2"></i>Force Password Reset
                                </a>
                            </div>
                    </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped datatable">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="selectAll">
                                            <label class="custom-control-label" for="selectAll"></label>
                                        </div>
                                    </th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Permissions</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input user-select" 
                                                       id="user_<?php echo $user['id']; ?>"
                                                       value="<?php echo $user['id']; ?>">
                                                <label class="custom-control-label" for="user_<?php echo $user['id']; ?>"></label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $user['profile_image'] ?: '../assets/img/default-avatar.png'; ?>" 
                                                     class="user-avatar mr-2" alt="Profile">
                    <div>
                                                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-role role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge status-<?php echo $user['status']; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewPermissions(<?php echo $user['id']; ?>)">
                                                <?php echo $user['permissions_count']; ?> Permissions
                                            </button>
                                        </td>
                                        <td class="timestamp" data-timestamp="<?php echo htmlspecialchars($user['last_login']); ?>">
                                            <?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="resetPassword(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="viewUserLogs(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="manageUserSettings(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <?php if ($user['status'] !== 'suspended'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="suspendUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-user-slash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-success" onclick="activateUser(<?php echo $user['id']; ?>)">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                <?php endif; ?>
                    </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="users_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" required>
                    </div>
                    </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-control" name="username" required>
                    </div>
                    </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" class="form-control" name="password" required>
                    </div>
                    </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role</label>
                                <select class="form-control" name="role" required>
                                    <?php foreach ($roles as $role => $description): ?>
                                        <option value="<?php echo $role; ?>"><?php echo ucfirst($role); ?></option>
                                    <?php endforeach; ?>
                        </select>
                    </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Department</label>
                                <select class="form-control" name="department">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['name']; ?>"><?php echo $dept['name']; ?></option>
                                    <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" class="form-control" name="contact_number">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Profile Image</label>
                                <input type="file" class="form-control-file" name="profile_image" accept="image/*">
                            </div>
                    </div>
                    </div>
                    <div class="form-group">
                        <label>Initial Permissions</label>
                        <div class="permission-list">
                            <?php 
                            $currentCategory = '';
                            foreach ($permissions as $permission):
                                if ($currentCategory !== $permission['category_name']):
                                    $currentCategory = $permission['category_name'];
                            ?>
                                <div class="permission-category">
                                    <i class="fas fa-folder"></i> <?php echo $currentCategory; ?>
                                </div>
                            <?php endif; ?>
                                <div class="permission-item">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" 
                                               id="perm_<?php echo $permission['id']; ?>" 
                                               name="permissions[]" 
                                               value="<?php echo $permission['id']; ?>">
                                        <label class="custom-control-label" for="perm_<?php echo $permission['id']; ?>">
                                            <i class="fas <?php echo $permission['icon']; ?> permission-icon"></i>
                                            <?php echo $permission['display_name']; ?>
                                        </label>
                    </div>
                    </div>
                            <?php endforeach; ?>
                    </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <!-- Similar structure to Add User Modal, populated via JavaScript -->
</div>

<!-- View Permissions Modal -->
<div class="modal fade" id="viewPermissionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Permissions</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Populated via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Bulk Assign Permissions Modal -->
<div class="modal fade" id="bulkPermissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Permissions to Selected Users</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="permission-list">
                    <?php 
                    $currentCategory = '';
                    foreach ($permissions as $permission):
                        if ($currentCategory !== $permission['category_name']):
                            $currentCategory = $permission['category_name'];
                    ?>
                        <div class="permission-category">
                            <i class="fas fa-folder"></i> <?php echo $currentCategory; ?>
                        </div>
                    <?php endif; ?>
                        <div class="permission-item">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input bulk-permission" 
                                       id="bulk_perm_<?php echo $permission['id']; ?>" 
                                       value="<?php echo $permission['id']; ?>">
                                <label class="custom-control-label" for="bulk_perm_<?php echo $permission['id']; ?>">
                                    <i class="fas <?php echo $permission['icon']; ?> permission-icon"></i>
                                    <?php echo $permission['display_name']; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveBulkPermissions()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- User Logs Modal -->
<div class="modal fade" id="userLogsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Activity Logs</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody id="userLogsTableBody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Settings Modal -->
<div class="modal fade" id="userSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Security Settings</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="userSettingsForm">
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="twoFactorEnabled" name="two_factor_enabled">
                            <label class="custom-control-label" for="twoFactorEnabled">Enable Two-Factor Authentication</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="loginNotification" name="login_notification">
                            <label class="custom-control-label" for="loginNotification">Email Notification on Login</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Account Lockout Threshold (failed attempts)</label>
                        <input type="number" class="form-control" name="account_lockout_threshold" min="3" max="10">
                    </div>
                    <div class="form-group">
                        <label>Session Timeout (minutes)</label>
                        <input type="number" class="form-control" name="session_timeout" min="15" max="120">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveUserSettings()">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('.datatable').DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "buttons": ["copy", "csv", "excel", "pdf", "print"]
    });

    // Initialize Select2 for dropdowns
    $('select').select2({
        theme: 'bootstrap4'
    });

    // Initialize Tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Show toast notifications
    <?php if ($success): ?>
        showToast("<?php echo addslashes($success); ?>", 'success');
    <?php endif; ?>
    
    <?php if ($error): ?>
        showToast("<?php echo addslashes($error); ?>", 'error');
    <?php endif; ?>

    // Update timestamp displays
    $('.timestamp').each(function() {
        const timestamp = $(this).data('timestamp');
        $(this).text(formatDateTime(timestamp));
    });
});

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

function editUser(userId) {
    // Fetch user data and populate modal
    $.get('users_actions.php', { action: 'get_user', user_id: userId }, function(response) {
        if (response.success) {
            $('#editUserModal').html(response.html).modal('show');
        } else {
            showToast(response.error, 'error');
        }
    });
}

function viewPermissions(userId) {
    // Fetch and display user permissions
    $.get('users_actions.php', { action: 'get_permissions', user_id: userId }, function(response) {
        if (response.success) {
            $('#viewPermissionsModal .modal-body').html(response.html);
            $('#viewPermissionsModal').modal('show');
        } else {
            showToast(response.error, 'error');
        }
    });
}

function resetPassword(userId) {
    if (confirm('Are you sure you want to reset this user\'s password?')) {
        $.post('users_actions.php', { action: 'reset_password', user_id: userId }, function(response) {
            showToast(response.message, response.success ? 'success' : 'error');
        });
    }
}

function suspendUser(userId) {
    if (confirm('Are you sure you want to suspend this user?')) {
        $.post('users_actions.php', { action: 'suspend_user', user_id: userId }, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                location.reload();
            } else {
                showToast(response.error, 'error');
            }
        });
    }
}

function activateUser(userId) {
    if (confirm('Are you sure you want to activate this user?')) {
        $.post('users_actions.php', { action: 'activate_user', user_id: userId }, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                location.reload();
            } else {
                showToast(response.error, 'error');
            }
        });
    }
}

function exportUsers() {
    window.location.href = 'users_actions.php?action=export_users';
}

// Handle select all checkbox
$('#selectAll').change(function() {
    $('.user-select').prop('checked', $(this).prop('checked'));
});

function getSelectedUsers() {
    return $('.user-select:checked').map(function() {
        return $(this).val();
    }).get();
}

function bulkAssignPermissions() {
    const selectedUsers = getSelectedUsers();
    if (selectedUsers.length === 0) {
        showToast('Please select users first', 'error');
        return;
    }
    $('#bulkPermissionsModal').modal('show');
}

function saveBulkPermissions() {
    const selectedUsers = getSelectedUsers();
    const selectedPermissions = $('.bulk-permission:checked').map(function() {
        return $(this).val();
    }).get();

    if (selectedPermissions.length === 0) {
        showToast('Please select at least one permission', 'error');
        return;
    }

    $.post('users_additional_actions.php', {
        action: 'bulk_assign_permissions',
        user_ids: selectedUsers,
        permissions: selectedPermissions
    }, function(response) {
        $('#bulkPermissionsModal').modal('hide');
        showToast(response.message, response.success ? 'success' : 'error');
        if (response.success) {
            location.reload();
        }
    });
}

function bulkUpdateStatus(status) {
    const selectedUsers = getSelectedUsers();
    if (selectedUsers.length === 0) {
        showToast('Please select users first', 'error');
        return;
    }

    if (!confirm(`Are you sure you want to ${status} the selected users?`)) {
        return;
    }

    $.post('users_additional_actions.php', {
        action: 'bulk_status_update',
        user_ids: selectedUsers,
        status: status
    }, function(response) {
        showToast(response.message, response.success ? 'success' : 'error');
        if (response.success) {
            location.reload();
        }
    });
}

function bulkForcePasswordReset() {
    const selectedUsers = getSelectedUsers();
    if (selectedUsers.length === 0) {
        showToast('Please select users first', 'error');
        return;
    }

    if (!confirm('Are you sure you want to force password reset for the selected users?')) {
        return;
    }

    $.post('users_additional_actions.php', {
        action: 'force_password_reset',
        user_ids: selectedUsers
    }, function(response) {
        showToast(response.message, response.success ? 'success' : 'error');
    });
}

function viewUserLogs(userId) {
    $.post('users_additional_actions.php', {
        action: 'export_user_logs',
        user_id: userId
    }, function(response) {
        if (response.success) {
            let html = '';
            response.logs.forEach(log => {
                html += `
                    <tr>
                        <td>${formatDateTime(log.created_at)}</td>
                        <td>${log.action || 'N/A'}</td>
                        <td>${log.description || 'N/A'}</td>
                        <td>${log.ip_address || 'N/A'}</td>
                        <td>${log.status || 'N/A'}</td>
                    </tr>
                `;
            });
            $('#userLogsTableBody').html(html || '<tr><td colspan="5" class="text-center">No activity logs found</td></tr>');
            $('#userLogsModal').modal('show');
        } else {
            showToast(response.error, 'error');
        }
    });
}

function manageUserSettings(userId) {
    // Fetch current settings
    $.get('users_additional_actions.php', {
        action: 'get_user_settings',
        user_id: userId
    }, function(response) {
        if (response.success) {
            const settings = response.settings;
            $('#twoFactorEnabled').prop('checked', settings.two_factor_enabled == 1);
            $('#loginNotification').prop('checked', settings.login_notification == 1);
            $('input[name="account_lockout_threshold"]').val(settings.account_lockout_threshold);
            $('input[name="session_timeout"]').val(settings.session_timeout);
            
            $('#userSettingsModal').data('userId', userId).modal('show');
        } else {
            showToast(response.error, 'error');
        }
    });
}

function saveUserSettings() {
    const userId = $('#userSettingsModal').data('userId');
    const settings = {
        two_factor_enabled: $('#twoFactorEnabled').prop('checked') ? 1 : 0,
        login_notification: $('#loginNotification').prop('checked') ? 1 : 0,
        account_lockout_threshold: $('input[name="account_lockout_threshold"]').val(),
        session_timeout: $('input[name="session_timeout"]').val()
    };

    $.post('users_additional_actions.php', {
        action: 'update_user_settings',
        user_id: userId,
        settings: settings
    }, function(response) {
        $('#userSettingsModal').modal('hide');
        showToast(response.message, response.success ? 'success' : 'error');
    });
}

// Add this helper function at the top of your script section
function formatDateTime(timestamp) {
    if (!timestamp || timestamp === '0000-00-00 00:00:00' || timestamp === 'null') {
        return 'Never';
    }
    try {
        return new Date(timestamp).toLocaleString();
    } catch (e) {
        return 'Invalid Date';
    }
}
</script>

<?php require_once 'include/footer.php'; ?>

<?php require_once 'templates/user_modals.php'; ?>
