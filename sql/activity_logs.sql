-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    category VARCHAR(50) NOT NULL,
    action VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_category ON activity_logs(category);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Create view for daily activity summary
CREATE OR REPLACE VIEW v_daily_activity_summary AS
SELECT 
    DATE(created_at) as activity_date,
    category,
    COUNT(*) as activity_count,
    COUNT(DISTINCT user_id) as unique_users
FROM activity_logs
GROUP BY DATE(created_at), category
ORDER BY activity_date DESC, activity_count DESC;

-- Create view for user activity summary
CREATE OR REPLACE VIEW v_user_activity_summary AS
SELECT 
    al.user_id,
    u.name as user_name,
    COUNT(*) as total_activities,
    COUNT(DISTINCT DATE(al.created_at)) as active_days,
    MAX(al.created_at) as last_activity
FROM activity_logs al
LEFT JOIN users u ON al.user_id = u.id
GROUP BY al.user_id, u.name
ORDER BY total_activities DESC;

-- Create stored procedure for cleaning old logs
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS sp_cleanup_old_logs(IN retention_days INT)
BEGIN
    DELETE FROM activity_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY);
END //
DELIMITER ;

-- Create event to automatically clean old logs weekly
CREATE EVENT IF NOT EXISTS evt_cleanup_old_logs
ON SCHEDULE EVERY 1 WEEK
DO CALL sp_cleanup_old_logs(30); 