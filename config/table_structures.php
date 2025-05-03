<?php
require_once 'config.php';
require_once 'database.php';

function createTables() {
    try {
        // First, create database without transaction
        $pdo = new PDO("mysql:host=localhost", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS dutsca");
        $pdo = null;

        // Connect to the specific database
        $pdo = new PDO("mysql:host=localhost;dbname=dutsca", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Drop existing tables in reverse order of dependencies
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = [
            'iqub_payments',
            'iqub_winners',
            'iqub_members',
            'iqub_rounds',
            'iqub_groups',
            'user_activity_logs',
            'user_permissions',
            'departments',
            'sessions',
            'savings',
            'savings_goals',
            'notifications',
            'system_settings',
            'users'
        ];
        
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS $table");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Start transaction for table creation
        $pdo->beginTransaction();

        try {
            // 1. Create Users Table with enhanced fields
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    email VARCHAR(100) UNIQUE NOT NULL,
                    role ENUM('superadmin', 'chairman', 'manager', 'finance', 'teacher', 'servant') NOT NULL,
                    status ENUM('pending', 'active', 'inactive', 'suspended') DEFAULT 'pending',
                    name VARCHAR(100) NOT NULL,
                    contact_number VARCHAR(20),
                    department VARCHAR(100),
                    employee_id VARCHAR(50) UNIQUE,
                    position VARCHAR(100),
                    date_joined DATE,
                    
                    -- Account Information
                    account_number VARCHAR(20) UNIQUE,
                    bank_name VARCHAR(100),
                    bank_branch VARCHAR(100),
                    
                    -- Membership Information
                    membership_number VARCHAR(50) UNIQUE,
                    membership_date DATE,
                    credit_eligible BOOLEAN DEFAULT FALSE,
                    credit_score INT DEFAULT 0,
                    monthly_contribution DECIMAL(10,2) DEFAULT 0.00,
                    total_contribution DECIMAL(10,2) DEFAULT 0.00,
                    available_balance DECIMAL(10,2) DEFAULT 0.00,
                    
                    -- System Fields
                    profile_image VARCHAR(255),
                    last_login TIMESTAMP NULL,
                    login_attempts INT DEFAULT 0,
                    password_reset_token VARCHAR(100),
                    password_reset_expires TIMESTAMP NULL,
                    email_verified BOOLEAN DEFAULT FALSE,
                    email_verification_token VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    approved_at TIMESTAMP NULL,
                    approved_by INT,
                    INDEX idx_role (role),
                    INDEX idx_status (status),
                    INDEX idx_department (department),
                    INDEX idx_employee_id (employee_id),
                    INDEX idx_account_number (account_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Add foreign key after table creation
            $pdo->exec("
                ALTER TABLE users 
                ADD FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
            ");

            // 2. Create User Permissions Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    permission_name VARCHAR(100) NOT NULL,
                    granted_by INT,
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_user_permission (user_id, permission_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 3. Create Departments Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS departments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    description TEXT,
                    head_id INT,
                    contact_email VARCHAR(255),
                    contact_phone VARCHAR(50),
                    location VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    FOREIGN KEY (head_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 4. Create User Activity Logs Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_activity_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    action VARCHAR(50) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Add after the user_activity_logs table creation
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    value TEXT,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insert default superadmin user
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, password, email, role, status, 
                    name, email_verified,
                    membership_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt->execute([
                'admin',
                $adminPassword,
                'admin@dutsca.com',
                'superadmin',
                'active',
                'System Administrator',
                true,
                'SA001'
            ]);

            // Insert default users
            $commonPassword = password_hash('cherean123', PASSWORD_DEFAULT);
            
            $defaultUsers = [
                // Chairman
                [
                    'username' => 'cherinet',
                    'password' => $commonPassword,
                    'email' => 'cherinet@dutsca.com',
                    'role' => 'chairman',
                    'status' => 'active',
                    'name' => 'Cherinet Aweke',
                    'contact_number' => '+251911223344',
                    'department' => 'Administration',
                    'employee_id' => 'EMP001',
                    'position' => 'Department Chairman',
                    'date_joined' => '2024-01-01',
                    'account_number' => 'ACC001',
                    'bank_name' => 'Commercial Bank of Ethiopia',
                    'bank_branch' => 'Addis Ababa',
                    'membership_number' => 'MEM001',
                    'membership_date' => '2024-01-01',
                    'credit_eligible' => true,
                    'monthly_contribution' => 1000.00,
                    'email_verified' => true
                ],
                
                // Manager
                [
                    'username' => 'cherean',
                    'password' => $commonPassword,
                    'email' => 'cherean@dutsca.com',
                    'role' => 'manager',
                    'status' => 'active',
                    'name' => 'Cherean Solomon',
                    'contact_number' => '+251922334455',
                    'department' => 'Management',
                    'employee_id' => 'EMP002',
                    'position' => 'General Manager',
                    'date_joined' => '2024-01-01',
                    'account_number' => 'ACC002',
                    'bank_name' => 'Dashen Bank',
                    'bank_branch' => 'Addis Ababa',
                    'membership_number' => 'MEM002',
                    'membership_date' => '2024-01-01',
                    'credit_eligible' => true,
                    'monthly_contribution' => 1000.00,
                    'email_verified' => true
                ],
                
                // Finance
                [
                    'username' => 'efi',
                    'password' => $commonPassword,
                    'email' => 'efi@dutsca.com',
                    'role' => 'finance',
                    'status' => 'active',
                    'name' => 'Efrem Tadesse',
                    'contact_number' => '+251933445566',
                    'department' => 'Finance',
                    'employee_id' => 'EMP003',
                    'position' => 'Finance Head',
                    'date_joined' => '2024-01-01',
                    'account_number' => 'ACC003',
                    'bank_name' => 'Awash Bank',
                    'bank_branch' => 'Addis Ababa',
                    'membership_number' => 'MEM003',
                    'membership_date' => '2024-01-01',
                    'credit_eligible' => true,
                    'monthly_contribution' => 1000.00,
                    'email_verified' => true
                ],
                
                // Teacher
                [
                    'username' => 'du',
                    'password' => $commonPassword,
                    'email' => 'du@dutsca.com',
                    'role' => 'teacher',
                    'status' => 'active',
                    'name' => 'Dureti Ahmed',
                    'contact_number' => '+251944556677',
                    'department' => 'Teaching',
                    'employee_id' => 'EMP004',
                    'position' => 'Senior Teacher',
                    'date_joined' => '2024-01-01',
                    'account_number' => 'ACC004',
                    'bank_name' => 'United Bank',
                    'bank_branch' => 'Addis Ababa',
                    'membership_number' => 'MEM004',
                    'membership_date' => '2024-01-01',
                    'credit_eligible' => true,
                    'monthly_contribution' => 1000.00,
                    'email_verified' => true
                ],
                // Superadmin (cherinetadmin@gmail.com)
                [
                    'username' => 'cherinetadmin',
                    'password' => $commonPassword,
                    'email' => 'cherinetadmin@gmail.com',
                    'role' => 'superadmin',
                    'status' => 'active',
                    'name' => 'Cherinet Admin',
                    'contact_number' => '+251900000001',
                    'department' => 'Administration',
                    'employee_id' => 'EMP005',
                    'position' => 'System Superadmin',
                    'date_joined' => '2024-01-01',
                    'account_number' => 'ACC005',
                    'bank_name' => 'Commercial Bank of Ethiopia',
                    'bank_branch' => 'Addis Ababa',
                    'membership_number' => 'MEM005',
                    'membership_date' => '2024-01-01',
                    'credit_eligible' => true,
                    'monthly_contribution' => 1000.00,
                    'email_verified' => true
                ],
                // Finance (cherinetfinance@gmail.com)
                [
                    'username' => 'cherinetfinance',
                    'password' => $commonPassword,
                    'email' => 'cherinetfinance@gmail.com',
                    'role' => 'finance',
                    'status' => 'active',
                    'name' => 'Cherinet Finance',
                    'contact_number' => '+251900000002',
                    'department' => 'Finance',
                    'employee_id' => 'EMP006',
                    'position' => 'Finance Officer',
                    'date_joined' => '2024-01-01',
                    'account_number' => 'ACC006',
                    'bank_name' => 'Awash Bank',
                    'bank_branch' => 'Addis Ababa',
                    'membership_number' => 'MEM006',
                    'membership_date' => '2024-01-01',
                    'credit_eligible' => true,
                    'monthly_contribution' => 1000.00,
                    'email_verified' => true
                ]
            ];

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, password, email, role, status,
                    name, contact_number, department, employee_id,
                    position, date_joined, account_number,
                    bank_name, bank_branch, membership_number,
                    membership_date, credit_eligible,
                    monthly_contribution, email_verified
                ) VALUES (
                    :username, :password, :email, :role, :status,
                    :name, :contact_number, :department, :employee_id,
                    :position, :date_joined, :account_number,
                    :bank_name, :bank_branch, :membership_number,
                    :membership_date, :credit_eligible,
                    :monthly_contribution, :email_verified
                )
            ");

            foreach ($defaultUsers as $user) {
                $stmt->execute($user);
                
                // Create an activity log entry for each user creation
                $userId = $pdo->lastInsertId();
                $pdo->exec("
                    INSERT INTO user_activity_logs (
                        user_id, action, description
                    ) VALUES (
                        $userId,
                        'account_created',
                        'User account created with role: {$user['role']}'
                    )
                ");
            }

            echo "<div class='mt-4 p-4 bg-green-100 text-green-700 rounded'>";
            echo "<h3 class='font-bold'>Default Users Created:</h3>";
            echo "<ul class='list-disc pl-5'>";
            echo "<li>Chairman: cherinet@dutsca.com</li>";
            echo "<li>Manager: cherean@dutsca.com</li>";
            echo "<li>Finance: efi@dutsca.com</li>";
            echo "<li>Teacher: du@dutsca.com</li>";
            echo "</ul>";
            echo "<p class='mt-2'><strong>Common Password:</strong> cherean123</p>";
            echo "</div>";

            // 2. Create Sessions Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    id VARCHAR(128) PRIMARY KEY,
                    user_id INT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    payload TEXT,
                    last_activity INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 3. Create Savings Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS savings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    type ENUM('deposit', 'withdrawal') NOT NULL,
                    description TEXT,
                    reference_number VARCHAR(50) UNIQUE,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    approved_by INT,
                    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 4. Create Savings Goals Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS savings_goals (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    title VARCHAR(100) NOT NULL,
                    target_amount DECIMAL(10,2) NOT NULL,
                    current_amount DECIMAL(10,2) DEFAULT 0.00,
                    deadline DATE,
                    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 5. Create Notifications Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 6. Create System Settings Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(50) UNIQUE NOT NULL,
                    setting_value TEXT NOT NULL,
                    description TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // 7. Create Activity Logs Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS activity_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    action VARCHAR(50) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insert default system settings
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
                VALUES (?, ?, ?)
            ");
            
            $defaultSettings = [
                ['site_name', 'DUTSCA', 'Site Name'],
                ['site_description', 'DUTSCA Savings and Credit Association', 'Site Description'],
                ['maintenance_mode', 'false', 'Maintenance Mode Status'],
                ['min_savings_amount', '100', 'Minimum Savings Amount'],
                ['max_withdrawal_amount', '10000', 'Maximum Withdrawal Amount']
            ];

            foreach ($defaultSettings as $setting) {
                $stmt->execute($setting);
            }

            // Create IQUB Groups Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS iqub_groups (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    contribution_amount DECIMAL(10,2) NOT NULL,
                    total_members INT NOT NULL,
                    current_members INT DEFAULT 0,
                    start_date DATE,
                    end_date DATE,
                    cycle_period ENUM('weekly', 'monthly') DEFAULT 'monthly',
                    status ENUM('forming', 'active', 'completed', 'cancelled') DEFAULT 'forming',
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create IQUB Members Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS iqub_members (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    join_date DATE NOT NULL,
                    position_number INT,
                    total_contribution DECIMAL(10,2) DEFAULT 0.00,
                    status ENUM('active', 'won', 'completed', 'defaulted') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES iqub_groups(id) ON DELETE RESTRICT,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                    UNIQUE KEY unique_member_group (group_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create IQUB Rounds Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS iqub_rounds (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    group_id INT NOT NULL,
                    round_number INT NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    winner_id INT,
                    pot_amount DECIMAL(10,2) DEFAULT 0.00,
                    status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (group_id) REFERENCES iqub_groups(id) ON DELETE RESTRICT,
                    FOREIGN KEY (winner_id) REFERENCES iqub_members(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_group_round (group_id, round_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create IQUB Payments Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS iqub_payments (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    member_id INT NOT NULL,
                    round_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    payment_date DATE NOT NULL,
                    payment_method ENUM('cash', 'bank_transfer', 'deduction') NOT NULL,
                    reference_number VARCHAR(50),
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    approved_by INT,
                    approved_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (member_id) REFERENCES iqub_members(id) ON DELETE RESTRICT,
                    FOREIGN KEY (round_id) REFERENCES iqub_rounds(id) ON DELETE RESTRICT,
                    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create Backup Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS backups (
                    id VARCHAR(50) PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    size INT NOT NULL,
                    status ENUM('pending', 'completed', 'failed', 'deleted') DEFAULT 'pending',
                    description TEXT,
                    encrypted BOOLEAN DEFAULT FALSE,
                    compressed BOOLEAN DEFAULT FALSE,
                    verify_result TEXT,
                    verify_at TIMESTAMP NULL,
                    created_by INT,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create System Status Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_status (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    status_type VARCHAR(100) NOT NULL,
                    value VARCHAR(255),
                    description TEXT,
                    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    checked_by INT,
                    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create Performance Monitoring Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS performance_monitoring (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    metric VARCHAR(100) NOT NULL,
                    value VARCHAR(100) NOT NULL,
                    unit VARCHAR(20),
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    recorded_by INT,
                    notes TEXT,
                    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create System Update Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_updates (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    version VARCHAR(20) NOT NULL,
                    update_type ENUM('security', 'feature', 'bugfix', 'maintenance') NOT NULL,
                    description TEXT,
                    status ENUM('pending', 'applied', 'failed', 'rolled_back') DEFAULT 'pending',
                    applied_at TIMESTAMP NULL,
                    applied_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (applied_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Enhance Security Setting Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS security_settings (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT NOT NULL,
                    description TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    updated_by INT,
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insert default security settings
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO security_settings (setting_key, setting_value, description)
                VALUES (?, ?, ?)
            ");
            $defaultSecuritySettings = [
                ['password_min_length', '8', 'Minimum password length'],
                ['password_require_special', 'true', 'Require special characters in password'],
                ['password_require_uppercase', 'true', 'Require uppercase letters in password'],
                ['password_require_number', 'true', 'Require numbers in password'],
                ['session_timeout_minutes', '30', 'Session timeout in minutes'],
                ['require_2fa', 'false', 'Require two-factor authentication for all users'],
                ['max_login_attempts', '5', 'Maximum allowed failed login attempts before lockout'],
                ['account_lockout_duration_minutes', '15', 'Duration (in minutes) for which account is locked after max failed attempts'],
                ['password_expiry_days', '90', 'Number of days before password must be changed'],
                ['allow_password_reuse', 'false', 'Allow users to reuse previous passwords']
            ];
            foreach ($defaultSecuritySettings as $setting) {
                $stmt->execute($setting);
            }

            // Create System Report Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS system_reports (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    report_type VARCHAR(100) NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    generated_by INT,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
                    notes TEXT,
                    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create User Report Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_reports (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    report_type VARCHAR(100) NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    generated_by INT,
                    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
                    notes TEXT,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insert default settings
            $stmt = $pdo->prepare("
                INSERT INTO settings (name, value, description) VALUES 
                ('backup_config', ?, 'Backup system configuration'),
                ('system_version', '1.0.0', 'Current system version')
            ");

            $backupConfig = json_encode([
                'frequency' => 'daily',
                'retention' => 30,
                'compression' => 'gzip',
                'encryption' => true
            ]);

            $stmt->execute([$backupConfig]);

            // Commit the transaction
            $pdo->commit();

            echo "<div style='color: green; font-family: Arial, sans-serif; padding: 20px;'>";
            echo "<h2>✅ Database Tables Created Successfully!</h2>";
            echo "<p>The following tables have been created:</p>";
            echo "<ul>";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            echo "</ul>";
            echo "<p><strong>Default Superadmin Credentials:</strong></p>";
            echo "<ul>";
            echo "<li>Username: admin</li>";
            echo "<li>Password: admin123</li>";
            echo "</ul>";
            echo "</div>";

            // Create Permission Categories Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS permission_categories (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    description TEXT,
                    icon VARCHAR(50),
                    display_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Create Permissions Table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    display_name VARCHAR(100) NOT NULL,
                    description TEXT,
                    category_id INT,
                    icon VARCHAR(50),
                    is_active BOOLEAN DEFAULT TRUE,
                    requires_approval BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES permission_categories(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Drop and recreate User Permissions Table with reference to permissions table
            $pdo->exec("DROP TABLE IF EXISTS user_permissions");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_permissions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    permission_id INT NOT NULL,
                    granted_by INT,
                    status ENUM('pending', 'active', 'revoked') DEFAULT 'active',
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
                    UNIQUE KEY unique_user_permission (user_id, permission_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insert default permission categories
            $pdo->exec("
                INSERT INTO permission_categories (name, description, icon, display_order) VALUES
                ('User Management', 'Permissions related to user management', 'fa-users', 1),
                ('Department Management', 'Permissions related to department management', 'fa-building', 2),
                ('Finance', 'Permissions related to financial operations', 'fa-money-bill', 3),
                ('Reports', 'Permissions related to reports and analytics', 'fa-chart-bar', 4),
                ('System', 'System-level permissions', 'fa-cogs', 5)
            ");

            // Insert default permissions
            $pdo->exec("
                INSERT INTO permissions (name, display_name, description, category_id, icon) VALUES
                ('manage_users', 'Manage Users', 'Create, edit, and delete user accounts', 1, 'fa-user-cog'),
                ('view_users', 'View Users', 'View user list and details', 1, 'fa-users'),
                ('manage_departments', 'Manage Departments', 'Create, edit, and delete departments', 2, 'fa-building'),
                ('view_departments', 'View Departments', 'View department list and details', 2, 'fa-building'),
                ('manage_finances', 'Manage Finances', 'Handle financial transactions', 3, 'fa-money-check'),
                ('view_finances', 'View Finances', 'View financial records', 3, 'fa-money-bill'),
                ('generate_reports', 'Generate Reports', 'Generate system reports', 4, 'fa-file-alt'),
                ('view_reports', 'View Reports', 'View system reports', 4, 'fa-chart-line'),
                ('manage_settings', 'Manage Settings', 'Modify system settings', 5, 'fa-cogs'),
                ('manage_permissions', 'Manage Permissions', 'Create and assign permissions', 5, 'fa-key'),
                ('manage_backups', 'Manage Backups', 'Create and restore system backups', 5, 'fa-database')
            ");

            // Grant all permissions to superadmin
            $pdo->exec("
                INSERT INTO user_permissions (user_id, permission_id, granted_by, status)
                SELECT 1, id, 1, 'active'
                FROM permissions
            ");

            // Create security_logs table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS security_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    action VARCHAR(50) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Create blocked_ips table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS blocked_ips (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    ip_address VARCHAR(45) NOT NULL,
                    reason TEXT,
                    blocked_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Create login_attempts table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT,
                    username VARCHAR(255) NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    success BOOLEAN DEFAULT FALSE,
                    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Insert default settings
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO settings (name, value, description) VALUES
                ('min_password_length', '8', 'Minimum password length'),
                ('require_special_chars', '1', 'Require special characters in password'),
                ('require_numbers', '1', 'Require numbers in password'),
                ('require_uppercase', '1', 'Require uppercase letters in password'),
                ('password_expiry_days', '90', 'Password expiry in days'),
                ('max_login_attempts', '5', 'Maximum login attempts before lockout'),
                ('lockout_duration_minutes', '30', 'Account lockout duration in minutes'),
                ('session_timeout_minutes', '30', 'Session timeout in minutes'),
                ('require_2fa', '0', 'Require two-factor authentication'),
                ('ip_whitelist', '', 'Whitelisted IP addresses')
            ");

            $stmt->execute();

            // Create department_members table for tracking department members
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS department_members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    department_id INT NOT NULL,
                    user_id INT NOT NULL,
                    role VARCHAR(50) DEFAULT 'member',
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_member (department_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

    } catch (Exception $e) {
        echo "<div style='color: red; font-family: Arial, sans-serif; padding: 20px;'>";
        echo "<h2>❌ Error Creating Tables!</h2>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Debug Information:</strong></p>";
        echo "<pre>";
        print_r($e);
        echo "</pre>";
        echo "</div>";
    }
}

// Execute table creation
createTables();
?>
