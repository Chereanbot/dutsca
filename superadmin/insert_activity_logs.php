<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';
require_once __DIR__ . '/include/sidebar.php';
require_once __DIR__ . '/include/header.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

$db = getDB();
$success = false;
$error = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create temporary function for random dates
        $db->execute("
            DROP FUNCTION IF EXISTS random_date;
            DELIMITER //
            CREATE FUNCTION random_date(start_date TIMESTAMP, end_date TIMESTAMP)
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
        ");

        // Insert authentication activities
        $insertQuery = "
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
            ) users_actions
        ";

        $db->execute($insertQuery);
        
        // Drop the temporary function
        $db->execute("DROP FUNCTION IF EXISTS random_date");

        $success = true;
        $message = 'Test activity logs have been successfully inserted.';
    } catch (Exception $e) {
        $error = 'Error inserting activity logs: ' . $e->getMessage();
        error_log('Activity Log Insert Error: ' . $e->getMessage());
    }
}
?>

<style>
.main-content {
    margin-left: 256px;
    padding: 2rem;
    background: #f8f9fa;
    min-height: 100vh;
}

.card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.card-header {
    background-color: #00572d;
    color: white;
    border-radius: 15px 15px 0 0;
    padding: 1rem;
}

.card-body {
    padding: 2rem;
}

.btn-primary {
    background-color: #00572d;
    border-color: #00572d;
}

.btn-primary:hover {
    background-color: #1f9345;
    border-color: #1f9345;
}

.alert-success {
    color: #0f5132;
    background-color: #d1e7dd;
    border-color: #badbcc;
}

.alert-danger {
    color: #842029;
    background-color: #f8d7da;
    border-color: #f5c2c7;
}

.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.icon-circle.success {
    background-color: #d1e7dd;
    color: #0f5132;
}

.icon-circle.error {
    background-color: #f8d7da;
    color: #842029;
}
</style>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1" style="color: #00572d;">Insert Test Activity Logs</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: #1f9345;">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="activity_reports.php" style="color: #1f9345;">Activity Reports</a></li>
                    <li class="breadcrumb-item active">Insert Test Data</li>
                </ol>
            </nav>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center" role="alert">
        <div class="icon-circle success">
            <i class="fas fa-check"></i>
        </div>
        <div>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center" role="alert">
        <div class="icon-circle error">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <?php echo htmlspecialchars($error); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Insert Test Activity Logs</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">
                This tool will insert test activity logs for demonstration purposes. The logs will include:
            </p>
            <ul class="mb-4">
                <li>Authentication activities (login/logout)</li>
                <li>Random IP addresses and user agents</li>
                <li>Timestamps within the last 30 days</li>
                <li>Various user activities across different categories</li>
            </ul>

            <form method="post" class="mt-4">
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i>Insert Test Data
                    </button>
                    <a href="activity_reports.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Next Steps</h5>
        </div>
        <div class="card-body">
            <p>Now that the test data has been inserted, you can:</p>
            <div class="row">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title" style="color: #00572d;">
                                <i class="fas fa-chart-line me-2"></i>View Reports
                            </h5>
                            <p class="card-text">Analyze the activity data through various charts and statistics.</p>
                            <a href="activity_reports.php" class="btn btn-primary">Go to Reports</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title" style="color: #00572d;">
                                <i class="fas fa-download me-2"></i>Export Data
                            </h5>
                            <p class="card-text">Export the activity logs for further analysis or backup.</p>
                            <a href="activity_actions.php?action=export_report" class="btn btn-primary">Export Logs</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title" style="color: #00572d;">
                                <i class="fas fa-cog me-2"></i>Configure Settings
                            </h5>
                            <p class="card-text">Adjust the logging system settings and parameters.</p>
                            <a href="settings.php" class="btn btn-primary">Settings</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../include/footer.php'; ?> 