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
$logger = Logger::getInstance();

// Filters
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
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
if ($search) {
    $where[] = '(t.reference LIKE ? OR t.description LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add date range filter
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
if ($start_date) {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $start_date;
}
if ($end_date) {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $end_date;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Add sorting
$sortable = ['id','user_name','type','amount','status','reference','created_at'];
$sort = $_GET['sort'] ?? 'created_at';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$sort_sql = in_array($sort, $sortable) ? $sort : 'created_at';

// Get total count
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM transactions t JOIN users u ON t.user_id = u.id $whereSql", $params)['cnt'] ?? 0;

// Get transactions
$transactions = $db->fetchAll("
    SELECT t.*, u.name as user_name, u.department, u.email, u.profile_image, p.name as processed_by_name
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    LEFT JOIN users p ON t.processed_by = p.id
    $whereSql
    ORDER BY $sort_sql $dir
    LIMIT $perPage OFFSET $offset
", $params);

// Transaction types and statuses
$types = ['deposit','withdrawal','transfer','credit','fee','adjustment'];
$statuses = ['pending','approved','rejected','cancelled'];

?>
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
    --footer-dark: #1a1a1a;
    --sidebar-width: 256px;
    --header-height: 60px;
}
body { background: var(--bg); }
.main-content-wrapper {
    min-height: 100vh;
    padding: 2rem 2vw 2rem 2vw;
    transition: margin-left 0.3s;
    margin-left: var(--sidebar-width);
}
@media (max-width: 991.98px) {
    .main-content-wrapper {
        margin-left: 0;
        padding: 1rem 0.5rem;
    }
}
.page-header {
    color: var(--primary);
    margin-bottom: 2rem;
    font-weight: 700;
    letter-spacing: 1px;
}
.filter-bar {
    background: var(--white);
    border-radius: 12px;
    padding: 1.2rem 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}
.filter-bar select, .filter-bar input {
    min-width: 160px;
    border-radius: 8px;
    border: 1px solid #ccc;
    padding: 0.6rem 1rem;
    font-size: 1rem;
}
.filter-bar button, .filter-bar a.btn {
    background: var(--primary);
    color: var(--white);
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    transition: background 0.2s;
}
.filter-bar button:hover, .filter-bar a.btn:hover {
    background: var(--secondary);
    color: var(--white);
}
.filter-bar .btn-warning {
    background: var(--accent) !important;
    color: var(--text) !important;
    border: none;
}
.table-responsive {
    background: var(--white);
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    padding: 1.2rem;
}
.status-badge {
    padding: 0.35rem 1rem;
    border-radius: 14px;
    font-size: 1em;
    font-weight: 700;
    display: inline-block;
    letter-spacing: 0.5px;
}
.status-pending { background: #fffbe6; color: var(--accent); border: 1px solid var(--accent); }
.status-approved { background: #e6f9ed; color: var(--secondary); border: 1px solid var(--secondary); }
.status-rejected { background: #fdeaea; color: #dc3545; border: 1px solid #dc3545; }
.status-cancelled { background: #f4f4f4; color: #888; border: 1px solid #ccc; }
.action-btn {
    border: none;
    background: none;
    color: var(--primary);
    font-size: 1.2em;
    margin-right: 0.5em;
    cursor: pointer;
    transition: color 0.2s;
}
.action-btn:hover { color: var(--secondary); }
.avatar {
    width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); margin-right: 0.5em;
}
tr.selected { background: #e6f9ed !important; }
tr:hover { background: #f3f7f5 !important; }
.tooltip-inner { background: var(--primary) !important; color: #fff !important; }
.pagination {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}
.pagination a, .pagination span {
    display: inline-block;
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    background: var(--white);
    color: var(--primary);
    border: 1px solid #eee;
    text-decoration: none;
    font-weight: 600;
}
.pagination .active {
    background: var(--primary);
    color: var(--white);
    border-color: var(--primary);
}
@media (max-width: 900px) {
    .filter-bar { flex-direction: column; align-items: stretch; }
    .table-responsive { padding: 0.5rem; }
}
</style>
<div class="main-content-wrapper">
    <h2 class="page-header"><i class="fas fa-money-check-alt me-2"></i>Transactions</h2>
    <div class="d-flex align-items-center mb-3">
        <button class="btn btn-success me-2" id="printSelectedBtn" onclick="printSelectedTransactions()" disabled><i class="fas fa-print me-1"></i> Print Selected</button>
        <form class="filter-bar row g-2 align-items-end" method="get">
            <div class="col-auto">
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <input type="text" name="search" class="form-control" placeholder="Search by user, reference, description..." value="<?= htmlspecialchars($search ?? '') ?>">
            </div>
            <div class="col-auto">
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date ?? '') ?>">
            </div>
            <div class="col-auto">
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date ?? '') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            </div>
            <div class="col-auto">
                <a href="?" class="btn btn-light">Reset</a>
            </div>
            <div class="col-auto ms-auto">
                <a href="export_transactions.php?<?= http_build_query($_GET) ?>" class="btn btn-warning"><i class="fas fa-file-export"></i> Export</a>
                <button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </form>
    </div>
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle">
            <thead style="background:var(--primary); color:#fff;">
                <tr>
                    <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                    <?php
                    $headers = [
                        'id' => '#',
                        'user_name' => 'User',
                        'type' => 'Type',
                        'amount' => 'Amount',
                        'status' => 'Status',
                        'reference' => 'Reference',
                        'description' => 'Description',
                        'related_user_id' => 'Related User',
                        'processed_by_name' => 'Processed By',
                        'created_at' => 'Created',
                        'actions' => 'Actions',
                    ];
                    foreach ($headers as $key => $label):
                        if ($key === 'actions') {
                            echo "<th>$label</th>";
                            continue;
                        }
                        $is_sortable = in_array($key, $sortable);
                        $sort_dir = ($sort === $key && $dir === 'ASC') ? 'desc' : 'asc';
                        $icon = $sort === $key ? ($dir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
                        $sort_link = http_build_query(array_merge($_GET, ['sort'=>$key, 'dir'=>$sort_dir]));
                        echo "<th>";
                        if ($is_sortable) {
                            echo "<a href='?$sort_link' class='text-green text-decoration-none'>$label <i class='fas $icon'></i></a>";
                        } else {
                            echo $label;
                        }
                        echo "</th>";
                    endforeach;
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr id="row-<?= $tx['id'] ?>">
                    <td><input type="checkbox" class="select-tx" value="<?= $tx['id'] ?>" onclick="updatePrintBtn()"></td>
                    <td><?= $tx['id'] ?></td>
                    <td>
                        <img src="<?= ($tx['profile_image'] ?? '') ? '/dutsca/assets/images/profile/' . htmlspecialchars($tx['profile_image'] ?? '') : 'https://ui-avatars.com/api/?name=' . urlencode($tx['user_name'] ?? '') . '&background=00572d&color=fff&size=64' ?>" class="avatar" alt="Avatar" title="<?= htmlspecialchars($tx['user_name'] ?? '') ?>" style="cursor:pointer;" onclick="showUserProfile(<?= htmlspecialchars(json_encode([
                            'name'=>$tx['user_name'] ?? '',
                            'email'=>$tx['email'] ?? '',
                            'department'=>$tx['department'] ?? '',
                            'profile_image'=>$tx['profile_image'] ?? ''
                        ])) ?>)">
                        <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($tx['email'] ?? '') ?>" style="cursor:pointer;" onclick="showUserProfile(<?= htmlspecialchars(json_encode([
                            'name'=>$tx['user_name'] ?? '',
                            'email'=>$tx['email'] ?? '',
                            'department'=>$tx['department'] ?? '',
                            'profile_image'=>$tx['profile_image'] ?? ''
                        ])) ?>)">
                            <?= htmlspecialchars($tx['user_name'] ?? '') ?><br><small><?= htmlspecialchars($tx['department'] ?? '') ?></small>
                        </span>
                    </td>
                    <td><span class="badge bg-secondary text-uppercase" style="background:var(--secondary);color:#fff;"> <?= ucfirst($tx['type'] ?? '') ?> </span></td>
                    <td><span class="fw-bold">₦<?= number_format($tx['amount'] ?? 0,2) ?></span></td>
                    <td><span class="status-badge status-<?= htmlspecialchars($tx['status'] ?? '') ?>"><i class="fas fa-circle me-1"></i> <?= ucfirst($tx['status'] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($tx['reference'] ?? '') ?></td>
                    <td><?= htmlspecialchars($tx['description'] ?? '') ?></td>
                    <td><?php if ($tx['related_user_id']): ?>User #<?= htmlspecialchars($tx['related_user_id'] ?? '') ?><?php endif; ?></td>
                    <td><?= htmlspecialchars($tx['processed_by_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($tx['created_at'] ?? ''))) ?></td>
                    <td>
                        <button class="action-btn" title="View Details" onclick="showDetails(<?= htmlspecialchars(json_encode($tx)) ?>)"><i class="fas fa-eye"></i></button>
                        <?php if (($tx['status'] ?? '')==='pending'): ?>
                            <button class="action-btn text-success" title="Approve" onclick="showActionModal(<?= $tx['id'] ?>, 'approve')"><i class="fas fa-check"></i></button>
                            <button class="action-btn text-danger" title="Reject" onclick="showActionModal(<?= $tx['id'] ?>, 'reject')"><i class="fas fa-times"></i></button>
                            <button class="action-btn text-warning" title="Cancel" onclick="showActionModal(<?= $tx['id'] ?>, 'cancel')"><i class="fas fa-ban"></i></button>
                        <?php endif; ?>
                        <button class="action-btn text-danger" title="Delete" onclick="showActionModal(<?= $tx['id'] ?>, 'delete')"><i class="fas fa-trash"></i></button>
                        <button class="action-btn text-info" title="Export" onclick="exportTransaction(<?= $tx['id'] ?>)"><i class="fas fa-file-export"></i></button>
                        <button class="action-btn text-dark" title="Print" onclick="printTransaction(<?= htmlspecialchars(json_encode($tx)) ?>)"><i class="fas fa-print"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$transactions): ?>
            <div class="text-center text-muted py-4">No transactions found.</div>
        <?php endif; ?>
    </div>
    <!-- Responsive card view for mobile -->
    <div class="d-md-none">
        <?php foreach ($transactions as $tx): ?>
        <div class="card mb-3 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="form-check mb-2">
                        <input class="form-check-input select-tx" type="checkbox" value="<?= $tx['id'] ?>" onclick="updatePrintBtn()" id="select-tx-<?= $tx['id'] ?>">
                        <label class="form-check-label" for="select-tx-<?= $tx['id'] ?>">Select</label>
                    </div>
                    <img src="<?= ($tx['profile_image'] ?? '') ? '/dutsca/assets/images/profile/' . htmlspecialchars($tx['profile_image'] ?? '') : 'https://ui-avatars.com/api/?name=' . urlencode($tx['user_name'] ?? '') . '&background=00572d&color=fff&size=64' ?>" class="avatar me-2" alt="Avatar" title="<?= htmlspecialchars($tx['user_name'] ?? '') ?>" style="cursor:pointer;" onclick="showUserProfile(<?= htmlspecialchars(json_encode([
                        'name'=>$tx['user_name'] ?? '',
                        'email'=>$tx['email'] ?? '',
                        'department'=>$tx['department'] ?? '',
                        'profile_image'=>$tx['profile_image'] ?? ''
                    ])) ?>)">
                    <div style="cursor:pointer;" onclick="showUserProfile(<?= htmlspecialchars(json_encode([
                        'name'=>$tx['user_name'] ?? '',
                        'email'=>$tx['email'] ?? '',
                        'department'=>$tx['department'] ?? '',
                        'profile_image'=>$tx['profile_image'] ?? ''
                    ])) ?>)">
                        <strong><?= htmlspecialchars($tx['user_name'] ?? '') ?></strong><br>
                        <small><?= htmlspecialchars($tx['department'] ?? '') ?></small>
                    </div>
                    <span class="badge bg-secondary ms-auto" style="background:var(--secondary);color:#fff;"> <?= ucfirst($tx['type'] ?? '') ?> </span>
                </div>
                <div><b>Amount:</b> ₦<?= number_format($tx['amount'] ?? 0,2) ?></div>
                <div><b>Status:</b> <span class="status-badge status-<?= htmlspecialchars($tx['status'] ?? '') ?>"> <?= ucfirst($tx['status'] ?? '') ?></span></div>
                <div><b>Reference:</b> <?= htmlspecialchars($tx['reference'] ?? '') ?></div>
                <div><b>Description:</b> <?= htmlspecialchars($tx['description'] ?? '') ?></div>
                <div><b>Related User:</b> <?= $tx['related_user_id'] ? 'User #' . htmlspecialchars($tx['related_user_id'] ?? '') : '-' ?></div>
                <div><b>Processed By:</b> <?= htmlspecialchars($tx['processed_by_name'] ?? '') ?></div>
                <div><b>Created:</b> <?= htmlspecialchars(date('Y-m-d H:i', strtotime($tx['created_at'] ?? ''))) ?></div>
                <div class="mt-2">
                    <button class="action-btn" title="View Details" onclick="showDetails(<?= htmlspecialchars(json_encode($tx)) ?>)"><i class="fas fa-eye"></i></button>
                    <?php if (($tx['status'] ?? '')==='pending'): ?>
                        <button class="action-btn text-success" title="Approve" onclick="showActionModal(<?= $tx['id'] ?>, 'approve')"><i class="fas fa-check"></i></button>
                        <button class="action-btn text-danger" title="Reject" onclick="showActionModal(<?= $tx['id'] ?>, 'reject')"><i class="fas fa-times"></i></button>
                        <button class="action-btn text-warning" title="Cancel" onclick="showActionModal(<?= $tx['id'] ?>, 'cancel')"><i class="fas fa-ban"></i></button>
                    <?php endif; ?>
                    <button class="action-btn text-danger" title="Delete" onclick="showActionModal(<?= $tx['id'] ?>, 'delete')"><i class="fas fa-trash"></i></button>
                    <button class="action-btn text-info" title="Export" onclick="exportTransaction(<?= $tx['id'] ?>)"><i class="fas fa-file-export"></i></button>
                    <button class="action-btn text-dark" title="Print" onclick="printTransaction(<?= htmlspecialchars(json_encode($tx)) ?>)"><i class="fas fa-print"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
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

