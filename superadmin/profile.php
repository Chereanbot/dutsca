<?php
// Include configuration first
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /dutsca/index.php');
    exit();
}

// Get database connection
$db = getDB();
$userId = $_SESSION['user_id'];
$user = null;
$departments = [];
$error = '';
$success = '';

try {
    // Fetch user details
    $stmt = $db->executeQuery("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.role,
            u.status,
            u.name,
            u.contact_number,
            d.name as department_name,
            d.id as department_id,
            u.employee_id,
            u.position,
            u.date_joined,
            u.profile_image,
            DATE_FORMAT(u.date_joined, '%Y-%m-%d') as formatted_date_joined,
            DATE_FORMAT(u.last_login, '%M %d, %Y %h:%i %p') as last_login_formatted
        FROM users u
        LEFT JOIN departments d ON u.department = d.id
        WHERE u.id = ?
    ", [$userId]);
$user = $stmt->fetch();

    // Fetch all departments for the dropdown
    $stmt = $db->executeQuery("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $stmt->fetchAll();

} catch (Exception $e) {
    $error = 'Error loading profile: ' . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updateFields = [];
        $params = [];
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
            $uploadDir = '../uploads/profile_images/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Only JPG, JPEG & PNG files are allowed');
            }
            
            $newFileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
                $updateFields[] = "profile_image = ?";
                $params[] = $newFileName;
                
                // Delete old profile image if exists
                if ($user['profile_image'] && file_exists($uploadDir . $user['profile_image'])) {
                    unlink($uploadDir . $user['profile_image']);
                }
            }
        }
        
        // Update other fields
        $fields = [
            'name' => 'string',
            'contact_number' => 'string',
            'department' => 'int',
            'position' => 'string',
            'date_joined' => 'date'
        ];
        
        foreach ($fields as $field => $type) {
            if (isset($_POST[$field]) && (!empty($_POST[$field]) || $type === 'int')) {
                $updateFields[] = "$field = ?";
                $params[] = $_POST[$field];
            }
        }
        
        if (!empty($updateFields)) {
            $params[] = $userId;
            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $db->executeQuery($query, $params);
            $_SESSION['success_message'] = 'Profile updated successfully!';
            
            // Refresh user data
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
        
    } catch (Exception $e) {
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}

// Get any flash messages
$success = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Now include the header and sidebar
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

.profile-header {
    background: #fff;
    border-radius: 0.5rem;
    padding: 2rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 2rem;
}

.profile-image-container {
    position: relative;
    width: 150px;
    height: 150px;
    margin: 0 auto 1rem;
}

.profile-image {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.profile-image-upload {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #00572d;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.profile-image-upload:hover {
    background: #1f9345;
    transform: scale(1.1);
}

.profile-info {
    text-align: center;
}

.profile-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #00572d;
    margin: 0.5rem 0;
}

.profile-role {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    background: #e8f5e9;
    color: #1f9345;
    border-radius: 2rem;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 1rem;
}

.profile-stats {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #00572d;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.form-label {
    font-weight: 500;
    color: #495057;
}

.form-control:focus {
    border-color: #1f9345;
    box-shadow: 0 0 0 0.2rem rgba(31, 147, 69, 0.25);
}

.btn-primary {
    background: #00572d;
    border: none;
}

.btn-primary:hover {
    background: #1f9345;
}

.profile-section {
    background: #fff;
    border-radius: 0.5rem;
    padding: 2rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.section-title {
    color: #00572d;
    font-weight: 600;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e9ecef;
}
</style>

<div class="main-content">
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

    <div class="profile-header">
        <form id="profileForm" method="POST" enctype="multipart/form-data">
            <div class="profile-image-container">
                <img src="<?php 
                    echo $user['profile_image'] 
                        ? '../uploads/profile_images/' . htmlspecialchars($user['profile_image'])
                        : 'https://via.placeholder.com/150'; 
                    ?>" 
                    alt="Profile" class="profile-image" id="profileImagePreview">
                <label for="profileImageInput" class="profile-image-upload">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="profileImageInput" name="profile_image" 
                       accept="image/jpeg,image/png" style="display: none;"
                       onchange="previewImage(this)">
                </div>

            <div class="profile-info">
                <h3 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                <span class="profile-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?></div>
                        <div class="stat-label">Employee ID</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></div>
                        <div class="stat-label">Department</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo htmlspecialchars($user['last_login_formatted'] ?? 'Never'); ?></div>
                        <div class="stat-label">Last Login</div>
                    </div>
                </div>
                </div>
        </div>

        <div class="profile-section">
            <h4 class="section-title">Personal Information</h4>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" class="form-control" name="contact_number"
                               value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $user['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position"
                               value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>">
        </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Date Joined</label>
                        <input type="date" class="form-control" name="date_joined"
                               value="<?php echo htmlspecialchars($user['formatted_date_joined'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
                </div>
            </form>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profileImagePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script> 

<?php require_once __DIR__ . '/include/footer.php'; ?> 