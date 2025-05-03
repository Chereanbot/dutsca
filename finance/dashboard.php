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

try {
    $db = getDB();
    $logger = Logger::getInstance();

    // Initialize statistics
    $stats = [
        'total_deposits' => 0,
        'total_withdrawals' => 0,
        'pending_deposits' => 0,
        'pending_withdrawals' => 0,
        'pending_credits' => 0,
        'total_transfers' => 0,
        'total_balance' => 0
    ];

    // Get today's transaction statistics
    $todayStats = $db->fetchOne("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as deposits,
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as withdrawals,
            COALESCE(COUNT(CASE WHEN type = 'deposit' AND status = 'pending' THEN 1 END), 0) as pending_deposits,
            COALESCE(COUNT(CASE WHEN type = 'withdrawal' AND status = 'pending' THEN 1 END), 0) as pending_withdrawals
        FROM transactions 
        WHERE DATE(created_at) = CURDATE()
    ");

    // Get pending credit requests
    $pendingCredits = $db->fetchAll("
        SELECT cr.*, u.name, u.email, u.department
        FROM credit_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.status = 'pending'
        ORDER BY cr.created_at DESC
        LIMIT 5
    ");

    // Get recent transactions
    $recentTransactions = $db->fetchAll("
        SELECT t.*, u.name as user_name, u.department
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");

    // Get monthly transaction summary
    $monthlyStats = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as deposits,
            SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as withdrawals,
            COUNT(DISTINCT user_id) as active_users
        FROM transactions
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");

    // Get department-wise summary
    $departmentStats = $db->fetchAll("
        SELECT 
            u.department,
            COUNT(DISTINCT u.id) as total_users,
            COALESCE(SUM(t.amount), 0) as total_savings
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id AND t.type = 'deposit' AND t.status = 'approved'
        GROUP BY u.department
        ORDER BY total_savings DESC
    ");

} catch (Exception $e) {
    error_log('Finance Dashboard Error: ' . $e->getMessage());
}
?>

<style>
.dashboard-container {
    margin-left: 256px;
    padding: 2rem;
    background: #f4f4f4;
    min-height: 100vh;
}

.stats-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-card .icon {
    width: 48px;
    height: 48px;
    background-color: #00572d;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.transaction-item {
    padding: 1rem;
    border-left: 4px solid #00572d;
    margin-bottom: 1rem;
    background: white;
    border-radius: 0 8px 8px 0;
    transition: all 0.2s;
}

.transaction-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.credit-request-card {
    background: white;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid #f3c300;
    transition: transform 0.2s;
}

.credit-request-card:hover {
    transform: translateX(5px);
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 15px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-pending {
    background-color: rgba(243, 195, 0, 0.1);
    color: #f3c300;
}

.status-approved {
    background-color: rgba(31, 147, 69, 0.1);
    color: #1f9345;
}

.status-rejected {
    background-color: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}
</style>

<div class="dashboard-container">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 style="color: #00572d;">Financial Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Financial Head'); ?></p>
        </div>
        <div>
            <a href="reports.php" class="btn btn-primary me-2" style="background-color: #00572d; border-color: #00572d;">
                <i class="fas fa-file-alt me-2"></i>Generate Reports
            </a>
            <a href="transactions.php" class="btn btn-outline-primary" style="color: #00572d; border-color: #00572d;">
                <i class="fas fa-list me-2"></i>View All Transactions
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1">₦<?php echo number_format($todayStats['deposits'], 2); ?></h3>
                        <p class="text-muted mb-0">Today's Deposits</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-success">
                        <i class="fas fa-circle me-1"></i>
                        <?php echo $todayStats['pending_deposits']; ?> pending
                    </span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1">₦<?php echo number_format($todayStats['withdrawals'], 2); ?></h3>
                        <p class="text-muted mb-0">Today's Withdrawals</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-warning">
                        <i class="fas fa-circle me-1"></i>
                        <?php echo $todayStats['pending_withdrawals']; ?> pending
                    </span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1"><?php echo count($pendingCredits); ?></h3>
                        <p class="text-muted mb-0">Pending Credits</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="credit_requests.php" class="text-primary">View all requests</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stats-card h-100 p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="mb-1">₦<?php echo number_format($stats['total_balance'], 2); ?></h3>
                        <p class="text-muted mb-0">Total Balance</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted">Updated just now</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Monthly Transaction Overview</h5>
                </div>
                <div class="card-body">
                    <canvas id="transactionTrend" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Department Summary</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($departmentStats as $dept): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?php echo htmlspecialchars($dept['department']); ?></strong>
                            <span class="text-success">₦<?php echo number_format($dept['total_savings'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted"><?php echo $dept['total_users']; ?> members</small>
                            <small class="text-muted">Avg: ₦<?php 
                                echo $dept['total_users'] > 0 
                                    ? number_format($dept['total_savings'] / $dept['total_users'], 2) 
                                    : '0.00'; 
                            ?></small>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: <?php 
                                $maxSavings = max(array_column($departmentStats, 'total_savings'));
                                echo $maxSavings > 0 ? ($dept['total_savings'] / $maxSavings * 100) : 0;
                            ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Actions and Recent Transactions -->
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Pending Credit Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingCredits)): ?>
                        <p class="text-muted text-center mb-0">No pending credit requests</p>
                    <?php else: ?>
                        <?php foreach ($pendingCredits as $credit): ?>
                        <div class="credit-request-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($credit['name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($credit['department']); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="h5 mb-0">₦<?php echo number_format($credit['amount'], 2); ?></div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($credit['created_at'])); ?></small>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-success" onclick="processCredit(<?php echo $credit['id']; ?>, 'approve')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="processCredit(<?php echo $credit['id']; ?>, 'reject')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <button class="btn btn-sm btn-info" onclick="viewCreditDetails(<?php echo $credit['id']; ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-4">
            <div class="card">
                <div class="card-header" style="background-color: #00572d; color: white;">
                    <h5 class="mb-0">Recent Transactions</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($recentTransactions as $transaction): ?>
                    <div class="transaction-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($transaction['user_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($transaction['department']); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="h5 mb-0 <?php echo $transaction['type'] === 'deposit' ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $transaction['type'] === 'deposit' ? '+' : '-'; ?>₦<?php echo number_format($transaction['amount'], 2); ?>
                                </div>
                                <small class="text-muted"><?php echo date('M d, H:i', strtotime($transaction['created_at'])); ?></small>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                            <?php if ($transaction['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-success ms-2" onclick="processTransaction(<?php echo $transaction['id']; ?>, 'approve')">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger ms-1" onclick="processTransaction(<?php echo $transaction['id']; ?>, 'reject')">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Transaction Trend Chart
    const monthlyData = <?php echo json_encode($monthlyStats); ?>;
    const ctx = document.getElementById('transactionTrend').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(item => {
                const [year, month] = item.month.split('-');
                return new Date(year, month - 1).toLocaleDateString('default', { month: 'short', year: 'numeric' });
            }),
            datasets: [{
                label: 'Deposits',
                data: monthlyData.map(item => item.deposits),
                backgroundColor: 'rgba(31, 147, 69, 0.5)',
                borderColor: '#1f9345',
                borderWidth: 1
            }, {
                label: 'Withdrawals',
                data: monthlyData.map(item => item.withdrawals),
                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                borderColor: '#dc3545',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});

function processTransaction(id, action) {
    if (confirm(`Are you sure you want to ${action} this transaction?`)) {
        fetch('ajax/process_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                transaction_id: id,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || `Failed to ${action} transaction`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(`Failed to ${action} transaction`);
        });
    }
}

function processCredit(id, action) {
    if (confirm(`Are you sure you want to ${action} this credit request?`)) {
        fetch('ajax/process_credit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                credit_id: id,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || `Failed to ${action} credit request`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(`Failed to ${action} credit request`);
        });
    }
}

function viewCreditDetails(id) {
    window.location.href = `credit_details.php?id=${id}`;
}
</script>

<?php include '../include/footer.php'; ?> 