<!-- Transaction Detail Modal -->
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
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-dark" onclick="printTransaction(currentDetailTx)"><i class="fas fa-print"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<!-- Action Modal (Approve/Reject/Cancel/Delete) -->
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff;">
        <h5 class="modal-title" id="actionModalLabel">Action</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="actionForm" onsubmit="return submitActionForm();">
        <div class="modal-body">
          <input type="hidden" name="tx_id" id="actionTxId">
          <input type="hidden" name="action" id="actionType">
          <div class="mb-3">
            <label for="actionReason" class="form-label">Reason/Note (optional):</label>
            <textarea class="form-control" id="actionReason" name="reason" rows="2" maxlength="255"></textarea>
          </div>
          <div id="actionModalInfo" class="text-muted small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- User Profile Modal -->
<div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff;">
        <h5 class="modal-title" id="userProfileModalLabel"><i class="fas fa-user me-2"></i>User Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="userProfileBody">
        <!-- User details will be loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
// Tooltip
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl);
});

let currentDetailTx = null;
function showDetails(tx) {
    currentDetailTx = tx;
    let html = `<div class='row'>
        <div class='col-md-6 mb-2'><b>User:</b> ${tx.user_name} <br><b>Email:</b> ${tx.email} <br><b>Department:</b> ${tx.department}</div>
        <div class='col-md-6 mb-2'><b>Type:</b> ${tx.type} <br><b>Status:</b> ${tx.status} <br><b>Reference:</b> ${tx.reference}</div>
        <div class='col-md-12 mb-2'><b>Description:</b> ${tx.description}</div>
        <div class='col-md-6 mb-2'><b>Amount:</b> ₦${parseFloat(tx.amount).toLocaleString()} </div>
        <div class='col-md-6 mb-2'><b>Created:</b> ${tx.created_at}</div>
        <div class='col-md-6 mb-2'><b>Processed By:</b> ${tx.processed_by_name || '-'} </div>
        <div class='col-md-6 mb-2'><b>Related User:</b> ${tx.related_user_id ? 'User #' + tx.related_user_id : '-'} </div>
    </div>`;
    document.getElementById('transactionDetailBody').innerHTML = html;
    let modal = new bootstrap.Modal(document.getElementById('transactionDetailModal'));
    modal.show();
}

