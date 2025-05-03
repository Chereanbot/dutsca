<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Initialize response
$response = ['success' => false];

try {
    $db = getDB();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_details':
            $activityId = intval($_GET['activity_id'] ?? 0);
            if (!$activityId) {
                throw new Exception('Invalid activity ID');
            }

            // Get activity details with user information
            $query = "
                SELECT 
                    al.*,
                    u.name as user_name,
                    u.email as user_email,
                    u.role as user_role
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.id = ?
            ";
            $activity = $db->fetchOne($query, [$activityId]);
            if (!$activity) {
                throw new Exception('Activity not found');
            }

            // Format additional data if it exists
            $additionalData = $activity['additional_data'] ? json_decode($activity['additional_data'], true) : [];

            // Build HTML for activity details
            $html = '
                <div class="activity-details">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Basic Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th>Timestamp</th>
                                    <td>' . date('Y-m-d H:i:s', strtotime($activity['created_at'])) . '</td>
                                </tr>
                                <tr>
                                    <th>Category</th>
                                    <td><span class="activity-badge activity-badge-' . htmlspecialchars($activity['category']) . '">' 
                                        . ucfirst(htmlspecialchars($activity['category'])) . '</span></td>
                                </tr>
                                <tr>
                                    <th>Action</th>
                                    <td>' . ucfirst(htmlspecialchars($activity['action'])) . '</td>
                                </tr>
                                <tr>
                                    <th>Message</th>
                                    <td>' . htmlspecialchars($activity['message']) . '</td>
                                </tr>
                                <tr>
                                    <th>IP Address</th>
                                    <td>' . htmlspecialchars($activity['ip_address']) . '</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">User Information</h6>
                            <table class="table table-sm">
                                ' . ($activity['user_id'] ? '
                                <tr>
                                    <th>Name</th>
                                    <td>' . htmlspecialchars($activity['user_name']) . '</td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td>' . htmlspecialchars($activity['user_email']) . '</td>
                                </tr>
                                <tr>
                                    <th>Role</th>
                                    <td>' . ucfirst(htmlspecialchars($activity['user_role'])) . '</td>
                                </tr>
                                ' : '<tr><td colspan="2" class="text-muted">System Activity</td></tr>') . '
                            </table>
                        </div>
                    </div>';

            // Add additional data section if exists
            if (!empty($additionalData)) {
                $html .= '
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted mb-2">Additional Data</h6>
                            <div class="bg-light p-3 rounded">
                                <pre class="mb-0"><code>' . htmlspecialchars(json_encode($additionalData, JSON_PRETTY_PRINT)) . '</code></pre>
                            </div>
                        </div>
                    </div>';
            }

            $html .= '</div>';

            $response = [
                'success' => true,
                'html' => $html
            ];
            break;

        case 'export':
            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');
            
            // Create output handle
            $output = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write CSV header
            fputcsv($output, [
                'Timestamp',
                'User',
                'Email',
                'Role',
                'Category',
                'Action',
                'Message',
                'IP Address',
                'Additional Data'
            ]);

            // Build query with filters
            $query = "
                SELECT 
                    al.*,
                    u.name as user_name,
                    u.email as user_email,
                    u.role as user_role
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1
            ";
            $params = [];

            // Add filters from GET parameters
            if (!empty($_GET['start_date'])) {
                $query .= " AND al.created_at >= ?";
                $params[] = $_GET['start_date'] . ' 00:00:00';
            }
            if (!empty($_GET['end_date'])) {
                $query .= " AND al.created_at <= ?";
                $params[] = $_GET['end_date'] . ' 23:59:59';
            }
            if (!empty($_GET['category'])) {
                $query .= " AND al.category = ?";
                $params[] = $_GET['category'];
            }
            if (!empty($_GET['action'])) {
                $query .= " AND al.action = ?";
                $params[] = $_GET['action'];
            }
            if (!empty($_GET['user_id'])) {
                $query .= " AND al.user_id = ?";
                $params[] = $_GET['user_id'];
            }
            if (!empty($_GET['search'])) {
                $query .= " AND (al.message LIKE ? OR al.ip_address LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
                $searchParam = "%" . $_GET['search'] . "%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            $query .= " ORDER BY al.created_at DESC";
            
            // Fetch and write data
            $stmt = $db->query($query, $params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['created_at'],
                    $row['user_name'] ?? 'System',
                    $row['user_email'] ?? '',
                    $row['user_role'] ?? '',
                    ucfirst($row['category']),
                    ucfirst($row['action']),
                    $row['message'],
                    $row['ip_address'],
                    $row['additional_data']
                ]);
            }
            
            fclose($output);
            exit();

        case 'export_report':
            // Get date range
            $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $_GET['end_date'] ?? date('Y-m-d');

            // Set headers for Excel download
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="activity_report_' . date('Y-m-d_His') . '.xls"');
            header('Cache-Control: max-age=0');

            // Start HTML output
            echo '<!DOCTYPE html>';
            echo '<html>';
            echo '<head>';
            echo '<meta charset="UTF-8">';
            echo '<style>';
            echo 'table { border-collapse: collapse; width: 100%; }';
            echo 'th, td { border: 1px solid #000; padding: 5px; }';
            echo 'th { background-color: #f0f0f0; }';
            echo '.section { margin: 20px 0; }';
            echo '</style>';
            echo '</head>';
            echo '<body>';

            // Report Title
            echo '<h1>Activity Report</h1>';
            echo '<p>Period: ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . '</p>';

            // Activity Overview
            echo '<div class="section">';
            echo '<h2>Activity Overview</h2>';
            $overview = $db->fetchOne("
                SELECT 
                    COUNT(*) as total_activities,
                    COUNT(DISTINCT user_id) as total_users,
                    COUNT(DISTINCT DATE(created_at)) as total_days
                FROM activity_logs
                WHERE created_at BETWEEN ? AND ?
            ", [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            echo '<table>';
            echo '<tr><th>Total Activities</th><th>Active Users</th><th>Active Days</th></tr>';
            echo '<tr>';
            echo '<td>' . number_format($overview['total_activities']) . '</td>';
            echo '<td>' . number_format($overview['total_users']) . '</td>';
            echo '<td>' . number_format($overview['total_days']) . '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</div>';

            // Daily Activity Summary
            echo '<div class="section">';
            echo '<h2>Daily Activity Summary</h2>';
            $dailyActivities = $db->fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    category,
                    COUNT(*) as count
                FROM activity_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at), category
                ORDER BY date DESC, count DESC
            ", [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            echo '<table>';
            echo '<tr><th>Date</th><th>Category</th><th>Activities</th></tr>';
            foreach ($dailyActivities as $activity) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($activity['date']) . '</td>';
                echo '<td>' . htmlspecialchars($activity['category']) . '</td>';
                echo '<td>' . number_format($activity['count']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';

            // User Activity Summary
            echo '<div class="section">';
            echo '<h2>User Activity Summary</h2>';
            $userActivities = $db->fetchAll("
                SELECT 
                    u.name as user_name,
                    COUNT(*) as total_activities,
                    COUNT(DISTINCT DATE(al.created_at)) as active_days,
                    MIN(al.created_at) as first_activity,
                    MAX(al.created_at) as last_activity
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE al.created_at BETWEEN ? AND ?
                GROUP BY al.user_id, u.name
                ORDER BY total_activities DESC
            ", [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            echo '<table>';
            echo '<tr><th>User</th><th>Activities</th><th>Active Days</th><th>First Activity</th><th>Last Activity</th></tr>';
            foreach ($userActivities as $user) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['user_name']) . '</td>';
                echo '<td>' . number_format($user['total_activities']) . '</td>';
                echo '<td>' . number_format($user['active_days']) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($user['first_activity'])) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($user['last_activity'])) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';

            // Action Summary
            echo '<div class="section">';
            echo '<h2>Action Summary</h2>';
            $actionSummary = $db->fetchAll("
                SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    MIN(created_at) as first_occurrence,
                    MAX(created_at) as last_occurrence
                FROM activity_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY action
                ORDER BY count DESC
            ", [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            echo '<table>';
            echo '<tr><th>Action</th><th>Count</th><th>Unique Users</th><th>First Occurrence</th><th>Last Occurrence</th></tr>';
            foreach ($actionSummary as $action) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($action['action']) . '</td>';
                echo '<td>' . number_format($action['count']) . '</td>';
                echo '<td>' . number_format($action['unique_users']) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($action['first_occurrence'])) . '</td>';
                echo '<td>' . date('Y-m-d H:i', strtotime($action['last_occurrence'])) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';

            echo '</body>';
            echo '</html>';
            exit;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Send JSON response for non-export actions
if (!in_array($action, ['export', 'export_report'])) {
    header('Content-Type: application/json');
    echo json_encode($response);
} 