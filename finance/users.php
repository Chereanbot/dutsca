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
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';
$role = $_GET['role'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search) {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.department LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($department) {
    $where[] = 'u.department = ?';
    $params[] = $department;
}
if ($status) {
    $where[] = 'u.status = ?';
    $params[] = $status;
}
if ($role) {
    $where[] = 'u.role = ?';
    $params[] = $role;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM users u $whereSql", $params)['cnt'] ?? 0;
$users = $db->fetchAll("
    SELECT * FROM users u $whereSql
    ORDER BY u.name ASC
    LIMIT $perPage OFFSET $offset
", $params);

// For filter dropdowns
$departments = $db->fetchAll("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
$roles = $db->fetchAll("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role != '' ORDER BY role ASC");
$statuses = ['active','inactive'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .filter-bar { background: var(--white); border-radius: 12px; padding: 1.2rem 2rem; margin-bottom: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,0.06); display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .filter-bar select, .filter-bar input { min-width: 160px; border-radius: 8px; border: 1px solid #ccc; padding: 0.6rem 1rem; font-size: 1rem; }
        .filter-bar button, .filter-bar a.btn { background: var(--primary); color: var(--white); border: none; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 600; transition: background 0.2s; }
        .filter-bar button:hover, .filter-bar a.btn:hover { background: var(--secondary); color: var(--white); }
        .filter-bar .btn-warning { background: var(--accent) !important; color: var(--text) !important; border: none; }
        .table-responsive { background: var(--white); border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 1.2rem; }
        .user-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary); margin-right: 0.7em; }
        .status-badge { padding: 0.35rem 1rem; border-radius: 14px; font-size: 1em; font-weight: 700; display: inline-block; letter-spacing: 0.5px; }
        .status-active { background: #e6f9ed; color: var(--secondary); border: 1px solid var(--secondary); }
        .status-inactive { background: #fdeaea; color: #dc3545; border: 1px solid #dc3545; }
        .role-badge { background: var(--accent); color: var(--primary); border-radius: 10px; padding: 0.2em 0.8em; font-weight: 600; font-size: 0.95em; }
        .pagination { margin-top: 2rem; display: flex; justify-content: center; gap: 0.5rem; }
        .pagination a, .pagination span { display: inline-block; padding: 0.6rem 1.2rem; border-radius: 8px; background: var(--white); color: var(--primary); border: 1px solid #eee; text-decoration: none; font-weight: 600; }
        .pagination .active { background: var(--primary); color: var(--white); border-color: var(--primary); }
        @media (max-width: 991.98px) { .main-content-wrapper { margin-left: 0; padding: 1rem 0.5rem; } }
    </style>
</head>
<body>
<div class="main-content-wrapper">
    <h2 class="page-header"><i class="fas fa-users me-2"></i>Users</h2>
    <form class="filter-bar" method="get">
        <input type="text" name="search" placeholder="Search by name, email, department..." value="<?= htmlspecialchars($search ?? '') ?>">
        <select name="department">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d['department']) ?>" <?= $department===$d['department']?'selected':'' ?>><?= htmlspecialchars($d['department']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="role">
            <option value="">All Roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= htmlspecialchars($r['role']) ?>" <?= $role===$r['role']?'selected':'' ?>><?= htmlspecialchars(ucfirst($r['role'])) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit"><i class="fas fa-search"></i> Filter</button>
        <a href="?" class="btn btn-light">Reset</a>
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead style="background:var(--primary); color:#fff;">
                <tr>
                    <th>#</th>
                    <th>Avatar</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr tabindex="0" role="button" aria-label="View details for <?= htmlspecialchars($user['name']) ?>" onclick="showUserDetails(<?= htmlspecialchars(json_encode($user)) ?>)">
                    <td><?= $user['id'] ?></td>
                    <td><img src="<?= ($user['profile_image'] ?? '') ? '/dutsca/assets/images/profile/' . htmlspecialchars($user['profile_image'] ?? '') : 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? '') . '&background=00572d&color=fff&size=64' ?>" class="user-avatar" alt="Avatar"></td>
                    <td><?= htmlspecialchars($user['name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($user['department'] ?? '') ?></td>
                    <td><span class="role-badge"><?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?></span></td>
                    <td><span class="status-badge status-<?= htmlspecialchars($user['status'] ?? '') ?>"> <?= ucfirst($user['status'] ?? '') ?></span></td>
                    <td><button class="btn btn-outline-primary btn-sm" onclick="event.stopPropagation();showUserDetails(<?= htmlspecialchars(json_encode($user)) ?>)"><i class="fas fa-eye"></i> View</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!$users): ?>
            <div class="text-center text-muted py-4">No users found.</div>
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
<!-- User Detail Modal -->
<div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--primary);color:#fff;">
        <h5 class="modal-title" id="userDetailModalLabel"><i class="fas fa-user me-2"></i>User Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="userDetailBody">
        <!-- Details will be loaded here -->
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showUserDetails(user) {
    // AJAX fetch user actions and build modal content
    fetch('user_actions.php?id=' + encodeURIComponent(user.id))
        .then(r => r.text())
        .then(html => {
            document.getElementById('userDetailBody').innerHTML = html;
            let modal = new bootstrap.Modal(document.getElementById('userDetailModal'));
            modal.show();
        });
}
</script>
</body>
</html> 