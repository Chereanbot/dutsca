<?php
// SQL schema for transactions and related tables
// Save this file and run the SQL statements in your database

$sql = <<<SQL
-- Users table (if not already exists)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    department VARCHAR(100),
    balance DECIMAL(15,2) DEFAULT 0,
    credit_balance DECIMAL(15,2) DEFAULT 0,
    membership_date DATE,
    role VARCHAR(50) DEFAULT 'member',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit','withdrawal','transfer','credit','fee','adjustment') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    reference VARCHAR(64),
    description VARCHAR(255),
    related_user_id INT DEFAULT NULL, -- for transfers (recipient)
    processed_by INT DEFAULT NULL, -- admin/financial head who processed
    processed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Credit Requests table
CREATE TABLE IF NOT EXISTS credit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    purpose VARCHAR(255),
    duration INT DEFAULT 12, -- months
    monthly_payment DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    processed_by INT DEFAULT NULL,
    processed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Transaction Audit Log (optional, for tracking changes)
CREATE TABLE IF NOT EXISTS transaction_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    action ENUM('created','updated','approved','rejected','cancelled') NOT NULL,
    performed_by INT NOT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    details TEXT,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
);
SQL;

echo "<pre>" . htmlspecialchars($sql) . "</pre>";

// Example INSERT statements for transactions
$insert_sql = <<<SQL
-- Example data for users (if needed)
INSERT INTO users (id, name, email, department, balance, credit_balance, membership_date, role, status) VALUES
(1, 'Alice Johnson', 'alice@example.com', 'Finance', 50000, 0, '2022-01-10', 'member', 'active'),
(2, 'Bob Smith', 'bob@example.com', 'IT', 30000, 0, '2021-11-15', 'member', 'active'),
(3, 'Carol Lee', 'carol@example.com', 'HR', 20000, 5000, '2020-06-01', 'member', 'active'),
(4, 'David Kim', 'david@example.com', 'Finance', 10000, 0, '2023-02-20', 'member', 'active');

-- Example data for transactions
INSERT INTO transactions (user_id, type, amount, status, reference, description, related_user_id, processed_by, processed_at, created_at) VALUES
(1, 'deposit', 10000, 'approved', 'DEP001', 'Initial deposit', NULL, 10, NOW(), NOW()),
(2, 'deposit', 15000, 'approved', 'DEP002', 'Monthly savings', NULL, 10, NOW(), NOW()),
(3, 'withdrawal', 5000, 'approved', 'WDR001', 'Emergency withdrawal', NULL, 10, NOW(), NOW()),
(1, 'transfer', 2000, 'approved', 'TRF001', 'Transfer to Bob', 2, 10, NOW(), NOW()),
(2, 'transfer', 2000, 'approved', 'TRF002', 'Received from Alice', 1, 10, NOW(), NOW()),
(3, 'deposit', 7000, 'pending', 'DEP003', 'Pending deposit', NULL, NULL, NULL, NOW()),
(4, 'withdrawal', 3000, 'pending', 'WDR002', 'Pending withdrawal', NULL, NULL, NULL, NOW()),
(3, 'credit', 5000, 'approved', 'CRD001', 'Credit approved', NULL, 10, NOW(), NOW()),
(1, 'fee', 500, 'approved', 'FEE001', 'Service fee', NULL, 10, NOW(), NOW()),
(2, 'adjustment', 1000, 'approved', 'ADJ001', 'Balance adjustment', NULL, 10, NOW(), NOW());
SQL;

echo "<h3>Example INSERT statements:</h3><pre>" . htmlspecialchars($insert_sql) . "</pre>";
