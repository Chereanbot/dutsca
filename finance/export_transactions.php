<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Validate user_id
$id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$id) {
    die('Invalid user ID');
}

// Get transaction type
$type = $_GET['type'] ?? 'all';
$typeMap = [
    'all' => '',
    'deposit' => 'deposit',
    'withdrawal' => 'withdrawal',
    'transfer' => 'transfer',
    'credit' => 'credit',
];
$transactionType = $typeMap[$type] ?? '';

try {
    $db = getDB();
    
    // Get user info
    $user = $db->fetchOne("SELECT name FROM users WHERE id = ?", [$id]);
    if (!$user) {
        die('User not found');
    }

    // Build query
    $where = ['user_id = ?'];
    $params = [$id];
    if ($transactionType) {
        $where[] = 'type = ?';
        $params[] = $transactionType;
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    
    // Get transactions
    $transactions = $db->fetchAll("
        SELECT id, type, amount, status, reference, description, created_at 
        FROM transactions 
        $whereSql 
        ORDER BY created_at DESC
    ", $params);

    // Set headers for CSV download
    $filename = sprintf(
        'transactions_%s_%s_%s.csv',
        $user['name'],
        $type,
        date('Y-m-d_His')
    );
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create CSV
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'Transaction ID',
        'Type',
        'Amount (₦)',
        'Status',
        'Reference',
        'Description',
        'Date/Time'
    ]);
    
    // Add data rows
    foreach ($transactions as $tx) {
        fputcsv($output, [
            $tx['id'],
            ucfirst($tx['type']),
            number_format($tx['amount'], 2),
            ucfirst($tx['status']),
            $tx['reference'],
            $tx['description'],
            date('Y-m-d H:i', strtotime($tx['created_at']))
        ]);
    }
    
    fclose($output);

} catch (Exception $e) {
    error_log('Export error: ' . $e->getMessage());
    die('Error exporting transactions. Please try again later.');
} 