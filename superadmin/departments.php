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
define('INCLUDED_FROM_DEPARTMENTS', true);

// Initialize variables
$departments = [];
$users = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'total_members' => 0,
    'avg_members' => 0
];
$error = '';

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    
    $db = getDB();

    // Fetch all departments with their statistics
    $departments = $db->fetchAll("
        SELECT 
            d.*,
            u.name as head_name,
            u.email as head_email,
            (SELECT COUNT(*) FROM department_members WHERE department_id = d.id) as member_count,
            (SELECT COUNT(*) FROM users WHERE department = d.name AND status = 'active') as active_users
        FROM departments d
        LEFT JOIN users u ON d.head_id = u.id
        ORDER BY d.name ASC
    ");

    // Fetch all users for department head selection
    $users = $db->fetchAll("
        SELECT id, name, email, role 
        FROM users 
        WHERE status = 'active' 
        ORDER BY name ASC
    ");

    // Calculate department statistics
    if ($departments) {
        $stats['total'] = count($departments);
        foreach ($departments as $dept) {
            if ($dept['status'] === 'active') {
                $stats['active']++;
            }
            $stats['total_members'] += (int)$dept['member_count'];
        }
        $stats['avg_members'] = $stats['total'] > 0 ? round($stats['total_members'] / $stats['total'], 1) : 0;
    }

} catch (Exception $e) {
    error_log("Database Error in departments.php: " . $e->getMessage());
    $error = "We encountered a temporary issue. Please try again in a few moments.";
}

include 'include/header.php';
include 'include/sidebar.php';
?>

<!-- Custom CSS -->
<style>
.department-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.department-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0,0,0,0.15);
}

