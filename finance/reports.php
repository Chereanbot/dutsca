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
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($type) {
    $where[] = 't.type = ?';
    $params[] = $type;
}
if ($status) {
    $where[] = 't.status = ?';
    $params[] = $status;
}
if ($start_date) {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $start_date;
}
if ($end_date) {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $end_date;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Summary stats
$summary = $db->fetchOne("
    SELECT 
        SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as total_credits,
        SUM(CASE WHEN type='transfer' THEN amount ELSE 0 END) as total_transfers,
        COUNT(*) as total_transactions
    FROM transactions t $whereSql
", $params);

// Monthly trend for chart
$monthly = $db->fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as deposits,
        SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as withdrawals,
        SUM(CASE WHEN type='credit' THEN amount ELSE 0 END) as credits
    FROM transactions t $whereSql
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", $params);

// Category breakdown for chart
$categories = $db->fetchAll("
    SELECT type, SUM(amount) as total
    FROM transactions t $whereSql
    GROUP BY type
", $params);

// Get paginated transactions
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM transactions t $whereSql", $params)['cnt'] ?? 0;
$transactions = $db->fetchAll("
    SELECT t.*, u.name as user_name, u.department
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    $whereSql
    ORDER BY t.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

$types = ['deposit','withdrawal','transfer','credit','fee','adjustment'];
$statuses = ['pending','approved','rejected','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Reports</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        :root {
            --primary: #00572d;
            --secondary: #1f9345;
            --accent: #f3c300;
            --text: #333;
            --bg: #f4f4f4;
            --white: #fff;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', Arial, sans-serif; }
        .main-content-wrapper { min-height: 100vh; padding: 2rem 2vw; margin-left: 256px; }
        .page-header { color: var(--primary); margin-bottom: 2rem; font-weight: 700; letter-spacing: 1px; }
        .summary-cards { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 2rem; }
        .summary-card { background: var(--white); border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 1.5rem 2rem; flex: 1 1 200px; min-width: 200px; }
        .summary-card .label { color: #888; font-size: 1em; }
        .summary-card .value { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .filter-bar { background: var(--white); border-radius: 12px; padding: 1.2rem 2rem; margin-bottom: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .filter-bar select, .filter-bar input { min-width: 160px; border-radius: 8px; border: 1px solid #ccc; padding: 0.6rem 1rem; font-size: 1rem; }
        .filter-bar button, .filter-bar a.btn { background: var(--primary); color: var(--white); border: none; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 600; transition: background 0.2s; }
        .filter-bar button:hover, .filter-bar a.btn:hover { background: var(--secondary); color: var(--white); }
        .filter-bar .btn-warning { background: var(--accent) !important; color: var(--text) !important; border: none; }
        .charts-row { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem; }
        .chart-card { background: var(--white); border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 1.5rem; flex: 1 1 350px; min-width: 320px; }
        .table-responsive { background: var(--white); border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 1.2rem; }
        .status-badge { padding: 0.35rem 1rem; border-radius: 14px; font-size: 1em; font-weight: 700; display: inline-block; letter-spacing: 0.5px; }
        .status-pending { background: #fffbe6; color: var(--accent); border: 1px solid var(--accent); }
        .status-approved { background: #e6f9ed; color: var(--secondary); border: 1px solid var(--secondary); }
        .status-rejected { background: #fdeaea; color: #dc3545; border: 1px solid #dc3545; }
        .status-cancelled { background: #f4f4f4; color: #888; border: 1px solid #ccc; }
        .pagination { margin-top: 2rem; display: flex; justify-content: center; gap: 0.5rem; }
        .pagination a, .pagination span { display: inline-block; padding: 0.6rem 1.2rem; border-radius: 8px; background: var(--white); color: var(--primary); border: 1px solid #eee; text-decoration: none; font-weight: 600; }
        .pagination .active { background: var(--primary); color: var(--white); border-color: var(--primary); }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; padding: 1rem 0.5rem; } .charts-row { flex-direction: column; gap: 1rem; } .summary-cards { flex-direction: column; gap: 1rem; } }
    </style>
</head>
<body>
<div class="main-content-wrapper">
    <h2 class="page-header"><i class="fas fa-chart-line me-2"></i>Financial Reports</h2>
    <form class="filter-bar" method="get">
        <select name="type">
            <option value="">All Types</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date ?? '') ?>">
        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date ?? '') ?>">
        <button type="submit"><i class="fas fa-search"></i> Filter</button>
        <a href="?" class="btn btn-light">Reset</a>
        <a href="export_transactions.php?<?= http_build_query($_GET) ?>" class="btn btn-warning"><i class="fas fa-file-export"></i> Export</a>
        <button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </form>
    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">Total Deposits</div>
            <div class="value">₦<?= number_format($summary['total_deposits'] ?? 0,2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Total Withdrawals</div>
            <div class="value">₦<?= number_format($summary['total_withdrawals'] ?? 0,2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Total Credits</div>
            <div class="value">₦<?= number_format($summary['total_credits'] ?? 0,2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Total Transfers</div>
            <div class="value">₦<?= number_format($summary['total_transfers'] ?? 0,2) ?></div>
        </div>
        <div class="summary-card">
            <div class="label">Total Transactions</div>
            <div class="value"><?= number_format($summary['total_transactions'] ?? 0) ?></div>
        </div>
    </div>
    <div class="charts-row">
        <div class="chart-card">
            <h6 class="mb-3" style="color:var(--primary);font-weight:700;">Monthly Trend</h6>
            <canvas id="monthlyTrend" height="180"></canvas>
        </div>
        <div class="chart-card">
            <h6 class="mb-3" style="color:var(--primary);font-weight:700;">Category Breakdown</h6>
            <canvas id="categoryBreakdown" height="180"></canvas>
        </div>
    </div>
    <div class="d-flex align-items-center mb-3">
        <button class="btn btn-success me-2" id="printSelectedBtn" onclick="printSelectedTransactions()" disabled><i class="fas fa-print me-1"></i> Print Selected</button>
        <button class="btn btn-warning me-2" id="exportSelectedBtn" onclick="exportSelectedTransactions()" disabled><i class="fas fa-file-export me-1"></i> Export Selected</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead style="background:var(--primary); color:#fff;">
                <tr>
                    <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" aria-label="Select all transactions"></th>
                    <th>#</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>Department</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr tabindex="0" role="button" aria-label="View details for transaction #<?= $tx['id'] ?>" onclick="showDetails(<?= htmlspecialchars(json_encode($tx)) ?>)">
                    <td><input type="checkbox" class="select-tx" value="<?= $tx['id'] ?>" onclick="updateActionBtns()" aria-label="Select transaction #<?= $tx['id'] ?>"></td>
                    <td><?= $tx['id'] ?></td>
                    <td><?= htmlspecialchars($tx['user_name'] ?? '') ?></td>
                    <td><?= ucfirst($tx['type'] ?? '') ?></td>
                    <td>₦<?= number_format($tx['amount'] ?? 0,2) ?></td>
                    <td><span class="status-badge status-<?= htmlspecialchars($tx['status'] ?? '') ?>"> <?= ucfirst($tx['status'] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($tx['reference'] ?? '') ?></td>
                    <td><?= htmlspecialchars($tx['description'] ?? '') ?></td>
                    <td><?= htmlspecialchars($tx['department'] ?? '') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($tx['created_at'] ?? ''))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$transactions): ?>
            <div class="text-center text-muted py-4">No transactions found.</div>
        <?php endif; ?>
    </div>
    <?php $totalPages = ceil($total/$perPage); if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i=1; $i<=$totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<div class="modal fade" id="transactionDetailModal" tabindex="-1" aria-labelledby="transactionDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff;">
        <h5 class="modal-title" id="transactionDetailModalLabel"><i class="fas fa-info-circle me-2"></i>Transaction Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="transactionDetailBody">
        <!-- Details will be loaded here -->
      </div>
      <div class="modal-footer">
        <a href="#" id="exportDetailBtn" class="btn btn-warning" target="_blank"><i class="fas fa-file-export"></i> Export</a>
        <a href="#" id="printDetailBtn" class="btn btn-outline-dark" target="_blank"><i class="fas fa-print"></i> Print</a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const monthlyData = <?= json_encode($monthly) ?>;
const ctx1 = document.getElementById('monthlyTrend').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => item.month),
        datasets: [
            { label: 'Deposits', data: monthlyData.map(item => parseFloat(item.deposits)), borderColor: '#1f9345', backgroundColor: 'rgba(31,147,69,0.1)', tension: 0.3 },
            { label: 'Withdrawals', data: monthlyData.map(item => parseFloat(item.withdrawals)), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', tension: 0.3 },
            { label: 'Credits', data: monthlyData.map(item => parseFloat(item.credits)), borderColor: '#f3c300', backgroundColor: 'rgba(243,195,0,0.1)', tension: 0.3 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});
const categoryData = <?= json_encode($categories) ?>;
const ctx2 = document.getElementById('categoryBreakdown').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: categoryData.map(item => item.type.charAt(0).toUpperCase() + item.type.slice(1)),
        datasets: [{ data: categoryData.map(item => parseFloat(item.total)), backgroundColor: ['#00572d','#1f9345','#f3c300','#dc3545','#888','#aaa'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
function toggleSelectAll(cb) {
    document.querySelectorAll('.select-tx').forEach(el => { el.checked = cb.checked; });
    updateActionBtns();
}
function updateActionBtns() {
    const anyChecked = Array.from(document.querySelectorAll('.select-tx')).some(cb => cb.checked);
    document.getElementById('printSelectedBtn').disabled = !anyChecked;
    document.getElementById('exportSelectedBtn').disabled = !anyChecked;
}
function getSelectedTransactionIds() {
    return Array.from(document.querySelectorAll('.select-tx:checked')).map(cb => cb.value);
}
function printSelectedTransactions() {
    const ids = getSelectedTransactionIds();
    if (!ids.length) return;
    const printWin = window.open('', '_blank');
    printWin.document.write('<html><head><title>Batch Transaction Receipts</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></head><body>');
    let loaded = 0;
    ids.forEach(id => {
        fetch('print_transaction.php?id=' + encodeURIComponent(id))
            .then(r => r.text())
            .then(html => {
                const div = document.createElement('div');
                div.innerHTML = html;
                const receipt = div.querySelector('.receipt-container');
                if (receipt) printWin.document.body.appendChild(receipt.cloneNode(true));
                loaded++;
                if (loaded === ids.length) {
                    printWin.document.write('</body></html>');
                    printWin.document.close();
                    printWin.focus();
                    setTimeout(() => { printWin.print(); }, 600);
                }
            });
    });
}
function exportSelectedTransactions() {
    const ids = getSelectedTransactionIds();
    if (!ids.length) return;
    window.open('export_transactions.php?ids=' + ids.join(','), '_blank');
}
let currentDetailTx = null;
function showDetails(tx) {
    currentDetailTx = tx;
    let html = `<div class='row'>
        <div class='col-md-6 mb-2'><b>User:</b> ${tx.user_name} <br><b>Department:</b> ${tx.department}</div>
        <div class='col-md-6 mb-2'><b>Type:</b> ${tx.type} <br><b>Status:</b> ${tx.status} <br><b>Reference:</b> ${tx.reference}</div>
        <div class='col-md-12 mb-2'><b>Description:</b> ${tx.description}</div>
        <div class='col-md-6 mb-2'><b>Amount:</b> ₦${parseFloat(tx.amount).toLocaleString()} </div>
        <div class='col-md-6 mb-2'><b>Created:</b> ${tx.created_at}</div>
    </div>`;
    document.getElementById('transactionDetailBody').innerHTML = html;
    document.getElementById('exportDetailBtn').href = 'export_transactions.php?single=1&id=' + encodeURIComponent(tx.id);
    document.getElementById('printDetailBtn').href = 'print_transaction.php?id=' + encodeURIComponent(tx.id);
    let modal = new bootstrap.Modal(document.getElementById('transactionDetailModal'));
    modal.show();
}
</script>
</body>
</html> 