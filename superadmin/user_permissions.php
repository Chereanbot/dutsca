<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

// Get database connection
$db = getDB();
$error = '';
$success = '';

// Define available permissions with more details
$availablePermissions = [
    'manage_users' => [
        'name' => 'Manage Users',
        'description' => 'Can create, edit, and delete user accounts',
        'icon' => 'fa-users',
        'category' => 'User Management'
    ],
    'manage_departments' => [
        'name' => 'Manage Departments',
        'description' => 'Can create, edit, and delete departments',
        'icon' => 'fa-building',
        'category' => 'Organization'
    ],
    'manage_backups' => [
        'name' => 'Manage Backups',
        'description' => 'Can create and restore system backups',
        'icon' => 'fa-database',
        'category' => 'System'
    ],
    'view_reports' => [
        'name' => 'View Reports',
        'description' => 'Can access and download system reports',
        'icon' => 'fa-chart-bar',
        'category' => 'Reports'
    ],
    'manage_settings' => [
        'name' => 'Manage Settings',
        'description' => 'Can modify system configuration and settings',
        'icon' => 'fa-cogs',
        'category' => 'System'
    ],
    'manage_finances' => [
        'name' => 'Manage Finances',
        'description' => 'Can handle financial transactions and records',
        'icon' => 'fa-money-bill',
        'category' => 'Finance'
    ],
    'manage_documents' => [
        'name' => 'Manage Documents',
        'description' => 'Can upload, edit, and delete documents',
        'icon' => 'fa-file-alt',
        'category' => 'Documents'
    ],
    'manage_events' => [
        'name' => 'Manage Events',
        'description' => 'Can create and manage system events',
        'icon' => 'fa-calendar',
        'category' => 'Events'
    ]
];

// Group permissions by category
$permissionsByCategory = [];
foreach ($availablePermissions as $key => $permission) {
    $permissionsByCategory[$permission['category']][$key] = $permission;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'grant_permission':
                if (empty($_POST['user_id']) || empty($_POST['permission_name'])) {
                    throw new Exception('User and permission are required');
                }
                
                // Check if permission already exists
                $stmt = $db->executeQuery(
                    "SELECT id FROM user_permissions WHERE user_id = ? AND permission_name = ?",
                    [$_POST['user_id'], $_POST['permission_name']]
                );
                if ($stmt->fetch()) {
                    throw new Exception('Permission already granted to this user');
                }
                
                // Grant permission
                $db->executeQuery(
                    "INSERT INTO user_permissions (user_id, permission_name, granted_by, expires_at) VALUES (?, ?, ?, ?)",
                    [
                        $_POST['user_id'],
                        $_POST['permission_name'],
                        $_SESSION['user_id'],
                        !empty($_POST['expires_at']) ? $_POST['expires_at'] : null
                    ]
                );
                $_SESSION['success_message'] = 'Permission granted successfully';
                break;
                
            case 'revoke_permission':
                if (empty($_POST['permission_id'])) {
                    throw new Exception('Permission ID is required');
                }
                
                $db->executeQuery(
                    "DELETE FROM user_permissions WHERE id = ? AND EXISTS (SELECT 1 FROM users WHERE id = user_id AND role != 'superadmin')",
                    [$_POST['permission_id']]
                );
                $_SESSION['success_message'] = 'Permission revoked successfully';
                break;
                
            case 'update_permission':
                if (empty($_POST['permission_id'])) {
                    throw new Exception('Permission ID is required');
                }
                
                $db->executeQuery(
                    "UPDATE user_permissions SET expires_at = ? WHERE id = ?",
                    [
                        !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
                        $_POST['permission_id']
                    ]
                );
                $_SESSION['success_message'] = 'Permission updated successfully';
                break;
        }
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch users and their permissions
try {
    // Get all users except superadmins
    $users = $db->executeQuery("
        SELECT id, username, name, role, status 
        FROM users 
        WHERE role != 'superadmin' 
        ORDER BY name
    ")->fetchAll();
    
    // Get all permissions with user and granter details
    $permissions = $db->executeQuery("
        SELECT 
            up.id,
            up.user_id,
            up.permission_name,
            up.expires_at,
            up.granted_at,
            u.name as user_name,
            u.username,
            u.role as user_role,
            g.name as granted_by_name
        FROM user_permissions up
        JOIN users u ON up.user_id = u.id
        LEFT JOIN users g ON up.granted_by = g.id
        ORDER BY u.name, up.granted_at DESC
    ")->fetchAll();
    
} catch (Exception $e) {
    $error = 'Error loading permissions: ' . $e->getMessage();
}

// Get flash messages
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/include/sidebar.php';
?>

<style>
.main-content {
    margin-left: 256px;
    padding: 2rem;
    background: #f8f9fa;
    min-height: 100vh;
}

.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1.5rem;
}

.card-header {
    background: #fff;
    border-bottom: 2px solid #e9ecef;
    padding: 1.5rem;
}

.permission-badge {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    background: #e8f5e9;
    color: #1f9345;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.25rem;
}

.permission-badge .remove-btn {
    color: #dc3545;
    cursor: pointer;
    padding: 0.25rem;
    margin-left: 0.25rem;
    border-radius: 0.25rem;
    transition: all 0.2s;
}

.permission-badge .remove-btn:hover {
    background: rgba(220, 53, 69, 0.1);
}

.btn-primary {
    background: #00572d;
    border: none;
}

.btn-primary:hover {
    background: #1f9345;
}

.user-row {
    transition: all 0.2s;
}

.user-row:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 0.35rem 0.65rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-active {
    background: #e8f5e9;
    color: #1f9345;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-inactive {
    background: #f8d7da;
    color: #dc3545;
}

.expires-soon {
    color: #856404;
    background: #fff3cd;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.expired {
    color: #dc3545;
    background: #f8d7da;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.permission-category {
    background: #fff;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.category-title {
    color: #00572d;
    font-weight: 600;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}

.permission-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 0.5rem;
    padding: 1.25rem;
    height: 100%;
    transition: all 0.3s ease;
    cursor: pointer;
}

