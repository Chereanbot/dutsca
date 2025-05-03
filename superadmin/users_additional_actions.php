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
            case 'bulk_assign_permissions':
                if (!isset($_POST['user_ids']) || !isset($_POST['permissions'])) {
                    throw new Exception('User IDs and permissions are required');
                }

                $db->beginTransaction();

                foreach ($_POST['user_ids'] as $userId) {
                    // Remove existing permissions
                    $db->executeQuery("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);

                    // Add new permissions
                    foreach ($_POST['permissions'] as $permissionId) {
                        $db->executeQuery("
                            INSERT INTO user_permissions (user_id, permission_id, granted_by, granted_at)
                            VALUES (?, ?, ?, NOW())
                        ", [$userId, $permissionId, $_SESSION['user_id']]);
                    }

                    // Log the action
                    $db->executeQuery("
                        INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                        VALUES (?, 'bulk_permissions_update', ?, ?)
                    ", [
                        $_SESSION['user_id'],
                        "Updated permissions for user ID: $userId",
                        $_SERVER['REMOTE_ADDR']
                    ]);
                }

                $db->commit();
                $response = ['success' => true, 'message' => 'Permissions updated successfully'];
                break;

            case 'bulk_status_update':
                if (!isset($_POST['user_ids']) || !isset($_POST['status'])) {
                    throw new Exception('User IDs and status are required');
                }

                $allowedStatuses = ['active', 'suspended', 'inactive'];
                if (!in_array($_POST['status'], $allowedStatuses)) {
                    throw new Exception('Invalid status');
                }

                $db->beginTransaction();

                foreach ($_POST['user_ids'] as $userId) {
                    $db->executeQuery("
                        UPDATE users 
                        SET status = ?, updated_at = NOW() 
                        WHERE id = ?
                    ", [$_POST['status'], $userId]);

                    // Log the action
                    $db->executeQuery("
                        INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                        VALUES (?, 'bulk_status_update', ?, ?)
                    ", [
                        $_SESSION['user_id'],
                        "Updated status to {$_POST['status']} for user ID: $userId",
                        $_SERVER['REMOTE_ADDR']
                    ]);
                }

                $db->commit();
                $response = ['success' => true, 'message' => 'Status updated successfully'];
                break;

            case 'force_password_reset':
                if (!isset($_POST['user_ids'])) {
                    throw new Exception('User IDs are required');
                }

                $db->beginTransaction();

                foreach ($_POST['user_ids'] as $userId) {
                    $db->executeQuery("
                        UPDATE users 
                        SET force_password_change = 1,
                            updated_at = NOW() 
                        WHERE id = ?
                    ", [$userId]);

                    // Log the action
                    $db->executeQuery("
                        INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                        VALUES (?, 'force_password_reset', ?, ?)
                    ", [
                        $_SESSION['user_id'],
                        "Forced password reset for user ID: $userId",
                        $_SERVER['REMOTE_ADDR']
                    ]);
                }

                $db->commit();
                $response = ['success' => true, 'message' => 'Password reset requirement set successfully'];
                break;

            case 'export_user_logs':
                if (!isset($_POST['user_id'])) {
                    throw new Exception('User ID is required');
                }

                $logs = $db->fetchAll("
                    SELECT action, description, ip_address, created_at
                    FROM user_activity_logs
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                ", [$_POST['user_id']]);

                $response = ['success' => true, 'logs' => $logs];
                break;

            case 'update_user_settings':
                if (!isset($_POST['user_id']) || !isset($_POST['settings'])) {
                    throw new Exception('User ID and settings are required');
                }

                $allowedSettings = [
                    'two_factor_enabled',
                    'login_notification',
                    'account_lockout_threshold',
                    'session_timeout'
                ];

                $updates = [];
                $params = [];

                foreach ($_POST['settings'] as $key => $value) {
                    if (in_array($key, $allowedSettings)) {
                        $updates[] = "$key = ?";
                        $params[] = $value;
                    }
                }

                if (!empty($updates)) {
                    $params[] = $_POST['user_id'];
                    $sql = "UPDATE user_settings SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE user_id = ?";
                    $db->executeQuery($sql, $params);

                    // Log the action
                    $db->executeQuery("
                        INSERT INTO user_activity_logs (user_id, action, description, ip_address)
                        VALUES (?, 'settings_update', ?, ?)
                    ", [
                        $_SESSION['user_id'],
                        "Updated settings for user ID: {$_POST['user_id']}",
                        $_SERVER['REMOTE_ADDR']
                    ]);

                    $response = ['success' => true, 'message' => 'User settings updated successfully'];
                } else {
                    throw new Exception('No valid settings provided');
                }
                break;
        }
    }
} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->rollback();
    }
    $response = ['success' => false, 'error' => $e->getMessage()];
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit(); 