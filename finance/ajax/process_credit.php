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

if (!isset($input['credit_id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

$creditId = intval($input['credit_id']);
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

    // Get credit request details
    $credit = $db->fetchOne("
        SELECT cr.*, u.name as user_name, u.email, u.balance, u.membership_date
        FROM credit_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.id = ? AND cr.status = 'pending'
    ", [$creditId]);

    if (!$credit) {
        throw new Exception('Credit request not found or already processed');
    }

    // Check eligibility if approving
    if ($action === 'approve') {
        // Check membership duration (must be > 12 months)
        $membershipDuration = (new DateTime())->diff(new DateTime($credit['membership_date']));
        if ($membershipDuration->y < 1) {
            throw new Exception('User must be a member for at least 12 months');
        }

        // Check credit limit (based on savings balance)
        $maxCreditLimit = $credit['balance'] * 2; // Example: credit limit is 2x savings
        if ($credit['amount'] > $maxCreditLimit) {
            throw new Exception('Credit amount exceeds maximum limit');
        }
    }

    // Update credit request status
    $db->execute("
        UPDATE credit_requests 
        SET 
            status = ?,
            processed_by = ?,
            processed_at = NOW()
        WHERE id = ?
    ", [$action === 'approve' ? 'approved' : 'rejected', $_SESSION['user_id'], $creditId]);

    // If approved, create a transaction and update user's balance
    if ($action === 'approve') {
        // Create credit transaction
        $db->execute("
            INSERT INTO transactions (
                user_id, type, amount, status, reference, description, created_at
            ) VALUES (
                ?, 'credit', ?, 'approved', ?, 'Credit request approved', NOW()
            )
        ", [
            $credit['user_id'],
            $credit['amount'],
            'CR' . str_pad($creditId, 8, '0', STR_PAD_LEFT)
        ]);

        // Update user's balance
        $db->execute("
            UPDATE users 
            SET 
                balance = balance + ?,
                credit_balance = credit_balance + ?
            WHERE id = ?
        ", [$credit['amount'], $credit['amount'], $credit['user_id']]);
    }

    // Log the action
    $logger->log(
        'credit_' . $action,
        'Credit request ' . $action . 'd: #' . $creditId,
        [
            'credit_id' => $creditId,
            'user_id' => $credit['user_id'],
            'amount' => $credit['amount']
        ]
    );

    // Commit transaction
    $db->commit();

    // Send notification email
    $subject = "Credit Request " . ucfirst($action) . "d";
    $message = "Dear " . $credit['user_name'] . ",\n\n";
    $message .= "Your credit request for ₦" . number_format($credit['amount'], 2);
    $message .= " has been " . $action . "d by the financial head.\n\n";
    
    if ($action === 'approve') {
        $message .= "The amount has been credited to your account. Please note the following:\n";
        $message .= "- Monthly repayment amount: ₦" . number_format($credit['monthly_payment'], 2) . "\n";
        $message .= "- First payment due: " . date('M d, Y', strtotime('+1 month')) . "\n";
        $message .= "- Total repayment period: " . $credit['duration'] . " months\n\n";
        $message .= "Please ensure timely repayment to maintain your credit standing.\n\n";
    } else {
        $message .= "Reason: Credit request rejected by financial head. ";
        $message .= "Please contact support if you have any questions.\n\n";
    }
    
    $message .= "Credit Request Details:\n";
    $message .= "- Request ID: #" . $creditId . "\n";
    $message .= "- Amount: ₦" . number_format($credit['amount'], 2) . "\n";
    $message .= "- Purpose: " . $credit['purpose'] . "\n";
    $message .= "- Date Requested: " . date('M d, Y H:i', strtotime($credit['created_at'])) . "\n\n";
    $message .= "Best regards,\nDUTSCA Financial Team";

    // Send email asynchronously
    mail(
        $credit['email'],
        $subject,
        $message,
        "From: " . SITE_EMAIL . "\r\n" .
        "Reply-To: " . SITE_EMAIL . "\r\n" .
        "X-Mailer: PHP/" . phpversion()
    );

    echo json_encode([
        'success' => true,
        'message' => 'Credit request successfully ' . $action . 'd'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('Credit Processing Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process credit request: ' . $e->getMessage()
    ]);
}
?> 