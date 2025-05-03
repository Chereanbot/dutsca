<?php
// config/tables.php
require_once __DIR__ . '/database.php';

$db = getDB();

function createTable($db, $sql, $tableName) {
    try {
        $db->executeQuery($sql);
        echo "<p style='color:green;'>Table <b>$tableName</b> created or already exists.</p>";
    } catch (Exception $e) {
        echo "<p style='color:red;'>Error creating <b>$tableName</b>: " . $e->getMessage() . "</p>";
    }
}

// Table for saving settings (expanded)
$savingSettingsSQL = "
CREATE TABLE IF NOT EXISTS saving_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_savings_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_savings_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    dividend_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    contribution_frequency VARCHAR(32) NOT NULL DEFAULT 'monthly',
    penalty_missed_contribution DECIMAL(12,2) NOT NULL DEFAULT 0,
    withdrawal_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
    withdrawal_notice_days INT NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'ETB',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
createTable($db, $savingSettingsSQL, 'saving_settings');

// Table for loan terms (expanded)
$loanTermsSQL = "
CREATE TABLE IF NOT EXISTS loan_terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    eligibility_min_months INT NOT NULL DEFAULT 12,
    eligibility_min_savings DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    repayment_period_months INT NOT NULL DEFAULT 12,
    grace_period_days INT NOT NULL DEFAULT 0,
    late_payment_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
    early_repayment_allowed TINYINT(1) NOT NULL DEFAULT 1,
    early_repayment_discount DECIMAL(5,2) NOT NULL DEFAULT 0,
    default_penalty DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'ETB',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
createTable($db, $loanTermsSQL, 'loan_terms');

// Optionally, add more fields based on the full terms document if needed.

echo "<p style='color:blue;'>All required tables checked/created. You can now safely close this page.</p>"; 