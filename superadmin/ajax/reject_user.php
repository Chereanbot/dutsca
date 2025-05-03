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
    
    // Get user details before deletion
    $user = $db->fetchOne("
        SELECT name, email, role 
        FROM users 
        WHERE id = ? AND status = 'pending'
    ", [$userId]);
    
    if (!$user) {
        throw new Exception('User not found or already processed');
    }
    
    // Delete the pending user
    $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
    
    // Log the rejection
    $logger->logActivity(
        $_SESSION['user_id'],
        'user',
        'reject',
        sprintf('Rejected user registration for %s (%s)', $user['name'], $user['email'])
    );
    
    // Send rejection email
    $to = $user['email'];
    $subject = 'Account Registration Status';
    $message = "Dear {$user['name']},\n\n"
             . "We regret to inform you that your account registration request has been declined.\n"
             . "If you believe this is an error, please contact the system administrator.\n\n"
             . "Best regards,\nSystem Administrator";
    
    mail($to, $subject, $message);
    
    echo json_encode([
        'success' => true,
        'message' => 'User rejected successfully'
    ]);
    
} catch (Exception $e) {
    error_log('User Rejection Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 