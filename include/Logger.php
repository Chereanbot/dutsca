<?php
class Logger {
    private static $instance = null;
    private $db;
    private $activityTypes = [
        'auth' => ['login', 'logout', 'password_reset', 'profile_update'],
        'data' => ['create', 'update', 'delete', 'import', 'export'],
        'system' => ['backup', 'maintenance', 'error', 'warning'],
        'user' => ['page_view', 'download', 'upload', 'search']
    ];

    private function __construct() {
        $this->db = getDB();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log user activity
     * 
     * @param string $category The activity category (auth, data, system, user)
     * @param string $action The specific action performed
     * @param string $message Description of the activity
     * @param array $additionalData Optional additional data
     * @return bool Whether the activity was successfully logged
     */
    public function log($category, $action, $message, $additionalData = null) {
        try {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

            $query = "
                INSERT INTO activity_logs 
                (user_id, category, action, message, ip_address, additional_data, created_at)
                VALUES (:user_id, :category, :action, :message, :ip_address, :additional_data, NOW())
            ";

            $params = [
                'user_id' => $userId,
                'category' => $category,
                'action' => $action,
                'message' => $message,
                'ip_address' => $ipAddress,
                'additional_data' => $additionalData ? json_encode($additionalData) : null
            ];

            return $this->db->executeQuery($query, $params)->rowCount() > 0;
        } catch (Exception $e) {
            error_log('Logger Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log an informational message
     * 
     * @param string $action The action performed
     * @param string $message The log message
     * @param array $additionalData Optional additional data to store
     * @return bool Whether the log was successfully created
     */
    public function info($action, $message, $additionalData = null) {
        return $this->log('info', $action, $message, $additionalData);
    }

    /**
     * Log a success message
     * 
     * @param string $action The action performed
     * @param string $message The log message
     * @param array $additionalData Optional additional data to store
     * @return bool Whether the log was successfully created
     */
    public function success($action, $message, $additionalData = null) {
        return $this->log('success', $action, $message, $additionalData);
    }

    /**
     * Log a warning message
     * 
     * @param string $action The action performed
     * @param string $message The log message
     * @param array $additionalData Optional additional data to store
     * @return bool Whether the log was successfully created
     */
    public function warning($action, $message, $additionalData = null) {
        return $this->log('warning', $action, $message, $additionalData);
    }

    /**
     * Log an error message
     * 
     * @param string $action The action performed
     * @param string $message The log message
     * @param array $additionalData Optional additional data to store
     * @return bool Whether the log was successfully created
     */
    public function error($action, $message, $additionalData = null) {
        return $this->log('error', $action, $message, $additionalData);
    }

    /**
     * Get system statistics
     */
    public function getSystemStats() {
        try {
            $query = "
                SELECT
                    (SELECT COUNT(*) FROM users) as total_users,
                    (SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at) = CURDATE()) as active_users_today,
                    (SELECT COUNT(*) FROM activity_logs) as total_activities,
                    (SELECT COUNT(*) FROM activity_logs WHERE category = 'error') as total_errors
            ";
            return $this->db->fetchOne($query) ?? [
                'total_users' => 0,
                'active_users_today' => 0,
                'total_activities' => 0,
                'total_errors' => 0
            ];
        } catch (Exception $e) {
            error_log('Logger Stats Error: ' . $e->getMessage());
            return [
                'total_users' => 0,
                'active_users_today' => 0,
                'total_activities' => 0,
                'total_errors' => 0
            ];
        }
    }

    /**
     * Get activity summary by category
     */
    public function getActivitySummary($startDate = null, $endDate = null) {
        try {
            $query = "
                SELECT 
                    category,
                    action,
                    COUNT(*) as count
                FROM activity_logs
                WHERE 1=1
            ";
            $params = [];

            if ($startDate) {
                $query .= " AND created_at >= ?";
                $params[] = $startDate . ' 00:00:00';
            }
            if ($endDate) {
                $query .= " AND created_at <= ?";
                $params[] = $endDate . ' 23:59:59';
            }

            $query .= " GROUP BY category, action ORDER BY count DESC";

            return $this->db->fetchAll($query, $params) ?? [];
        } catch (Exception $e) {
            error_log('Logger Summary Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean old logs based on retention period
     * 
     * @param int $days Number of days to keep logs
     * @return bool Whether the cleanup was successful
     */
    public function cleanupOldLogs($days = 30) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            // Create archive directory if it doesn't exist
            $archiveDir = __DIR__ . '/../archives/logs';
            if (!file_exists($archiveDir)) {
                mkdir($archiveDir, 0777, true);
            }

            // Get logs to archive
            $query = "SELECT * FROM activity_logs WHERE created_at < ?";
            $oldLogs = $this->db->fetchAll($query, [$cutoffDate]);

            if (!empty($oldLogs)) {
                // Archive logs
                $archiveFile = $archiveDir . '/logs_' . date('Y-m-d_His') . '.json';
                file_put_contents($archiveFile, json_encode($oldLogs, JSON_PRETTY_PRINT));

                // Delete archived logs
                $deleteQuery = "DELETE FROM activity_logs WHERE created_at < ?";
                $this->db->execute($deleteQuery, [$cutoffDate]);

                // Log the cleanup
                $this->info(
                    'cleanup',
                    sprintf('Archived and deleted %d logs older than %s', count($oldLogs), $cutoffDate),
                    ['archive_file' => basename($archiveFile)]
                );
            }

            return true;
        } catch (Exception $e) {
            error_log('Logger Cleanup Error: ' . $e->getMessage());
            return false;
        }
    }
}
?> 