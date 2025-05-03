<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'finance') {
    header('Location: /dutsca/index.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';
require_once __DIR__ . '/include/sidebar.php';
require_once __DIR__ . '/include/header.php';


$db = getDB();
$success = $error = '';
$user_id = $_SESSION['user_id'];

// Handle password change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    $user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$user_id]);
    
    if (!password_verify($currentPassword, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->executeQuery("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $user_id]);
            $success = 'Password updated successfully!';
            
            // Log the activity
            $logger = Logger::getInstance();
            $logger->log('auth', 'password_changed', 'User changed their password');
        } catch (Exception $e) {
            $error = 'Error updating password: ' . $e->getMessage();
        }
    }
}

// Handle form submission for settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_password'])) {
    try {
        $db->beginTransaction();

        // Update financial settings
        if (isset($_POST['financial_settings'])) {
            $financialSettings = [
                'min_savings_amount' => floatval($_POST['min_savings_amount']),
                'max_withdrawal_amount' => floatval($_POST['max_withdrawal_amount']),
                'monthly_contribution_min' => floatval($_POST['monthly_contribution_min']),
                'credit_score_threshold' => intval($_POST['credit_score_threshold']),
                'interest_rate' => floatval($_POST['interest_rate']),
                'late_payment_fee' => floatval($_POST['late_payment_fee']),
                'processing_fee' => floatval($_POST['processing_fee'])
            ];

            foreach ($financialSettings as $key => $value) {
                $db->executeQuery(
                    "INSERT INTO settings (name, value, description) 
                     VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE value = ?",
                    [$key, $value, ucwords(str_replace('_', ' ', $key)), $value]
                );
            }
        }

        // Update notification settings
        if (isset($_POST['notification_settings'])) {
            $notificationSettings = [
                'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? '1' : '0',
                'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? '1' : '0',
                'notify_on_deposit' => isset($_POST['notify_on_deposit']) ? '1' : '0',
                'notify_on_withdrawal' => isset($_POST['notify_on_withdrawal']) ? '1' : '0',
                'notify_on_low_balance' => isset($_POST['notify_on_low_balance']) ? '1' : '0'
            ];

            foreach ($notificationSettings as $key => $value) {
                $db->executeQuery(
                    "INSERT INTO settings (name, value, description) 
                     VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE value = ?",
                    [$key, $value, ucwords(str_replace('_', ' ', $key)), $value]
                );
            }
        }

        $db->commit();
        $success = 'Settings updated successfully!';
        
        // Log the activity
        $logger = Logger::getInstance();
        $logger->log('system', 'settings_updated', 'System settings were updated');
        
    } catch (Exception $e) {
        $db->rollback();
        $error = 'Error updating settings: ' . $e->getMessage();
    }
}

// Fetch current settings
$settings = [];
$result = $db->fetchAll("SELECT name, value FROM settings");
foreach ($result as $row) {
    $settings[$row['name']] = $row['value'];
}

// Fetch saving settings (single row)
$savingSettings = $db->fetchOne("SELECT * FROM saving_settings ORDER BY id DESC LIMIT 1");
if (!$savingSettings) {
    $savingSettings = [
        'min_savings_amount' => 100,
        'max_savings_amount' => 10000,
        'interest_rate' => 5,
        'dividend_rate' => 2,
        'contribution_frequency' => 'monthly',
        'penalty_missed_contribution' => 50,
        'withdrawal_limit' => 5000,
        'withdrawal_notice_days' => 7,
        'currency' => 'ETB'
    ];
}

// Fetch loan terms (single row)
$loanTerms = $db->fetchOne("SELECT * FROM loan_terms ORDER BY id DESC LIMIT 1");
if (!$loanTerms) {
    $loanTerms = [
        'eligibility_min_months' => 12,
        'eligibility_min_savings' => 5000,
        'min_loan_amount' => 1000,
        'max_loan_amount' => 100000,
        'interest_rate' => 10,
        'repayment_period_months' => 12,
        'grace_period_days' => 7,
        'late_payment_fee' => 100,
        'early_repayment_allowed' => 1,
        'early_repayment_discount' => 2,
        'default_penalty' => 500,
        'currency' => 'ETB'
    ];
}

// Handle saving settings update
if (isset($_POST['update_saving_settings'])) {
    try {
        $db->beginTransaction();

        // Validate inputs
        $minSavings = floatval($_POST['min_savings_amount']);
        $maxSavings = floatval($_POST['max_savings_amount']);
        
        if ($minSavings > $maxSavings) {
            throw new Exception('Minimum savings amount cannot be greater than maximum savings amount');
        }

        // Build SQL query
        $sql = "INSERT INTO saving_settings (
            min_savings_amount, max_savings_amount, interest_rate,
            dividend_rate, contribution_frequency, penalty_missed_contribution,
            withdrawal_limit, withdrawal_notice_days, currency,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        // Execute query with parameters array
        $result = $db->executeQuery($sql, [
            $minSavings,
            $maxSavings,
            floatval($_POST['interest_rate']),
            floatval($_POST['dividend_rate']),
            $_POST['contribution_frequency'],
            floatval($_POST['penalty_missed_contribution']),
            floatval($_POST['withdrawal_limit']),
            intval($_POST['withdrawal_notice_days']),
            'ETB'
        ]);

        // Log the activity
        $newSettings = [
            'min_savings_amount' => $minSavings,
            'max_savings_amount' => $maxSavings,
            'interest_rate' => floatval($_POST['interest_rate']),
            'dividend_rate' => floatval($_POST['dividend_rate']),
            'contribution_frequency' => $_POST['contribution_frequency'],
            'penalty_missed_contribution' => floatval($_POST['penalty_missed_contribution']),
            'withdrawal_limit' => floatval($_POST['withdrawal_limit']),
            'withdrawal_notice_days' => intval($_POST['withdrawal_notice_days']),
            'currency' => 'ETB'
        ];

        Logger::getInstance()->log('system', 'saving_settings_updated', 'Savings settings were updated', [
            'old_settings' => $savingSettings,
            'new_settings' => $newSettings
        ]);

        // Commit transaction
        $db->commit();

        // Refresh settings
        $savingSettings = $db->fetchOne("SELECT * FROM saving_settings ORDER BY id DESC LIMIT 1");
        $success = 'Savings settings updated successfully!';

    } catch (PDOException $e) {
        $db->rollback();
        $error = 'Database error: ' . $e->getMessage();
        error_log('Savings Settings Update Error: ' . $e->getMessage());
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
        error_log('Savings Settings Validation Error: ' . $e->getMessage());
    }
}

// Handle loan terms update
if (isset($_POST['update_loan_terms'])) {
    try {
        $db->beginTransaction();

        // Validate inputs
        $minLoan = floatval($_POST['min_loan_amount']);
        $maxLoan = floatval($_POST['max_loan_amount']);
        $minSavings = floatval($_POST['eligibility_min_savings']);
        
        // Validation checks
        if ($minLoan > $maxLoan) {
            throw new Exception('Minimum loan amount cannot be greater than maximum loan amount');
        }

        // Build SQL query
        $sql = "INSERT INTO loan_terms (
            eligibility_min_months, eligibility_min_savings,
            min_loan_amount, max_loan_amount, interest_rate,
            repayment_period_months, grace_period_days, late_payment_fee,
            early_repayment_allowed, early_repayment_discount,
            default_penalty, currency,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        // Prepare parameters
        $params = [
            intval($_POST['eligibility_min_months']),
            $minSavings,
            $minLoan,
            $maxLoan,
            floatval($_POST['interest_rate_loan']),
            intval($_POST['repayment_period_months']),
            intval($_POST['grace_period_days']),
            floatval($_POST['late_payment_fee']),
            isset($_POST['early_repayment_allowed']) ? 1 : 0,
            floatval($_POST['early_repayment_discount']),
            floatval($_POST['default_penalty']),
            'ETB'
        ];

        // Execute query
        $db->executeQuery($sql, $params);

        // Log the activity with old and new values
        $newTerms = [
            'eligibility_min_months' => intval($_POST['eligibility_min_months']),
            'eligibility_min_savings' => $minSavings,
            'min_loan_amount' => $minLoan,
            'max_loan_amount' => $maxLoan,
            'interest_rate' => floatval($_POST['interest_rate_loan']),
            'repayment_period_months' => intval($_POST['repayment_period_months']),
            'grace_period_days' => intval($_POST['grace_period_days']),
            'late_payment_fee' => floatval($_POST['late_payment_fee']),
            'early_repayment_allowed' => isset($_POST['early_repayment_allowed']) ? 1 : 0,
            'early_repayment_discount' => floatval($_POST['early_repayment_discount']),
            'default_penalty' => floatval($_POST['default_penalty']),
            'currency' => 'ETB'
        ];

        Logger::getInstance()->log('system', 'loan_terms_updated', 'Loan terms were updated', [
            'old_terms' => $loanTerms,
            'new_terms' => $newTerms
        ]);

        // Commit transaction
        $db->commit();

        // Refresh loan terms
        $loanTerms = $db->fetchOne("SELECT * FROM loan_terms ORDER BY id DESC LIMIT 1");
        $success = 'Loan terms updated successfully!';

    } catch (PDOException $e) {
        $db->rollback();
        $error = 'Database error: ' . $e->getMessage();
        error_log('Loan Terms Update Error: ' . $e->getMessage());
    } catch (Exception $e) {
        $db->rollback();
        $error = $e->getMessage();
        error_log('Loan Terms Validation Error: ' . $e->getMessage());
    }
}

// Fetch login devices
$loginDevices = $db->fetchAll("
    SELECT ip_address, user_agent, attempted_at, success 
    FROM login_attempts 
    WHERE user_id = ? 
    ORDER BY attempted_at DESC 
    LIMIT 10
", [$user_id]);

// Default values if not set
$defaults = [
    'min_savings_amount' => 100,
    'max_withdrawal_amount' => 10000,
    'monthly_contribution_min' => 1000,
    'credit_score_threshold' => 650,
    'interest_rate' => 5,
    'late_payment_fee' => 50,
    'processing_fee' => 100,
    'enable_email_notifications' => '1',
    'enable_sms_notifications' => '0',
    'notify_on_deposit' => '1',
    'notify_on_withdrawal' => '1',
    'notify_on_low_balance' => '1'
];

foreach ($defaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: #f4f4f4; 
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .main-content {
            margin-left: 250px; /* Adjust based on your sidebar width */
            padding: 20px;
            padding-top: 70px; /* Height of header */
        }
        .settings-card { 
            background: #fff; 
            border-radius: 16px; 
            box-shadow: 0 4px 24px #0002; 
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            transition: transform 0.2s;
        }
        .settings-card:hover {
            transform: translateY(-5px);
        }
        .card-title {
            color: #00572d;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-label {
            color: #00572d;
            font-weight: 500;
        }
        .input-group-text {
            background: #f3c300;
            color: #00572d;
            border: none;
        }
        .btn-primary {
            background: #00572d;
            border: none;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s;
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background: #1f9345;
            transform: translateY(-2px);
        }
        .form-switch .form-check-input:checked {
            background-color: #00572d;
            border-color: #00572d;
        }
        .form-switch .form-check-input:focus {
            border-color: #1f9345;
            box-shadow: 0 0 0 0.25rem rgba(0, 87, 45, 0.25);
        }
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(255,255,255,0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        .spinner-border { color: #00572d; }
        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1055;
        }
        .device-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .device-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .device-item:hover {
            background-color: #f8f9fa;
        }
        .device-item i {
            font-size: 1.5rem;
            color: #00572d;
        }
        .device-item.current {
            background-color: #e8f5e9;
        }
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 1.5rem;
        }
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }
        .nav-tabs .nav-link:hover {
            border-color: #1f9345;
            color: #1f9345;
        }
        .nav-tabs .nav-link.active {
            color: #00572d;
            border-color: #00572d;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #1f9345;
            box-shadow: 0 0 0 0.25rem rgba(0, 87, 45, 0.25);
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .settings-nav {
            position: sticky;
            top: 90px;
            z-index: 1000;
            background: #fff;
            padding: 1rem 0;
            border-radius: 16px;
            box-shadow: 0 2px 12px #0001;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner-border" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>
<div class="toast-container" id="toastContainer"></div>

<div class="main-content">
    <div class="settings-nav">
        <ul class="nav nav-tabs nav-fill">
            <li class="nav-item">
                <a class="nav-link active" href="#financialSettings" data-bs-toggle="tab">
                    <i class="fas fa-money-bill-wave me-2"></i>Financial
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#savingsSettings" data-bs-toggle="tab">
                    <i class="fas fa-piggy-bank me-2"></i>Savings Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#loanTerms" data-bs-toggle="tab">
                    <i class="fas fa-file-contract me-2"></i>Loan Terms
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#notificationSettings" data-bs-toggle="tab">
                    <i class="fas fa-bell me-2"></i>Notifications
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#securitySettings" data-bs-toggle="tab">
                    <i class="fas fa-shield-alt me-2"></i>Security
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#loginDevices" data-bs-toggle="tab">
                    <i class="fas fa-mobile-alt me-2"></i>Devices
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        <!-- Financial Settings Tab -->
        <div class="tab-pane fade show active" id="financialSettings">
            <div class="settings-card">
                <h3 class="card-title">
                    <i class="fas fa-money-bill-wave"></i>
                    Financial Settings
                </h3>
                <form method="post" id="financialSettingsForm">
                    <input type="hidden" name="financial_settings" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Minimum Savings Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₦</span>
                                <input type="number" name="min_savings_amount" class="form-control" 
                                       value="<?= htmlspecialchars($settings['min_savings_amount']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Maximum Withdrawal Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₦</span>
                                <input type="number" name="max_withdrawal_amount" class="form-control" 
                                       value="<?= htmlspecialchars($settings['max_withdrawal_amount']) ?>" 
                                       min="0" step="1000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Monthly Contribution</label>
                            <div class="input-group">
                                <span class="input-group-text">₦</span>
                                <input type="number" name="monthly_contribution_min" class="form-control" 
                                       value="<?= htmlspecialchars($settings['monthly_contribution_min']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Score Threshold</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-star"></i></span>
                                <input type="number" name="credit_score_threshold" class="form-control" 
                                       value="<?= htmlspecialchars($settings['credit_score_threshold']) ?>" 
                                       min="0" max="1000" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interest Rate (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                <input type="number" name="interest_rate" class="form-control" 
                                       value="<?= htmlspecialchars($settings['interest_rate']) ?>" 
                                       min="0" max="100" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Late Payment Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₦</span>
                                <input type="number" name="late_payment_fee" class="form-control" 
                                       value="<?= htmlspecialchars($settings['late_payment_fee']) ?>" 
                                       min="0" step="10" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Processing Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₦</span>
                                <input type="number" name="processing_fee" class="form-control" 
                                       value="<?= htmlspecialchars($settings['processing_fee']) ?>" 
                                       min="0" step="10" required>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Financial Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Savings Settings Tab -->
        <div class="tab-pane fade" id="savingsSettings">
            <div class="settings-card">
                <h3 class="card-title">
                    <i class="fas fa-piggy-bank"></i>
                    Savings Settings
                </h3>
                <form method="post" id="savingsSettingsForm">
                    <input type="hidden" name="update_saving_settings" value="1">
                    <div class="row g-3">
                        <!-- Basic Settings -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Basic Settings</h5>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Savings Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="min_savings_amount" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['min_savings_amount']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Savings Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="max_savings_amount" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['max_savings_amount']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contribution Frequency</label>
                            <select name="contribution_frequency" class="form-select" required>
                                <option value="daily" <?= $savingSettings['contribution_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= $savingSettings['contribution_frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= $savingSettings['contribution_frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            </select>
                        </div>

                        <!-- Interest and Dividends -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Interest & Dividends</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interest Rate (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                <input type="number" name="interest_rate" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['interest_rate']) ?>" 
                                       min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dividend Rate (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                <input type="number" name="dividend_rate" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['dividend_rate']) ?>" 
                                       min="0" step="0.01" required>
                            </div>
                        </div>

                        <!-- Withdrawal Rules -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Withdrawal Rules</h5>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Withdrawal Limit</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="withdrawal_limit" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['withdrawal_limit']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notice Period (Days)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="number" name="withdrawal_notice_days" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['withdrawal_notice_days']) ?>" 
                                       min="0" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Missed Contribution Penalty</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="penalty_missed_contribution" class="form-control" 
                                       value="<?= htmlspecialchars($savingSettings['penalty_missed_contribution']) ?>" 
                                       min="0" step="10" required>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Savings Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loan Terms Tab -->
        <div class="tab-pane fade" id="loanTerms">
            <div class="settings-card">
                <h3 class="card-title">
                    <i class="fas fa-file-contract"></i>
                    Loan Terms
                </h3>
                <form method="post" id="loanTermsForm">
                    <input type="hidden" name="update_loan_terms" value="1">
                    <div class="row g-3">
                        <!-- Eligibility Requirements -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Eligibility Requirements</h5>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Membership (Months)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                <input type="number" name="eligibility_min_months" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['eligibility_min_months']) ?>" 
                                       min="1" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Minimum Savings Required</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="eligibility_min_savings" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['eligibility_min_savings']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>

                        <!-- Loan Amount and Interest -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Loan Amount & Interest</h5>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Loan Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="min_loan_amount" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['min_loan_amount']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Maximum Loan Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="max_loan_amount" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['max_loan_amount']) ?>" 
                                       min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Interest Rate (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                <input type="number" name="interest_rate_loan" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['interest_rate']) ?>" 
                                       min="0" step="0.01" required>
                            </div>
                        </div>

                        <!-- Repayment Terms -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Repayment Terms</h5>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Repayment Period (Months)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="number" name="repayment_period_months" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['repayment_period_months']) ?>" 
                                       min="1" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Grace Period (Days)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="number" name="grace_period_days" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['grace_period_days']) ?>" 
                                       min="0" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Late Payment Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="late_payment_fee" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['late_payment_fee']) ?>" 
                                       min="0" step="10" required>
                            </div>
                        </div>

                        <!-- Early Repayment -->
                        <div class="col-12">
                            <h5 class="text-success mb-3">Early Repayment</h5>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="early_repayment_allowed" 
                                       id="earlyRepaymentAllowed" <?= $loanTerms['early_repayment_allowed'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="earlyRepaymentAllowed">Allow Early Repayment</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Early Repayment Discount (%)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percent"></i></span>
                                <input type="number" name="early_repayment_discount" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['early_repayment_discount']) ?>" 
                                       min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default Penalty</label>
                            <div class="input-group">
                                <span class="input-group-text">ETB</span>
                                <input type="number" name="default_penalty" class="form-control" 
                                       value="<?= htmlspecialchars($loanTerms['default_penalty']) ?>" 
                                       min="0" step="10" required>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Loan Terms
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notification Settings Tab -->
        <div class="tab-pane fade" id="notificationSettings">
            <div class="settings-card">
                <h3 class="card-title">
                    <i class="fas fa-bell"></i>
                    Notification Settings
                </h3>
                <form method="post" id="notificationSettingsForm">
                    <input type="hidden" name="notification_settings" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enable_email_notifications" 
                                       id="enableEmailNotifications" <?= $settings['enable_email_notifications'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enableEmailNotifications">
                                    Enable Email Notifications
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="enable_sms_notifications" 
                                       id="enableSmsNotifications" <?= $settings['enable_sms_notifications'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enableSmsNotifications">
                                    Enable SMS Notifications
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notify_on_deposit" 
                                       id="notifyOnDeposit" <?= $settings['notify_on_deposit'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyOnDeposit">
                                    Notify on Deposit
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notify_on_withdrawal" 
                                       id="notifyOnWithdrawal" <?= $settings['notify_on_withdrawal'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyOnWithdrawal">
                                    Notify on Withdrawal
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notify_on_low_balance" 
                                       id="notifyOnLowBalance" <?= $settings['notify_on_low_balance'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="notifyOnLowBalance">
                                    Notify on Low Balance
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Save Notification Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Settings Tab -->
        <div class="tab-pane fade" id="securitySettings">
            <div class="settings-card">
                <h3 class="card-title">
                    <i class="fas fa-shield-alt"></i>
                    Change Password
                </h3>
                <form method="post" id="passwordChangeForm">
                    <input type="hidden" name="change_password" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="current_password" class="form-control" required>
                                <span class="password-toggle" onclick="togglePassword(this)">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="new_password" class="form-control" 
                                       pattern=".{8,}" title="Password must be at least 8 characters long" required>
                                <span class="password-toggle" onclick="togglePassword(this)">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="confirm_password" class="form-control" required>
                                <span class="password-toggle" onclick="togglePassword(this)">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Login Devices Tab -->
        <div class="tab-pane fade" id="loginDevices">
            <div class="settings-card">
                <h3 class="card-title">
                    <i class="fas fa-mobile-alt"></i>
                    Recent Login Devices
                </h3>
                <div class="device-list">
                    <?php foreach ($loginDevices as $device): 
                        $userAgent = get_browser_name($device['user_agent']);
                        $isCurrentDevice = $device['ip_address'] === $_SERVER['REMOTE_ADDR'];
                    ?>
                    <div class="device-item <?= $isCurrentDevice ? 'current' : '' ?>">
                        <div class="d-flex align-items-center">
                            <i class="<?= get_device_icon($device['user_agent']) ?> me-3"></i>
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($userAgent) ?></h6>
                                <small class="text-muted">
                                    IP: <?= htmlspecialchars($device['ip_address']) ?>
                                    <?= $isCurrentDevice ? ' (Current Device)' : '' ?>
                                </small><br>
                                <small class="text-muted">
                                    Last activity: <?= date('M d, Y H:i', strtotime($device['attempted_at'])) ?>
                                </small>
                            </div>
                            <div class="ms-auto">
                                <span class="badge bg-<?= $device['success'] ? 'success' : 'danger' ?>">
                                    <?= $device['success'] ? 'Success' : 'Failed' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show loading spinner on form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        document.getElementById('loadingSpinner').style.display = 'flex';
    });
});

// Toast notification function
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 mb-2 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    toastContainer.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4000);
}

// Toggle password visibility
function togglePassword(element) {
    const input = element.parentElement.querySelector('input');
    const icon = element.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Show PHP feedback as toast
<?php if ($success): ?>showToast("<?= addslashes($success) ?>", 'success');<?php endif; ?>
<?php if ($error): ?>showToast("<?= addslashes($error) ?>", 'danger');<?php endif; ?>

// Helper function to get browser name
<?php
function get_browser_name($user_agent) {
    if (strpos($user_agent, 'Firefox')) return 'Mozilla Firefox';
    if (strpos($user_agent, 'Chrome')) return 'Google Chrome';
    if (strpos($user_agent, 'Safari')) return 'Safari';
    if (strpos($user_agent, 'Edge')) return 'Microsoft Edge';
    if (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/')) return 'Internet Explorer';
    return 'Unknown Browser';
}

function get_device_icon($user_agent) {
    if (strpos(strtolower($user_agent), 'mobile')) return 'fas fa-mobile-alt';
    if (strpos(strtolower($user_agent), 'tablet')) return 'fas fa-tablet-alt';
    return 'fas fa-laptop';
}
?>
</script>
</body>
</html> 