function showActionModal(id, action) {
    document.getElementById('actionTxId').value = id;
    document.getElementById('actionType').value = action;
    document.getElementById('actionReason').value = '';
    let info = '';
    if (action === 'approve') info = 'Approve this transaction. This will update the user\'s balance.';
    if (action === 'reject') info = 'Reject this transaction. The user will be notified.';
    if (action === 'cancel') info = 'Cancel this transaction. This cannot be undone.';
    if (action === 'delete') info = 'Delete this transaction permanently. This cannot be undone!';
    document.getElementById('actionModalInfo').innerText = info;
    let modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}

function submitActionForm() {
    const id = document.getElementById('actionTxId').value;
    const action = document.getElementById('actionType').value;
    const reason = document.getElementById('actionReason').value;
    if (!id || !action) return false;
    fetch('ajax/process_transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ transaction_id: id, action: action, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        Toastify({
            text: data.success ? `Transaction ${action}d!` : (data.error || 'Error'),
            duration: 3500,
            gravity: 'top',
            position: 'right',
            backgroundColor: data.success ? '#1f9345' : '#dc3545',
        }).showToast();
        if (data.success) setTimeout(()=>location.reload(), 1200);
    })
    .catch(() => {
        Toastify({
            text: 'Network error',
            duration: 3500,
            gravity: 'top',
            position: 'right',
            backgroundColor: '#dc3545',
        }).showToast();
    });
    let modal = bootstrap.Modal.getInstance(document.getElementById('actionModal'));
    modal.hide();
    return false;
}