.permission-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    border-color: #1f9345;
}

.permission-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.permission-card-header i {
    font-size: 1.25rem;
    color: #00572d;
}

.permission-card-header h6 {
    margin: 0;
    color: #00572d;
    font-weight: 600;
}

.permission-description {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 1rem;
    min-height: 2.5rem;
}

.granted-user-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: #f8f9fa;
    margin-bottom: 0.5rem;
}

.granted-user-item img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.user-info {
    flex: 1;
}

.user-info .name {
    font-weight: 500;
    margin: 0;
}

.user-info .details {
    font-size: 0.875rem;
    color: #6c757d;
}

.permission-stats {
    display: flex;
    gap: 2rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
    margin: 1rem 0;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #00572d;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}
</style>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: #00572d;">User Permissions</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">User Permissions</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#grantPermissionModal">
            <i class="fas fa-plus me-2"></i>Grant Permission
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">Active Permissions</h5>
                </div>
                <div class="col-auto">
                    <input type="text" class="form-control" id="searchPermissions" 
                           placeholder="Search permissions..." onkeyup="filterPermissions()">
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="permissionsTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Permission</th>
                            <th>Granted By</th>
                            <th>Granted At</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permissions as $perm): ?>
                            <?php
                            $isExpired = !empty($perm['expires_at']) && strtotime($perm['expires_at']) < time();
                            $expiresSoon = !empty($perm['expires_at']) && 
                                         strtotime($perm['expires_at']) > time() && 
                                         strtotime($perm['expires_at']) < strtotime('+7 days');
                            ?>
                            <tr>
                                <td>
                                    <div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($perm['user_name']); ?></div>
                                        <small class="text-muted">@<?php echo htmlspecialchars($perm['username']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="permission-badge">
                                        <i class="fas fa-key"></i>
                                        <?php echo htmlspecialchars($availablePermissions[$perm['permission_name']]['name'] ?? $perm['permission_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($perm['granted_by_name']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($perm['granted_at'])); ?></td>
                                <td>
                                    <?php if ($perm['expires_at']): ?>
                                        <?php echo date('M d, Y', strtotime($perm['expires_at'])); ?>
                                        <?php if ($isExpired): ?>
                                            <span class="expired">Expired</span>
                                        <?php elseif ($expiresSoon): ?>
                                            <span class="expires-soon">Expires Soon</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Never
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editPermission(<?php echo htmlspecialchars(json_encode($perm)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="revokePermission(<?php echo $perm['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($permissions)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="text-muted">No permissions found</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add this new card for Available Permissions -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Available Permissions</h5>
        </div>
        <div class="card-body">
            <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                <div class="permission-category mb-4">
                    <h6 class="category-title mb-3">
                        <?php echo htmlspecialchars($category); ?>
                    </h6>
                    <div class="row g-3">
                        <?php foreach ($permissions as $key => $permission): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="permission-card" onclick="showPermissionDetails('<?php echo $key; ?>')">
                                    <div class="permission-card-header">
                                        <i class="fas <?php echo $permission['icon']; ?>"></i>
                                        <h6><?php echo htmlspecialchars($permission['name']); ?></h6>
                                    </div>
                                    <p class="permission-description">
                                        <?php echo htmlspecialchars($permission['description']); ?>
                                    </p>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="event.stopPropagation(); showGrantModal('<?php echo $key; ?>')">
                                        <i class="fas fa-plus"></i> Grant
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Grant Permission Modal -->
<div class="modal fade" id="grantPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Grant Permission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="grantPermissionForm" method="POST">
                    <input type="hidden" name="action" value="grant_permission">
                    
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?> 
                                    (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permission</label>
                        <select class="form-select" name="permission_name" required>
                            <option value="">Select Permission</option>
                            <?php foreach ($availablePermissions as $key => $permission): ?>
                                <option value="<?php echo $key; ?>"><?php echo $permission['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expires At (Optional)</label>
                        <input type="date" class="form-control" name="expires_at" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Grant Permission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Permission Modal -->
<div class="modal fade" id="editPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Permission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPermissionForm" method="POST">
                    <input type="hidden" name="action" value="update_permission">
                    <input type="hidden" name="permission_id" id="editPermissionId">
                    
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <input type="text" class="form-control" id="editUserName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permission</label>
                        <input type="text" class="form-control" id="editPermissionName" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expires At</label>
                        <input type="date" class="form-control" name="expires_at" id="editExpiresAt"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Permission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Permission Details Modal -->
<div class="modal fade" id="permissionDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Permission Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="permissionDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="granted-users mt-4">
                    <h6>Users with this Permission</h6>
                    <div id="grantedUsersList">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="showGrantModal(currentPermissionKey)">
                    Grant Permission
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPermissionKey = '';
const permissions = <?php echo json_encode($availablePermissions); ?>;

function showPermissionDetails(permissionKey) {
    currentPermissionKey = permissionKey;
    const permission = permissions[permissionKey];
    const modal = new bootstrap.Modal(document.getElementById('permissionDetailsModal'));
    
    // Populate permission details
    const content = document.getElementById('permissionDetailsContent');
    content.innerHTML = `
        <div class="text-center mb-4">
            <i class="fas ${permission.icon} fa-3x text-primary"></i>
            <h4 class="mt-3">${permission.name}</h4>
            <p class="text-muted">${permission.description}</p>
        </div>
        <div class="permission-stats">
            <div class="stat-item">
                <div class="stat-value">0</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">0</div>
                <div class="stat-label">Expired</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">0</div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
    `;
    
    // Populate granted users list
    const usersList = document.getElementById('grantedUsersList');
    usersList.innerHTML = ''; // Clear existing content
    
    // Show the modal
    modal.show();
}

function showGrantModal(permissionKey) {
    const permission = permissions[permissionKey];
    document.querySelector('#grantPermissionModal select[name="permission_name"]').value = permissionKey;
    new bootstrap.Modal(document.getElementById('grantPermissionModal')).show();
}

// Enhance the existing filterPermissions function
function filterPermissions() {
    const input = document.getElementById('searchPermissions');
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll('.permission-card');
    
    cards.forEach(card => {
        const title = card.querySelector('h6').textContent;
        const description = card.querySelector('.permission-description').textContent;
        const text = `${title} ${description}`.toLowerCase();
        
        if (text.includes(filter)) {
            card.closest('.col-md-6').style.display = '';
        } else {
            card.closest('.col-md-6').style.display = 'none';
        }
    });
}

// Add event listeners for the search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPermissions');
    if (searchInput) {
        searchInput.addEventListener('input', filterPermissions);
    }
});

function editPermission(permission) {
    document.getElementById('editPermissionId').value = permission.id;
    document.getElementById('editUserName').value = permission.user_name;
    document.getElementById('editPermissionName').value = 
        '<?php echo json_encode($availablePermissions); ?>'[permission.permission_name] || permission.permission_name;
    document.getElementById('editExpiresAt').value = permission.expires_at ? 
        permission.expires_at.split(' ')[0] : '';
    
    new bootstrap.Modal(document.getElementById('editPermissionModal')).show();
}

function revokePermission(permissionId) {
    if (confirm('Are you sure you want to revoke this permission?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="revoke_permission">
            <input type="hidden" name="permission_id" value="${permissionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once __DIR__ . '/include/footer.php'; ?> 