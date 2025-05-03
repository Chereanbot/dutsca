<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

$id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
if (!$id) {
    echo '<div class="alert alert-danger">Invalid user ID.</div>';
    exit;
}

$db = getDB();
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    exit;
}

$tab = $_GET['tab'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$typeMap = [
    'all' => '',
    'deposit' => 'deposit',
    'withdrawal' => 'withdrawal',
    'transfer' => 'transfer',
    'credit' => 'credit',
];
$types = ['all','deposit','withdrawal','transfer','credit'];
$type = $typeMap[$tab] ?? '';
$where = ['user_id = ?'];
$params = [$id];
if ($type) {
    $where[] = 'type = ?';
    $params[] = $type;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM transactions $whereSql", $params)['cnt'] ?? 0;
$transactions = $db->fetchAll("
    SELECT * FROM transactions $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-3 text-center">
            <img src="<?= ($user['profile_image'] ?? '') ? '/dutsca/assets/images/profile/' . htmlspecialchars($user['profile_image'] ?? '') : 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? '') . '&background=00572d&color=fff&size=96' ?>" class="rounded-circle mb-2" style="width:96px;height:96px;border:3px solid #00572d;object-fit:cover;">
            <h5 class="mt-2 mb-0" style="color:#00572d;"><?= htmlspecialchars($user['name'] ?? '') ?></h5>
            <div class="text-muted mb-1">ID: <?= $user['id'] ?></div>
            <div class="mb-1"><span class="badge bg-secondary" style="background:#f3c300;color:#00572d;"> <?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?> </span></div>
            <div class="mb-1"><span class="badge <?= ($user['status'] ?? '')==='active' ? 'bg-success' : 'bg-danger' ?>"> <?= ucfirst($user['status'] ?? '') ?> </span></div>
            <div class="mb-1"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($user['email'] ?? '') ?></div>
            <div class="mb-1"><i class="fas fa-building me-1"></i> <?= htmlspecialchars($user['department'] ?? '') ?></div>
            <div class="mb-1"><i class="fas fa-calendar-alt me-1"></i> Joined: <?= htmlspecialchars($user['membership_date'] ?? '') ?></div>
        </div>
        <div class="col-md-9">
            <ul class="nav nav-tabs mb-3" id="userTabs">
                <li class="nav-item"><a class="nav-link<?= $tab==='all'?' active':'' ?>" href="#" onclick="loadUserTab('all');return false;">All Transactions</a></li>
                <li class="nav-item"><a class="nav-link<?= $tab==='deposit'?' active':'' ?>" href="#" onclick="loadUserTab('deposit');return false;">Deposits</a></li>
                <li class="nav-item"><a class="nav-link<?= $tab==='withdrawal'?' active':'' ?>" href="#" onclick="loadUserTab('withdrawal');return false;">Withdrawals</a></li>
                <li class="nav-item"><a class="nav-link<?= $tab==='transfer'?' active':'' ?>" href="#" onclick="loadUserTab('transfer');return false;">Transfers</a></li>
                <li class="nav-item"><a class="nav-link<?= $tab==='credit'?' active':'' ?>" href="#" onclick="loadUserTab('credit');return false;">Credits</a></li>
            </ul>
            <div class="d-flex mb-2 align-items-center">
                <button class="btn btn-warning btn-sm me-2" onclick="exportUserTab('<?= $tab ?>')"><i class="fas fa-file-export"></i> Export</button>
                <button class="btn btn-outline-dark btn-sm" onclick="printUserTab('<?= $tab ?>')"><i class="fas fa-print"></i> Print</button>
                <span class="ms-auto text-muted small">Showing <?= min($total, $perPage*($page-1)+1) ?> - <?= min($total, $perPage*$page) ?> of <?= $total ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead style="background:#00572d;color:#fff;">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td><?= $tx['id'] ?></td>
                            <td><?= ucfirst($tx['type'] ?? '') ?></td>
                            <td>₦<?= number_format($tx['amount'] ?? 0,2) ?></td>
                            <td><span class="badge <?= $tx['status']==='approved'?'bg-success':($tx['status']==='pending'?'bg-warning text-dark':'bg-danger') ?>"> <?= ucfirst($tx['status'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars($tx['reference'] ?? '') ?></td>
                            <td><?= htmlspecialchars($tx['description'] ?? '') ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($tx['created_at'] ?? ''))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$transactions): ?>
                    <div class="text-center text-muted py-4">No records found.</div>
                <?php endif; ?>
            </div>
            <?php $totalPages = ceil($total/$perPage); if ($totalPages > 1): ?>
            <nav><ul class="pagination pagination-sm justify-content-end mt-2">
                <?php for ($i=1; $i<=$totalPages; $i++): ?>
                    <li class="page-item<?= $i==$page?' active':'' ?>">
                        <a class="page-link" href="#" onclick="loadUserTab('<?= $tab ?>',<?= $i ?>);return false;"> <?= $i ?> </a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
function loadUserTab(tab, page=1) {
    const url = `user_actions.php?user_id=<?= $id ?>&tab=${tab}&page=${page}`;
    fetch(url)
        .then(r => r.text())
        .then(html => {
            document.getElementById('userDetailBody').innerHTML = html;
        });
}
function exportUserTab(tab) {
    window.open('export_transactions.php?user_id=<?= $id ?>&type=' + tab, '_blank');
}
function printUserTab(tab) {
    window.open('print_user_transactions.php?user_id=<?= $id ?>&type=' + tab, '_blank');
}
</script> 