<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Get query parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE :search OR username LIKE :search OR employee_id LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($department !== '') {
    $where[] = "department = :department";
    $params[':department'] = $department;
}
if ($role !== '') {
    $where[] = "role = :role";
    $params[':role'] = $role;
}
if ($status !== '') {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

try {
    $db = getDB();
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM users $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get paginated users
    $sql = "SELECT id, username, name, email, contact_number, department, employee_id, position, date_joined, account_number, bank_name, bank_branch, membership_number, membership_date, credit_eligible, credit_score, monthly_contribution, total_contribution, available_balance, role, status, profile_image, last_login, login_attempts, email_verified, created_at, updated_at, approved_at, approved_by FROM users $where_sql ORDER BY id DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => intval($total),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
