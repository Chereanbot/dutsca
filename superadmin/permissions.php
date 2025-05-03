<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once 'include/header.php';
require_once 'include/sidebar.php';

// Check if user is logged in and has superadmin role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
    header('Location: ../login.php');
    exit();
}

// Initialize variables
$success = $error = '';
$categories = $permissions = $users = [];
$pdo = getDatabaseConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'assign_user_permissions':
                // Begin transaction
                $pdo->beginTransaction();
                
                $userId = $_POST['user_id'];
                $selectedPermissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
                
                // First, revoke all existing permissions for this user
                $stmt = $pdo->prepare("UPDATE user_permissions SET status = 'revoked' WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Then, add new permissions
                foreach ($selectedPermissions as $permissionId) {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_permissions (user_id, permission_id, granted_by, status)
                        VALUES (?, ?, ?, 'active')
                        ON DUPLICATE KEY UPDATE status = 'active', granted_by = ?, granted_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$userId, $permissionId, $_SESSION['user']['id'], $_SESSION['user']['id']]);
                }
                
                $pdo->commit();
                $success = 'User permissions updated successfully!';
                break;

            case 'add_permission':
                $stmt = $pdo->prepare("
                    INSERT INTO permissions (name, display_name, description, category_id, icon, requires_approval)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['display_name'],
                    $_POST['description'],
                    $_POST['category_id'],
                    $_POST['icon'],
                    isset($_POST['requires_approval']) ? 1 : 0
                ]);
                $success = 'Permission added successfully!';
                break;

            case 'edit_permission':
                $stmt = $pdo->prepare("
                    UPDATE permissions 
                    SET name = ?, display_name = ?, description = ?, category_id = ?, 
                        icon = ?, requires_approval = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['display_name'],
                    $_POST['description'],
                    $_POST['category_id'],
                    $_POST['icon'],
                    isset($_POST['requires_approval']) ? 1 : 0,
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['permission_id']
                ]);
                $success = 'Permission updated successfully!';
                break;

            case 'delete_permission':
                $stmt = $pdo->prepare("DELETE FROM permissions WHERE id = ?");
                $stmt->execute([$_POST['permission_id']]);
                $success = 'Permission deleted successfully!';
                break;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Error: ' . $e->getMessage();
    }
}

