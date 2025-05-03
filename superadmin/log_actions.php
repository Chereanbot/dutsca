<?php
require_once '../config/database.php';
require_once '../config/config.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get database connection
$db = getDB();

// Initialize response
$response = ['success' => false];

try {
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'get_details':
            $logId = $_GET['log_id'] ?? null;
            if (!$logId) {
                throw new Exception('Log ID is required');
            }

            // Get log details
            $log = $db->fetchOne("
                SELECT 
                    l.*,
                    u.name as user_name,
                    u.email as user_email
                FROM system_logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = ?
            ", [$logId]);

            if (!$log) {
                throw new Exception('Log not found');
            }

            // Format the details HTML
            $html = '
            <div class="log-details">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">Timestamp</h6>
                        <p>' . date('Y-m-d H:i:s', strtotime($log['created_at'])) . '</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">Type</h6>
                        <span class="log-type log-type-' . $log['log_type'] . '">' . ucfirst($log['log_type']) . '</span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="mb-2">User</h6>
                        ' . ($log['user_id'] ? '
                        <div>
                            <strong>' . htmlspecialchars($log['user_name']) . '</strong><br>
                            <small class="text-muted">' . htmlspecialchars($log['user_email']) . '</small>
                        </div>
                        ' : '<span class="text-muted">System</span>') . '
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-2">IP Address</h6>
                        <p>' . htmlspecialchars($log['ip_address']) . '</p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6 class="mb-2">Message</h6>
                    <p>' . htmlspecialchars($log['message']) . '</p>
                </div>';

            // Add additional data if available
            if (!empty($log['additional_data'])) {
                $additionalData = json_decode($log['additional_data'], true);
                if ($additionalData) {
                    $html .= '
                    <div>
                        <h6 class="mb-2">Additional Data</h6>
                        <pre class="bg-light p-3 rounded"><code>' . htmlspecialchars(json_encode($additionalData, JSON_PRETTY_PRINT)) . '</code></pre>
                    </div>';
                }
            }

            $html .= '</div>';

            $response = [
                'success' => true,
                'html' => $html
            ];
            break;

        case 'clear_logs':
            // Only allow POST method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }

            // Begin transaction
            $db->beginTransaction();

            try {
                // Archive logs before deletion (optional)
                $archiveDate = date('Y-m-d_H-i-s');
                $archiveFile = __DIR__ . "/../logs/archive/logs_{$archiveDate}.json";
                
                // Ensure archive directory exists
                if (!is_dir(__DIR__ . '/../logs/archive')) {
                    mkdir(__DIR__ . '/../logs/archive', 0755, true);
                }

                // Get all logs
                $logs = $db->fetchAll("SELECT * FROM system_logs");
                
                // Save to archive
                file_put_contents($archiveFile, json_encode($logs, JSON_PRETTY_PRINT));

                // Delete all logs
                $db->executeQuery("DELETE FROM system_logs");

                // Log the clear action
                $db->executeQuery(
                    "INSERT INTO system_logs (log_type, message, user_id, ip_address, additional_data) VALUES (?, ?, ?, ?, ?)",
                    [
                        'info',
                        'All system logs cleared and archived',
                        $_SESSION['user_id'],
                        $_SERVER['REMOTE_ADDR'],
                        json_encode(['archive_file' => basename($archiveFile)])
                    ]
                );

                $db->commit();
                $response = [
                    'success' => true,
                    'message' => 'All logs have been cleared and archived successfully'
                ];
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'export':
            // Get filter parameters
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';
            $logType = $_GET['log_type'] ?? '';
            $userId = $_GET['user_id'] ?? '';
            $searchTerm = $_GET['search'] ?? '';

            // Build query with filters
            $query = "
                SELECT 
                    l.*,
                    u.name as user_name,
                    u.email as user_email
                FROM system_logs l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE 1=1
            ";
            $params = [];

            if ($startDate) {
                $query .= " AND l.created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if ($endDate) {
                $query .= " AND l.created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }
            if ($logType) {
                $query .= " AND l.log_type = ?";
                $params[] = $logType;
            }
            if ($userId) {
                $query .= " AND l.user_id = ?";
                $params[] = $userId;
            }
            if ($searchTerm) {
                $query .= " AND (l.message LIKE ? OR l.ip_address LIKE ? OR u.name LIKE ?)";
                $searchParam = "%{$searchTerm}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            $query .= " ORDER BY l.created_at DESC";
            
            // Get logs
            $logs = $db->fetchAll($query, $params);

            // Prepare CSV data
            $csvData = [
                ['Timestamp', 'Type', 'User', 'Email', 'Message', 'IP Address', 'Additional Data']
            ];

            foreach ($logs as $log) {
                $csvData[] = [
                    date('Y-m-d H:i:s', strtotime($log['created_at'])),
                    ucfirst($log['log_type']),
                    $log['user_id'] ? $log['user_name'] : 'System',
                    $log['user_id'] ? $log['user_email'] : '-',
                    $log['message'],
                    $log['ip_address'],
                    $log['additional_data'] ? json_encode(json_decode($log['additional_data'], true)) : ''
                ];
            }

            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');

            // Output CSV
            $output = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Only send JSON response for non-export actions
if ($action !== 'export') {
    header('Content-Type: application/json');
    echo json_encode($response);
}
?> 