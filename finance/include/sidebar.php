<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="fixed left-0 top-0 z-40 h-screen pt-16 transition-transform -translate-x-full bg-[#00572d] border-r border-gray-200 md:translate-x-0 w-64">
    <div class="h-full px-3 pb-4 overflow-y-auto">
        <div class="space-y-2 font-medium text-white">
            <div class="flex items-center p-2 mb-6">
                <img src="/assets/images/default-avatar.png" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <p class="text-sm font-semibold"><?php echo $_SESSION['name'] ?? 'Finance Head'; ?></p>
                    <p class="text-xs opacity-75">Financial Head</p>
                </div>
            </div>

            <nav>
                <ul class="space-y-1">
                    <li>
                        <a href="/finance/dashboard.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'dashboard.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-chart-line w-6"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="/finance/deposits.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'deposits.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-money-bill-wave w-6"></i>
                            <span>Manage Deposits</span>
                        </a>
                    </li>
                    <li>
                        <a href="/finance/withdrawals.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'withdrawals.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-money-bill-wave-alt w-6"></i>
                            <span>Manage Withdrawals</span>
                        </a>
                    </li>
                    <li>
                        <a href="/finance/transfers.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'transfers.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-exchange-alt w-6"></i>
                            <span>Money Transfers</span>
                        </a>
                    </li>
                    <li>
                        <a href="/finance/credit.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'credit.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-credit-card w-6"></i>
                            <span>Credit Requests</span>
                        </a>
                    </li>
                    <li>
                        <a href="/finance/reports.php" 
                           class="flex items-center p-2 rounded-lg hover:bg-[#1f9345] <?php echo $currentPage == 'reports.php' ? 'bg-[#1f9345]' : ''; ?>">
                            <i class="fas fa-file-invoice w-6"></i>
                            <span>Financial Reports</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="pt-8">
                <a href="/logout.php" class="flex items-center p-2 text-white hover:bg-[#1f9345] rounded-lg">
                    <i class="fas fa-sign-out-alt w-6"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</aside>

<div class="md:ml-64">
    <!-- Main content goes here -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuButton = document.querySelector('[data-drawer-toggle="sidebar"]');
    const sidebar = document.querySelector('aside');
    
    menuButton?.addEventListener('click', function() {
        sidebar.classList.toggle('-translate-x-full');
    });
});
</script>