function showUserProfile(user) {
    let html = `<div class='text-center mb-3'>
        <img src='${user.profile_image ? '/dutsca/assets/images/profile/' + user.profile_image : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(user.name) + '&background=00572d&color=fff&size=96'}' class='avatar mb-2' style='width:64px;height:64px;'>
        <h5>${user.name}</h5>
        <div class='text-muted'>${user.department}</div>
        <div class='text-muted'>${user.email}</div>
    </div>`;
    document.getElementById('userProfileBody').innerHTML = html;
    let modal = new bootstrap.Modal(document.getElementById('userProfileModal'));
    modal.show();
}

function exportTransaction(id) {
    window.open('export_transactions.php?single=1&id=' + encodeURIComponent(id) + '&' + new URLSearchParams(window.location.search), '_blank');
}

function printTransaction(tx) {
    // Create a print window with transaction details
    let html = `<html><head><title>Transaction #${tx.id}</title>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'>
    <style>body{padding:2rem;}h2{color:#00572d;}table{width:100%;margin-top:1rem;}td{padding:0.5rem;vertical-align:top;}</style>
    </head><body>`;
    html += `<h2>Transaction #${tx.id}</h2><table class='table table-bordered'>`;
    html += `<tr><td><b>User</b></td><td>${tx.user_name}</td></tr>`;
    html += `<tr><td><b>Email</b></td><td>${tx.email}</td></tr>`;
    html += `<tr><td><b>Department</b></td><td>${tx.department}</td></tr>`;
    html += `<tr><td><b>Type</b></td><td>${tx.type}</td></tr>`;
    html += `<tr><td><b>Status</b></td><td>${tx.status}</td></tr>`;
    html += `<tr><td><b>Reference</b></td><td>${tx.reference}</td></tr>`;
    html += `<tr><td><b>Description</b></td><td>${tx.description}</td></tr>`;
    html += `<tr><td><b>Amount</b></td><td>₦${parseFloat(tx.amount).toLocaleString()}</td></tr>`;
    html += `<tr><td><b>Created</b></td><td>${tx.created_at}</td></tr>`;
    html += `<tr><td><b>Processed By</b></td><td>${tx.processed_by_name || '-'}</td></tr>`;
    html += `<tr><td><b>Related User</b></td><td>${tx.related_user_id ? 'User #' + tx.related_user_id : '-'}</td></tr>`;
    html += `</table></body></html>`;
    let win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); }, 500);
}

function toggleSelectAll(cb) {
    document.querySelectorAll('.select-tx').forEach(el => { el.checked = cb.checked; });
    updatePrintBtn();
}
function updatePrintBtn() {
    const anyChecked = Array.from(document.querySelectorAll('.select-tx')).some(cb => cb.checked);
    document.getElementById('printSelectedBtn').disabled = !anyChecked;
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
                // Extract only the .receipt-container from the HTML
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
</script>
<?php include '../include/footer.php'; ?> 