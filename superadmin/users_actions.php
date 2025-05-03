<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has superadmin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once '../config/config.php';
require_once '../config/database.php';

$db = getDB();
$response = ['success' => false, 'error' => 'Unknown error occurred'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($_POST['action']) {
            case 'add_user':
                // Validate input
                if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['email'])) {
                    throw new Exception('Required fields are missing');
                }

                // Check if username or email already exists
                $existingUser = $db->fetchOne("
                    SELECT id FROM users 
                    WHERE username = ? OR email = ?
                ", [$_POST['username'], $_POST['email']]);

                if ($existingUser) {
                    throw new Exception('Username or email already exists');
                }

                // Handle profile image upload
                $profileImage = null;
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/profiles/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($fileExtension, $allowedExtensions)) {
                        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
                    }

                    $fileName = uniqid() . '.' . $fileExtension;
                    $uploadFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadFile)) {
                        $profileImage = 'uploads/profiles/' . $fileName;
                    }
                }

                // Start transaction
                $db->beginTransaction();

                // Insert new user
                $stmt = $db->executeQuery("
                    INSERT INTO users (
                        username, password, email, role, status, name,
                        contact_number, department, profile_image,
                        email_verified, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ", [
                    $_POST['username'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $_POST['email'],
                    $_POST['role'],
                    'active',
                    $_POST['name'],
                    $_POST['contact_number'],
                    $_POST['department'],
                    $profileImage,
                    true
                ]);

                $userId = $db->getLastInsertId();

                // Add permissions if selected
                if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                    $permissionStmt = $db->prepare("
                        INSERT INTO user_permissions (user_id, permission_id, granted_by)
                        VALUES (?, ?, ?)
                    ");

                    foreach ($_POST['permissions'] as $permissionId) {
                        $permissionStmt->execute([$userId, $permissionId, $_SESSION['user_id']]);
                    }
                }

                // Log the action
                $db->executeQuery("
                    INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                    VALUES (?, 'user_created', ?, ?)
                ", [
                    $_SESSION['user_id'],
                    "Created new user: {$_POST['username']}",
                    $_SERVER['REMOTE_ADDR']
                ]);

                $db->commit();
                $_SESSION['success'] = 'User created successfully!';
                header('Location: users.php');
                exit();

            case 'edit_user':
                if (!isset($_POST['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $userId = $_POST['user_id'];
                $updates = [];
                $params = [];

                // Build update query dynamically
                if (!empty($_POST['name'])) {
                    $updates[] = 'name = ?';
                    $params[] = $_POST['name'];
                }
                if (!empty($_POST['email'])) {
                    $updates[] = 'email = ?';
                    $params[] = $_POST['email'];
                }
                if (!empty($_POST['role'])) {
                    $updates[] = 'role = ?';
                    $params[] = $_POST['role'];
                }
                if (!empty($_POST['department'])) {
                    $updates[] = 'department = ?';
                    $params[] = $_POST['department'];
                }
                if (!empty($_POST['contact_number'])) {
                    $updates[] = 'contact_number = ?';
                    $params[] = $_POST['contact_number'];
                }

                // Handle profile image update
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/profiles/';
                    $fileExtension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                    $fileName = uniqid() . '.' . $fileExtension;
                    $uploadFile = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadFile)) {
                        $updates[] = 'profile_image = ?';
                        $params[] = 'uploads/profiles/' . $fileName;
                    }
                }

                if (!empty($updates)) {
                    $params[] = $userId;
                    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                    $db->executeQuery($sql, $params);

                    // Update permissions
                    if (isset($_POST['permissions'])) {
                        // Remove existing permissions
                        $db->executeQuery("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);

                        // Add new permissions
                        foreach ($_POST['permissions'] as $permissionId) {
                            $db->executeQuery("
                                INSERT INTO user_permissions (user_id, permission_id, granted_by)
                                VALUES (?, ?, ?)
                            ", [$userId, $permissionId, $_SESSION['user_id']]);
                        }
                    }

                    $_SESSION['success'] = 'User updated successfully!';
                } else {
                    $_SESSION['error'] = 'No changes were made';
                }

                header('Location: users.php');
                exit();

            case 'reset_password':
                if (!isset($_POST['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $newPassword = generateRandomPassword();
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $db->executeQuery("
                    UPDATE users 
                    SET password = ?, password_reset_token = NULL, password_reset_expires = NULL 
                    WHERE id = ?
                ", [$hashedPassword, $_POST['user_id']]);

                // Log the action
                $db->executeQuery("
                    INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                    VALUES (?, 'password_reset', ?, ?)
                ", [
                    $_SESSION['user_id'],
                    "Reset password for user ID: {$_POST['user_id']}",
                    $_SERVER['REMOTE_ADDR']
                ]);

                $response = [
                    'success' => true,
                    'message' => "Password has been reset to: $newPassword"
                ];
                break;

            case 'suspend_user':
                if (!isset($_POST['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $db->executeQuery("
                    UPDATE users 
                    SET status = 'suspended', 
                        updated_at = NOW() 
                    WHERE id = ?
                ", [$_POST['user_id']]);

                // Log the action
                $db->executeQuery("
                    INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                    VALUES (?, 'user_suspended', ?, ?)
                ", [
                    $_SESSION['user_id'],
                    "Suspended user ID: {$_POST['user_id']}",
                    $_SERVER['REMOTE_ADDR']
                ]);

                $response = [
                    'success' => true,
                    'message' => 'User has been suspended'
                ];
                break;

            case 'activate_user':
                if (!isset($_POST['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $db->executeQuery("
                    UPDATE users 
                    SET status = 'active', 
                        updated_at = NOW() 
                    WHERE id = ?
                ", [$_POST['user_id']]);

                // Log the action
                $db->executeQuery("
                    INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                    VALUES (?, 'user_activated', ?, ?)
                ", [
                    $_SESSION['user_id'],
                    "Activated user ID: {$_POST['user_id']}",
                    $_SERVER['REMOTE_ADDR']
                ]);

                $response = [
                    'success' => true,
                    'message' => 'User has been activated'
                ];
                break;
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        switch ($_GET['action']) {
            case 'get_user':
                if (!isset($_GET['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $user = $db->fetchOne("
                    SELECT * FROM users WHERE id = ?
                ", [$_GET['user_id']]);

                if (!$user) {
                    throw new Exception('User not found');
                }

                // Fetch user's current permissions
                $userPermissions = $db->fetchAll("
                    SELECT permission_id 
                    FROM user_permissions 
                    WHERE user_id = ?
                ", [$_GET['user_id']]);

                $userPermissionIds = array_column($userPermissions, 'permission_id');

                // Fetch all permissions
                $permissions = $db->fetchAll("
                    SELECT p.*, pc.name as category_name 
                    FROM permissions p
                    LEFT JOIN permission_categories pc ON p.category_id = pc.id
                    ORDER BY pc.display_order, p.display_name
                ");

                // Fetch departments
                $departments = $db->fetchAll("SELECT name FROM departments ORDER BY name");

                // Generate modal HTML
                ob_start();
                include 'templates/edit_user_modal.php';
                $modalHtml = ob_get_clean();

                $response = [
                    'success' => true,
                    'html' => $modalHtml
                ];
                break;

            case 'get_permissions':
                if (!isset($_GET['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $permissions = $db->fetchAll("
                    SELECT p.*, pc.name as category_name,
                           up.granted_at, u.name as granted_by_name
                    FROM user_permissions up
                    JOIN permissions p ON up.permission_id = p.id
                    LEFT JOIN permission_categories pc ON p.category_id = pc.id
                    LEFT JOIN users u ON up.granted_by = u.id
                    WHERE up.user_id = ?
                    ORDER BY pc.display_order, p.display_name
                ", [$_GET['user_id']]);

                ob_start();
                include 'templates/user_permissions_list.php';
                $permissionsHtml = ob_get_clean();

                $response = [
                    'success' => true,
                    'html' => $permissionsHtml
                ];
                break;

            case 'export_users':
                // Set headers for CSV download
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');

                $users = $db->fetchAll("
                    SELECT u.*, d.name as department_name,
                           (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id) as permissions_count
                    FROM users u
                    LEFT JOIN departments d ON u.department = d.name
                    ORDER BY u.created_at DESC
                ");

                $output = fopen('php://output', 'w');
                
                // Add CSV headers
                fputcsv($output, [
                    'Name', 'Email', 'Username', 'Role', 'Department',
                    'Status', 'Contact Number', 'Permissions Count',
                    'Last Login', 'Created At'
                ]);

                // Add user data
                foreach ($users as $user) {
                    fputcsv($output, [
                        $user['name'],
                        $user['email'],
                        $user['username'],
                        $user['role'],
                        $user['department_name'],
                        $user['status'],
                        $user['contact_number'],
                        $user['permissions_count'],
                        $user['last_login'],
                        $user['created_at']
                    ]);
                }

                fclose($output);
                exit();
        }
    }
} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    $response = ['success' => false, 'error' => $e->getMessage()];
}

// Send JSON response for AJAX requests
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Helper function to generate random password
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
} 