.department-card .card-header {
    background: linear-gradient(135deg, #00572d 0%, #1f9345 100%);
    color: white;
    border: none;
    padding: 1.25rem;
}

.department-card .card-body {
    padding: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
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

.member-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    margin-right: -10px;
    border: 2px solid white;
}

.member-count {
    background: #f8f9fa;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.department-actions .btn {
    padding: 0.5rem;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-left: 0.5rem;
}

.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 50rem;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-active {
    background-color: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.status-inactive {
    background-color: rgba(108, 117, 125, 0.1);
    color: #6c757d;
}
</style>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0" style="color: #00572d;">Department Management</h1>
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

            <!-- Department Statistics -->
            <div class="row">
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card" style="--primary-color: #00572d; --secondary-color: #1f9345;">
                        <div class="icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-title">Total Departments</div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-desc">Registered departments</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card" style="--primary-color: #1f9345; --secondary-color: #2d7b48;">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-title">Active Departments</div>
                        <div class="stat-value"><?php echo $stats['active']; ?></div>
                        <div class="stat-desc">Currently active</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card" style="--primary-color: #3498db; --secondary-color: #2980b9;">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-title">Total Members</div>
                        <div class="stat-value"><?php echo $stats['total_members']; ?></div>
                        <div class="stat-desc">Across all departments</div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="stat-card" style="--primary-color: #f3c300; --secondary-color: #d4aa00;">
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-title">Average Members</div>
                        <div class="stat-value"><?php echo $stats['avg_members']; ?></div>
                        <div class="stat-desc">Per department</div>
                    </div>
                </div>
            </div>

            <!-- Departments List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">Departments</h3>
                    <div class="btn-group">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addDepartmentModal">
                            <i class="fas fa-plus mr-2"></i>Add Department
                        </button>
                        <button class="btn btn-success" onclick="exportDepartments()">
                            <i class="fas fa-file-export mr-2"></i>Export
                        </button>
    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($departments as $dept): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="department-card card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($dept['name']); ?></h5>
                                        <span class="status-badge status-<?php echo $dept['status']; ?>">
                                            <?php echo ucfirst($dept['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($dept['description']); ?></p>
                                        <div class="department-info mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-user-tie mr-2"></i>
                                                <strong>Head:</strong>
                                                <span class="ml-2"><?php echo $dept['head_name'] ?: 'Not assigned'; ?></span>
                                            </div>
                                            <div class="d-flex align-items-center mb-2">
                                                <i class="fas fa-envelope mr-2"></i>
                                                <span><?php echo htmlspecialchars($dept['contact_email']); ?></span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-phone mr-2"></i>
                                                <span><?php echo htmlspecialchars($dept['contact_phone']); ?></span>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="member-info">
                                                <span class="member-count">
                                                    <i class="fas fa-users mr-1"></i>
                                                    <?php echo $dept['member_count']; ?> Members
                                                </span>
                                            </div>
                                            <div class="department-actions">
                                                <button class="btn btn-info" onclick="viewMembers(<?php echo $dept['id']; ?>)" title="View Members">
                                                    <i class="fas fa-users"></i>
                                                </button>
                                                <button class="btn btn-primary" onclick="editDepartment(<?php echo $dept['id']; ?>)" title="Edit Department">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($dept['status'] === 'active'): ?>
                                                    <button class="btn btn-danger" onclick="deactivateDepartment(<?php echo $dept['id']; ?>)" title="Deactivate Department">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-success" onclick="activateDepartment(<?php echo $dept['id']; ?>)" title="Activate Department">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #00572d; color: white;">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="addDepartmentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_department">
                    
                    <div class="form-group">
                        <label>Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required 
                               minlength="2" maxlength="100" placeholder="Enter department name">
                        <small class="form-text text-muted">Name must be between 2 and 100 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3" 
                                placeholder="Enter department description"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" class="form-control" name="contact_email" 
                               placeholder="department@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="text" class="form-control" name="contact_phone" 
                               placeholder="+251 " pattern="[\+]?[0-9]{10,13}">
                        <small class="form-text text-muted">Format: +251xxxxxxxxx</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" class="form-control" name="location" 
                               placeholder="Enter department location">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addDepartmentBtn" 
                            style="background: #00572d; border-color: #00572d;">
                        <i class="fas fa-plus mr-2"></i>Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <!-- Populated via JavaScript -->
</div>

<!-- View Members Modal -->
<div class="modal fade" id="viewMembersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Department Members</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Populated via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('select').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Initialize form submission
    $('#addDepartmentForm').on('submit', function(e) {
        e.preventDefault();
        
        // Disable submit button to prevent double submission
        const submitBtn = $('#addDepartmentBtn');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Adding...');
        
        const formData = new FormData(this);

        $.ajax({
            url: 'department_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    $('#addDepartmentModal').modal('hide');
                    // Reset form
                    $('#addDepartmentForm')[0].reset();
                    // Reload page after short delay
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast(response.error, 'error');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-plus mr-2"></i>Add Department');
                }
            },
            error: function(xhr, status, error) {
                showToast('An error occurred. Please try again.', 'error');
                submitBtn.prop('disabled', false).html('<i class="fas fa-plus mr-2"></i>Add Department');
                console.error('Error:', error);
            }
        });
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

function editDepartment(departmentId) {
    $.get('department_actions.php', {
        action: 'get_department',
        department_id: departmentId
    }, function(response) {
        if (response.success) {
            $('#editDepartmentModal').html(response.html).modal('show');
            // Reinitialize Select2 for the edit modal
            $('#editDepartmentModal select').select2({
                theme: 'bootstrap4',
                width: '100%'
            });
        } else {
            showToast(response.error, 'error');
        }
    });
}

function viewMembers(departmentId) {
    $.get('department_actions.php', {
        action: 'get_members',
        department_id: departmentId
    }, function(response) {
        if (response.success) {
            $('#viewMembersModal .modal-body').html(response.html);
            $('#viewMembersModal').modal('show');
        } else {
            showToast(response.error, 'error');
        }
    });
}

function deactivateDepartment(departmentId) {
    if (confirm('Are you sure you want to deactivate this department?')) {
        $.post('department_actions.php', {
            action: 'update_status',
            department_id: departmentId,
            status: 'inactive'
        }, function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                location.reload();
            } else {
                showToast(response.error, 'error');
            }
        });
    }
}

function activateDepartment(departmentId) {
    $.post('department_actions.php', {
        action: 'update_status',
        department_id: departmentId,
        status: 'active'
    }, function(response) {
        if (response.success) {
            showToast(response.message, 'success');
            location.reload();
        } else {
            showToast(response.error, 'error');
        }
    });
}

function exportDepartments() {
    window.location.href = 'department_actions.php?action=export_departments';
}
</script>

<?php require_once 'include/footer.php'; ?> 