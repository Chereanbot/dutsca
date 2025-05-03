<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../include/Logger.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has financial_head role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'financial_head') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['transaction_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$transactionId = intval($input['transaction_id']);
$action = strtolower($input['action']);

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

try {
    $db = getDB();
    $logger = Logger::getInstance();

    // Start transaction
    $db->beginTransaction();

    // Get transaction details
    $transaction = $db->fetchOne("
        SELECT t.*, u.name as user_name, u.email
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND t.status = 'pending'
    ", [$transactionId]);

    if (!$transaction) {
        throw new Exception('Transaction not found or already processed');
    }

    // Update transaction status
    $db->execute("
        UPDATE transactions 
        SET 
            status = ?,
            processed_by = ?,
            processed_at = NOW()
        WHERE id = ?
    ", [$action === 'approve' ? 'approved' : 'rejected', $_SESSION['user_id'], $transactionId]);

    // If approved, update user's balance
    if ($action === 'approve') {
        $amount = $transaction['type'] === 'deposit' ? $transaction['amount'] : -$transaction['amount'];
        $db->execute("
            UPDATE users 
            SET balance = balance + ? 
            WHERE id = ?
        ", [$amount, $transaction['user_id']]);
    }

    // Log the action
    $logger->log(
        'transaction_' . $action,
        'Transaction ' . $action . 'd: #' . $transactionId,
        [
            'transaction_id' => $transactionId,
            'user_id' => $transaction['user_id'],
            'amount' => $transaction['amount'],
            'type' => $transaction['type']
        ]
    );

    // Commit transaction
    $db->commit();

    // Send notification email
    $subject = "Transaction " . ucfirst($action) . "d";
    $message = "Dear " . $transaction['user_name'] . ",\n\n";
    $message .= "Your " . $transaction['type'] . " transaction of ₦" . number_format($transaction['amount'], 2);
    $message .= " has been " . $action . "d by the financial head.\n\n";
    
    if ($action === 'approve') {
        $message .= "The amount has been " . ($transaction['type'] === 'deposit' ? "added to" : "deducted from") . " your account balance.\n\n";
    } else {
        $message .= "Reason: Transaction rejected by financial head. Please contact support if you have any questions.\n\n";
    }
    
    $message .= "Transaction Details:\n";
    $message .= "- Transaction ID: #" . $transactionId . "\n";
    $message .= "- Type: " . ucfirst($transaction['type']) . "\n";
    $message .= "- Amount: ₦" . number_format($transaction['amount'], 2) . "\n";
    $message .= "- Date: " . date('M d, Y H:i', strtotime($transaction['created_at'])) . "\n\n";
    $message .= "Best regards,\nDUTSCA Financial Team";

    // Send email asynchronously
    mail(
        $transaction['email'],
        $subject,
        $message,
        "From: " . SITE_EMAIL . "\r\n" .
        "Reply-To: " . SITE_EMAIL . "\r\n" .
        "X-Mailer: PHP/" . phpversion()
    );

    echo json_encode([
        'success' => true,
        'message' => 'Transaction successfully ' . $action . 'd'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Transaction Processing Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process transaction: ' . $e->getMessage()
    ]);
}
?> 