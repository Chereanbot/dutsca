-- Insert test users if they don't exist
INSERT IGNORE INTO users (id, name, email, role, password) VALUES
(1, 'Admin User', 'admin@example.com', 'superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Regular User', 'user@example.com', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3, 'Test User', 'test@example.com', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Function to generate random dates between two dates
DELIMITER //
CREATE FUNCTION IF NOT EXISTS random_date(start_date TIMESTAMP, end_date TIMESTAMP)
RETURNS TIMESTAMP
BEGIN
    RETURN FROM_UNIXTIME(
        UNIX_TIMESTAMP(start_date) + FLOOR(
            RAND() * (
                UNIX_TIMESTAMP(end_date) - UNIX_TIMESTAMP(start_date)
            )
        )
    );
END //
DELIMITER ;

-- Insert authentication activities
INSERT INTO activity_logs (user_id, category, action, message, ip_address, additional_data, created_at)
SELECT 
    user_id,
    'auth',
    CASE WHEN RAND() < 0.8 THEN 'login' ELSE 'logout' END,
    CASE 
        WHEN action = 'login' THEN 'User logged in successfully'
        ELSE 'User logged out'
    END,
    CONCAT('192.168.', FLOOR(RAND()*256), '.', FLOOR(RAND()*256)),
    JSON_OBJECT(
        'user_agent', ELT(FLOOR(RAND()*4)+1,
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1)',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B)'
        ),
        'success', true
    ),
    random_date(DATE_SUB(NOW(), INTERVAL 30 DAY), NOW())
FROM (
    SELECT id as user_id, 'login' as action
    FROM users
    CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n1
    CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3) n2
) users_actions;

-- Insert data manipulation activities
INSERT INTO activity_logs (user_id, category, action, message, ip_address, additional_data, created_at)
SELECT 
    user_id,
    'data',
    action,
    CONCAT('User ', action, ' a record'),
    CONCAT('192.168.', FLOOR(RAND()*256), '.', FLOOR(RAND()*256)),
    JSON_OBJECT(
        'user_agent', ELT(FLOOR(RAND()*4)+1,
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1)',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B)'
        ),
        'record_id', FLOOR(RAND()*1000),
        'table', ELT(FLOOR(RAND()*3)+1, 'users', 'products', 'orders')
    ),
    random_date(DATE_SUB(NOW(), INTERVAL 30 DAY), NOW())
FROM (
    SELECT id as user_id, action
    FROM users
    CROSS JOIN (
        SELECT 'created' as action UNION
        SELECT 'updated' UNION
        SELECT 'deleted'
    ) actions
    CROSS JOIN (SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) n
) users_actions;

-- Insert system activities
INSERT INTO activity_logs (user_id, category, action, message, ip_address, additional_data, created_at)
SELECT 
    CASE WHEN RAND() < 0.3 THEN NULL ELSE user_id END,
    'system',
    action,
    CASE 
        WHEN action = 'backup' THEN 'System backup completed'
        WHEN action = 'maintenance' THEN 'System maintenance performed'
        ELSE 'System update installed'
    END,
    CONCAT('192.168.', FLOOR(RAND()*256), '.', FLOOR(RAND()*256)),
    JSON_OBJECT(
        'user_agent', 'System',
        'status', 'success',
        'details', CASE 
            WHEN action = 'backup' THEN 'Full database backup'
            WHEN action = 'maintenance' THEN 'Cache cleared and optimized'
            ELSE 'Security patches applied'
        END
    ),
    random_date(DATE_SUB(NOW(), INTERVAL 30 DAY), NOW())
FROM (
    SELECT id as user_id, action
    FROM users
    CROSS JOIN (
        SELECT 'backup' as action UNION
        SELECT 'maintenance' UNION
        SELECT 'update'
    ) actions
) users_actions;

-- Insert error activities
INSERT INTO activity_logs (user_id, category, action, message, ip_address, additional_data, created_at)
SELECT 
    CASE WHEN RAND() < 0.5 THEN NULL ELSE user_id END,
    'error',
    'error',
    ELT(FLOOR(RAND()*3)+1,
        'Database connection failed',
        'Invalid input parameters',
        'API request timeout'
    ),
    CONCAT('192.168.', FLOOR(RAND()*256), '.', FLOOR(RAND()*256)),
    JSON_OBJECT(
        'user_agent', ELT(FLOOR(RAND()*4)+1,
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1)',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B)'
        ),
        'error_code', FLOOR(RAND()*500),
        'stack_trace', 'Error details would be here'
    ),
    random_date(DATE_SUB(NOW(), INTERVAL 30 DAY), NOW())
FROM (
    SELECT id as user_id
    FROM users
    CROSS JOIN (SELECT 1 UNION SELECT 2) n
) users;

-- Drop the temporary function
DROP FUNCTION IF EXISTS random_date; 