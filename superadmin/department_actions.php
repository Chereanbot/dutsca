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

try {
    $db = getDB();
    $response = ['success' => false];

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add_department':
                // Validate required fields
                if (empty($_POST['name'])) {
                    throw new Exception('Department name is required');
                }

                // Sanitize inputs
                $name = trim($_POST['name']);
                $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
                $contact_email = !empty($_POST['contact_email']) ? trim($_POST['contact_email']) : null;
                $contact_phone = !empty($_POST['contact_phone']) ? trim($_POST['contact_phone']) : null;
                $location = !empty($_POST['location']) ? trim($_POST['location']) : null;

                // Validate department name length
                if (strlen($name) < 2 || strlen($name) > 100) {
                    throw new Exception('Department name must be between 2 and 100 characters');
                }

                // Validate email format if provided
                if ($contact_email && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }

                // Check if department name already exists
                $existing = $db->fetchOne("SELECT id FROM departments WHERE name = ?", [$name]);
                if ($existing) {
                    throw new Exception('A department with this name already exists');
                }

                // Start transaction
                $db->beginTransaction();

                try {
                    // Insert new department
                    $db->executeQuery("
                        INSERT INTO departments (
                            name, description, contact_email,
                            contact_phone, location, status,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
                    ", [
                        $name,
                        $description,
                        $contact_email,
                        $contact_phone,
                        $location
                    ]);

                    // Get the new department ID
                    $departmentId = $db->getLastInsertId();

                    // Log the action
                    $db->executeQuery("
                        INSERT INTO user_activity_logs (
                            user_id, action, description,
                            ip_address, created_at
                        ) VALUES (?, ?, ?, ?, NOW())
                    ", [
                        $_SESSION['user_id'],
                        'create_department',
                        "Created new department: {$name}",
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $db->commit();
                    $response = [
                        'success' => true,
                        'message' => 'Department created successfully'
                    ];
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;

            case 'update_status':
                // Validate required fields
                if (empty($_POST['department_id']) || empty($_POST['status'])) {
                    throw new Exception('Department ID and status are required');
                }

                // Check if department exists
                $department = $db->fetchOne("SELECT name FROM departments WHERE id = ?", [$_POST['department_id']]);
                if (!$department) {
                    throw new Exception('Department not found');
                }

                // Update department status
                $db->executeQuery("
                    UPDATE departments 
                    SET status = ?, updated_at = NOW() 
                    WHERE id = ?
                ", [
                    $_POST['status'],
                    $_POST['department_id']
                ]);

                // Log the action
                $db->executeQuery("
                    INSERT INTO user_activity_logs (
                        user_id, action, description,
                        ip_address, created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ", [
                    $_SESSION['user_id'],
                    'update_department_status',
                    "Updated department status to {$_POST['status']}: {$department['name']}",
                    $_SERVER['REMOTE_ADDR']
                ]);

                $response = [
                    'success' => true,
                    'message' => 'Department status updated successfully'
                ];
                break;

            case 'update_department':
                // Validate required fields
                if (empty($_POST['department_id']) || empty($_POST['name'])) {
                    throw new Exception('Department ID and name are required');
                }

                // Check if department exists
                $department = $db->fetchOne("SELECT id FROM departments WHERE id = ?", [$_POST['department_id']]);
                if (!$department) {
                    throw new Exception('Department not found');
                }

                // Check if name is already taken by another department
                $existing = $db->fetchOne("
                    SELECT id FROM departments 
                    WHERE name = ? AND id != ?
                ", [$_POST['name'], $_POST['department_id']]);
                
                if ($existing) {
                    throw new Exception('A department with this name already exists');
                }

                // Start transaction
                $db->beginTransaction();

                try {
                    // Update department
                    $db->query("
                        UPDATE departments SET
                            name = ?,
                            description = ?,
                            head_id = ?,
                            contact_email = ?,
                            contact_phone = ?,
                            location = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ", [
                        $_POST['name'],
                        $_POST['description'] ?? null,
                        $_POST['head_id'] ?: null,
                        $_POST['contact_email'] ?? null,
                        $_POST['contact_phone'] ?? null,
                        $_POST['location'] ?? null,
                        $_POST['department_id']
                    ]);

                    // Log the action
                    $db->query("
                        INSERT INTO user_activity_logs (
                            user_id, action, description,
                            ip_address, created_at
                        ) VALUES (?, ?, ?, ?, NOW())
                    ", [
                        $_SESSION['user_id'],
                        'update_department',
                        "Updated department: {$_POST['name']}",
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $db->commit();
                    $response = [
                        'success' => true,
                        'message' => 'Department updated successfully'
                    ];
                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }
                break;
        }
    }
    // Handle GET requests
    else {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'get_department':
                if (empty($_GET['department_id'])) {
                    throw new Exception('Department ID is required');
                }

                $department = $db->fetchOne("
                    SELECT d.*, u.name as head_name 
                    FROM departments d
                    LEFT JOIN users u ON d.head_id = u.id
                    WHERE d.id = ?
                ", [$_GET['department_id']]);

                if (!$department) {
                    throw new Exception('Department not found');
                }

                // Get all users for department head selection
                $users = $db->fetchAll("
                    SELECT id, name, email, role 
                    FROM users 
                    WHERE status = 'active' 
                    ORDER BY name ASC
                ");

                // Generate edit modal HTML
                ob_start();
                include 'templates/edit_department_modal.php';
                $modalHtml = ob_get_clean();

                $response = [
                    'success' => true,
                    'html' => $modalHtml
                ];
                break;

            case 'get_members':
                if (empty($_GET['department_id'])) {
                    throw new Exception('Department ID is required');
                }

                $department = $db->fetchOne("
                    SELECT * FROM departments WHERE id = ?
                ", [$_GET['department_id']]);

                if (!$department) {
                    throw new Exception('Department not found');
                }

                // Get department members
                $members = $db->fetchAll("
                    SELECT u.*, dm.role as department_role, dm.joined_at
                    FROM department_members dm
                    JOIN users u ON dm.user_id = u.id
                    WHERE dm.department_id = ?
                    ORDER BY u.name ASC
                ", [$_GET['department_id']]);

                // Generate members list HTML
                ob_start();
                include 'templates/department_members_list.php';
                $membersHtml = ob_get_clean();

                $response = [
                    'success' => true,
                    'html' => $membersHtml
                ];
                break;

            case 'export_departments':
                // Set headers for CSV download
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="departments_export_' . date('Y-m-d') . '.csv"');

                // Create output stream
                $output = fopen('php://output', 'w');

                // Add UTF-8 BOM for Excel compatibility
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

                // Add headers
                fputcsv($output, [
                    'ID', 'Name', 'Description', 'Head', 'Contact Email',
                    'Contact Phone', 'Location', 'Status', 'Member Count',
                    'Created At', 'Updated At'
                ]);

                // Fetch all departments with their data
                $departments = $db->fetchAll("
                    SELECT 
                        d.*,
                        u.name as head_name,
                        (SELECT COUNT(*) FROM department_members WHERE department_id = d.id) as member_count
                    FROM departments d
                    LEFT JOIN users u ON d.head_id = u.id
                    ORDER BY d.name ASC
                ");

                // Add department data
                foreach ($departments as $dept) {
                    fputcsv($output, [
                        $dept['id'],
                        $dept['name'],
                        $dept['description'],
                        $dept['head_name'],
                        $dept['contact_email'],
                        $dept['contact_phone'],
                        $dept['location'],
                        $dept['status'],
                        $dept['member_count'],
                        $dept['created_at'],
                        $dept['updated_at']
                    ]);
                }

                fclose($output);
                exit();
        }
    }
} catch (Exception $e) {
    error_log("Error in department_actions.php: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Send JSON response for all actions except export
if (!isset($_GET['action']) || $_GET['action'] !== 'export_departments') {
    header('Content-Type: application/json');
    echo json_encode($response);
}
?> 