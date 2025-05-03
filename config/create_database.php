<?php
try {
    // First connect without database selected
    $pdo = new PDO(
        "mysql:host=localhost",
        "root",
        "",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS dutsca CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Select the database
    $pdo->exec("USE dutsca");

    // Create blocked_ips table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blocked_ips (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            reason TEXT,
            blocked_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create security_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create login_attempts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            username VARCHAR(255),
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN DEFAULT 0,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Create settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            value TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_setting (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert default settings
    $defaultSettings = [
        ['ethiopia_only_access', '0', 'Allow only Ethiopian IP addresses'],
        ['min_password_length', '8', 'Minimum password length'],
        ['require_special_chars', '1', 'Require special characters in password'],
        ['require_numbers', '1', 'Require numbers in password'],
        ['require_uppercase', '1', 'Require uppercase letters in password'],
        ['password_expiry_days', '90', 'Password expiry in days'],
        ['max_login_attempts', '5', 'Maximum login attempts before lockout'],
        ['lockout_duration_minutes', '30', 'Account lockout duration in minutes'],
        ['session_timeout_minutes', '30', 'Session timeout in minutes'],
        ['require_2fa', '0', 'Require two-factor authentication']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO settings (name, value, description)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }

    echo "Database and tables created successfully!";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
} 