// Fetch all categories
try {
    $categories = $pdo->query("
        SELECT * FROM permission_categories 
        ORDER BY display_order, name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching categories: ' . $e->getMessage();
}

// Fetch all permissions with their categories
try {
    $permissions = $pdo->query("
        SELECT p.*, pc.name as category_name 
        FROM permissions p
        LEFT JOIN permission_categories pc ON p.category_id = pc.id
        ORDER BY pc.display_order, p.display_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching permissions: ' . $e->getMessage();
}

// Fetch users with their permissions
try {
    $stmt = $pdo->query("
        SELECT 
            u.id, 
            u.username, 
            u.name, 
            u.role,
            u.status,
            GROUP_CONCAT(DISTINCT p.id) as permission_ids,
            GROUP_CONCAT(DISTINCT p.display_name) as permission_names
        FROM users u
        LEFT JOIN user_permissions up ON u.id = up.user_id AND up.status = 'active'
        LEFT JOIN permissions p ON up.permission_id = p.id
        WHERE u.role != 'superadmin'
        GROUP BY u.id
        ORDER BY u.name
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process permission arrays
    foreach ($users as &$user) {
        $user['permission_ids'] = $user['permission_ids'] ? explode(',', $user['permission_ids']) : [];
        $user['permission_names'] = $user['permission_names'] ? explode(',', $user['permission_names']) : [];
    }
} catch (PDOException $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
}
?>

<!-- Main Content -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Permissions Management</h1>
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

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#permissions" role="tab">
                        <i class="fas fa-key"></i> Permissions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#user-permissions" role="tab">
                        <i class="fas fa-users-cog"></i> User Permissions
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Permissions Tab -->
                <div class="tab-pane fade show active" id="permissions" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Manage Permissions</h3>
                            <button class="btn btn-primary float-right" data-toggle="modal" data-target="#addPermissionModal">
                                <i class="fas fa-plus"></i> Add Permission
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Display Name</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($permissions as $permission): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($permission['name']); ?></td>
                                                <td><?php echo htmlspecialchars($permission['display_name']); ?></td>
                                                <td><?php echo htmlspecialchars($permission['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($permission['description']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $permission['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $permission['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="editPermission(<?php echo htmlspecialchars(json_encode($permission)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deletePermission(<?php echo $permission['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
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

                <!-- User Permissions Tab -->
                <div class="tab-pane fade" id="user-permissions" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Manage User Permissions</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped datatable">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Current Permissions</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($user['permission_names'])): ?>
                                                        <?php foreach ($user['permission_names'] as $perm): ?>
                                                            <span class="badge badge-secondary mr-1">
                                                                <?php echo htmlspecialchars($perm); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No permissions assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary" onclick="editUserPermissions(<?php echo htmlspecialchars(json_encode([
                                                        'id' => $user['id'],
                                                        'name' => $user['name'],
                                                        'permissions' => $user['permission_ids']
                                                    ])); ?>)">
                                                        <i class="fas fa-edit"></i> Edit Permissions
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

<!-- Add Permission Modal -->
<div class="modal fade" id="addPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Permission</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_permission">
                    <div class="form-group">
                        <label>Name (system name)</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" class="form-control" name="display_name" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Icon (FontAwesome class)</label>
                        <input type="text" class="form-control" name="icon" placeholder="fa-key">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="requires_approval" id="requires_approval">
                        <label class="form-check-label" for="requires_approval">Requires Approval</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Permission Modal -->
<div class="modal fade" id="editPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Permission</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_permission">
                    <input type="hidden" name="permission_id" id="edit_permission_id">
                    <div class="form-group">
                        <label>Name (system name)</label>
                        <input type="text" class="form-control" name="name" id="edit_permission_name" required>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" class="form-control" name="display_name" id="edit_permission_display_name" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select class="form-control" name="category_id" id="edit_permission_category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" id="edit_permission_description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Icon (FontAwesome class)</label>
                        <input type="text" class="form-control" name="icon" id="edit_permission_icon">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="requires_approval" id="edit_permission_requires_approval">
                        <label class="form-check-label" for="edit_permission_requires_approval">Requires Approval</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="edit_permission_is_active">
                        <label class="form-check-label" for="edit_permission_is_active">Is Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Permission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Permissions Modal -->
<div class="modal fade" id="editUserPermissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User Permissions</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_user_permissions">
                    <input type="hidden" name="user_id" id="edit_user_permissions_id">
                    
                    <h6 class="mb-3">Editing permissions for: <span id="edit_user_permissions_name" class="font-weight-bold"></span></h6>
                    
                    <div class="row">
                        <?php foreach ($categories as $category): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $categoryPermissions = array_filter($permissions, function($p) use ($category) {
                                            return $p['category_id'] == $category['id'];
                                        });
                                        ?>
                                        <?php foreach ($categoryPermissions as $permission): ?>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" 
                                                       class="custom-control-input permission-checkbox" 
                                                       id="permission_<?php echo $permission['id']; ?>" 
                                                       name="permissions[]" 
                                                       value="<?php echo $permission['id']; ?>">
                                                <label class="custom-control-label" for="permission_<?php echo $permission['id']; ?>">
                                                    <?php echo htmlspecialchars($permission['display_name']); ?>
                                                    <?php if ($permission['requires_approval']): ?>
                                                        <span class="badge badge-warning">Requires Approval</span>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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
});

function editPermission(permission) {
    document.getElementById('edit_permission_id').value = permission.id;
    document.getElementById('edit_permission_name').value = permission.name;
    document.getElementById('edit_permission_display_name').value = permission.display_name;
    document.getElementById('edit_permission_category_id').value = permission.category_id;
    document.getElementById('edit_permission_description').value = permission.description;
    document.getElementById('edit_permission_icon').value = permission.icon;
    document.getElementById('edit_permission_requires_approval').checked = permission.requires_approval == 1;
    document.getElementById('edit_permission_is_active').checked = permission.is_active == 1;
    $('#editPermissionModal').modal('show');
}

function deletePermission(permissionId) {
    if (confirm('Are you sure you want to delete this permission? This will also remove it from all users.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_permission">
            <input type="hidden" name="permission_id" value="${permissionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function editUserPermissions(user) {
    document.getElementById('edit_user_permissions_id').value = user.id;
    document.getElementById('edit_user_permissions_name').textContent = user.name;
    
    // Reset all checkboxes
    document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
        checkbox.checked = user.permissions.includes(parseInt(checkbox.value));
    });
    
    $('#editUserPermissionsModal').modal('show');
}
</script>

<?php require_once 'include/footer.php'; ?>
</rewritten_file>