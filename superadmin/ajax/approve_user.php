<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../include/Logger.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = getDB();
    $logger = Logger::getInstance();
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id'] ?? null;
    
    if (!$userId) {
        throw new Exception('User ID is required');
    }
    
    // Get user details before update
    $user = $db->fetchOne("
        SELECT name, email, role 
        FROM users 
        WHERE id = ? AND status = 'pending'
    ", [$userId]);
    
    if (!$user) {
        throw new Exception('User not found or already approved');
    }
    
    // Update user status to active
    $db->execute("
        UPDATE users 
        SET status = 'active', 
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ?
    ", [$_SESSION['user_id'], $userId]);
    
    // Log the approval
    $logger->logActivity(
        $_SESSION['user_id'],
        'user',
        'approve',
        sprintf('Approved user account for %s (%s)', $user['name'], $user['email'])
    );
    
    // Send approval email
    $to = $user['email'];
    $subject = 'Your Account Has Been Approved';
    $message = "Dear {$user['name']},\n\n"
             . "Your account has been approved. You can now log in to the system.\n\n"
             . "Role: {$user['role']}\n"
             . "Login URL: " . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') 
             . $_SERVER['HTTP_HOST'] . "/login.php\n\n"
             . "Best regards,\nSystem Administrator";
    
    mail($to, $subject, $message);
    
    echo json_encode([
        'success' => true,
        'message' => 'User approved successfully'
    ]);
    
} catch (Exception $e) {
    error_log('User Approval Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 