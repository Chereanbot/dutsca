<?php
// Include necessary files
require_once '../config/config.php';
require_once '../config/database.php';

// Verify teacher role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: /index.php');
    exit();
}

// Get user data
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Teacher';

try {
    $db = getDB();
    
    // Fetch account summary
    $accountQuery = "SELECT 
        COALESCE(SUM(amount), 0) as total_balance,
        COALESCE(SUM(CASE WHEN type = 'savings' THEN amount ELSE 0 END), 0) as total_savings,
        COUNT(DISTINCT CASE WHEN type = 'goal' AND status = 'active' THEN id END) as active_goals,
        COALESCE(SUM(CASE WHEN type = 'team' THEN amount ELSE 0 END), 0) as team_savings
    FROM transactions 
    WHERE user_id = ?";
    
    $accountStmt = $db->executeQuery($accountQuery, [$userId]);
    $accountData = $accountStmt->fetch();

    // Fetch recent transactions
    $transactionsQuery = "SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5";
    
    $transactionsStmt = $db->executeQuery($transactionsQuery, [$userId]);
    $recentTransactions = $transactionsStmt->fetchAll();

    // Fetch savings goals
    $goalsQuery = "SELECT * FROM savings_goals 
        WHERE user_id = ? AND status = 'active' 
        ORDER BY progress DESC 
        LIMIT 3";
    
    $goalsStmt = $db->executeQuery($goalsQuery, [$userId]);
    $savingsGoals = $goalsStmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    // Set default values in case of database error
    $accountData = [
        'total_balance' => 0,
        'total_savings' => 0,
        'active_goals' => 0,
        'team_savings' => 0
    ];
    $recentTransactions = [];
    $savingsGoals = [];
}

// Include header and sidebar
require_once 'include/header.php';
require_once 'include/sidebar.php';
?>

<main class="ml-0 md:ml-64 pt-16 min-h-screen bg-gray-50">
    <div class="p-6">
        <!-- Welcome Section -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center space-x-4">
                    <img src="/assets/images/default-avatar.png" alt="Profile" class="w-12 h-12 rounded-full">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Welcome back, <?php echo htmlspecialchars($userName); ?>!</h1>
                        <p class="text-gray-500">cherinet</p>
                    </div>
                </div>
            </div>
            <button class="bg-[#00572d] text-white px-6 py-2 rounded-lg hover:bg-[#1f9345] transition-colors flex items-center space-x-2">
                <i class="fas fa-bolt"></i>
                <span>Quick Actions</span>
            </button>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Today's Status -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center space-x-4">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-check text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Today's Status</p>
                        <p class="text-lg font-semibold text-gray-800">Present</p>
                    </div>
                </div>
            </div>

            <!-- This Week -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center space-x-4">
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-calendar text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">This Week</p>
                        <p class="text-lg font-semibold text-gray-800">40h</p>
                    </div>
                </div>
            </div>

            <!-- Next Shift -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center space-x-4">
                    <div class="bg-yellow-100 p-3 rounded-lg">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Next Shift</p>
                        <p class="text-lg font-semibold text-gray-800">Tomorrow, 8:00 AM</p>
                    </div>
                </div>
            </div>

            <!-- Leave Balance -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center space-x-4">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-calendar-alt text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Leave Balance</p>
                        <p class="text-lg font-semibold text-gray-800">12 days</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule & Attendance Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Upcoming Schedule -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">Upcoming Schedule</h2>
                        <a href="#" class="text-[#00572d] hover:text-[#1f9345] text-sm">View All</a>
                    </div>
                </div>
                <div class="p-6">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-4">Date</th>
                                <th class="pb-4">Shift</th>
                                <th class="pb-4">Time</th>
                                <th class="pb-4">Location</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <tr class="border-b border-gray-100">
                                <td class="py-4">Jan 16, 2024</td>
                                <td class="py-4">Morning Shift</td>
                                <td class="py-4">8:00 AM - 4:00 PM</td>
                                <td class="py-4">Main Campus</td>
                            </tr>
                            <tr>
                                <td class="py-4">Jan 17, 2024</td>
                                <td class="py-4">Afternoon Shift</td>
                                <td class="py-4">2:00 PM - 10:00 PM</td>
                                <td class="py-4">Library</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="bg-white rounded-xl shadow-sm">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Attendance</h2>
                        <a href="#" class="text-[#00572d] hover:text-[#1f9345] text-sm">View All</a>
                    </div>
                </div>
                <div class="p-6">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="pb-4">Date</th>
                                <th class="pb-4">Clock In</th>
                                <th class="pb-4">Clock Out</th>
                                <th class="pb-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <tr class="border-b border-gray-100">
                                <td class="py-4">Jan 15, 2024</td>
                                <td class="py-4">8:00 AM</td>
                                <td class="py-4">4:00 PM</td>
                                <td class="py-4">
                                    <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">Present</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-4">Jan 14, 2024</td>
                                <td class="py-4">8:15 AM</td>
                                <td class="py-4">4:00 PM</td>
                                <td class="py-4">
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm">Late</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<?php

?> 