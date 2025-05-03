<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Validate required fields
$required = ['name', 'username', 'email', 'department', 'role', 'status'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'error' => 'Missing required field: ' . $field]);
        exit();
    }
}

// Prepare data
$name = trim($_POST['name']);
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$contact_number = trim($_POST['contact_number'] ?? '');
$department = trim($_POST['department']);
$employee_id = trim($_POST['employee_id'] ?? '');
$position = trim($_POST['position'] ?? '');
$date_joined = trim($_POST['date_joined'] ?? '');
$account_number = trim($_POST['account_number'] ?? '');
$bank_name = trim($_POST['bank_name'] ?? '');
$bank_branch = trim($_POST['bank_branch'] ?? '');
$membership_number = trim($_POST['membership_number'] ?? '');
$membership_date = trim($_POST['membership_date'] ?? '');
$credit_eligible = isset($_POST['credit_eligible']) ? intval($_POST['credit_eligible']) : 0;
$credit_score = trim($_POST['credit_score'] ?? '');
$monthly_contribution = trim($_POST['monthly_contribution'] ?? '');
$total_contribution = trim($_POST['total_contribution'] ?? '');
$available_balance = trim($_POST['available_balance'] ?? '');
$role = trim($_POST['role']);
$status = trim($_POST['status']);
$password = trim($_POST['password'] ?? '');

// Handle profile image upload
$profile_image = '';
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
    $filename = 'user_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $target = __DIR__ . '/../../assets/images/' . $filename;
    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
        $profile_image = '/assets/images/' . $filename;
    }
}

// Hash password if provided
$hashed_password = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

try {
    $db = getDB();
    $sql = "INSERT INTO users (name, username, email, contact_number, department, employee_id, position, date_joined, account_number, bank_name, bank_branch, membership_number, membership_date, credit_eligible, credit_score, monthly_contribution, total_contribution, available_balance, role, status, profile_image, password, created_at, updated_at) VALUES (:name, :username, :email, :contact_number, :department, :employee_id, :position, :date_joined, :account_number, :bank_name, :bank_branch, :membership_number, :membership_date, :credit_eligible, :credit_score, :monthly_contribution, :total_contribution, :available_balance, :role, :status, :profile_image, :password, NOW(), NOW())";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':contact_number', $contact_number);
    $stmt->bindValue(':department', $department);
    $stmt->bindValue(':employee_id', $employee_id);
    $stmt->bindValue(':position', $position);
    $stmt->bindValue(':date_joined', $date_joined);
    $stmt->bindValue(':account_number', $account_number);
    $stmt->bindValue(':bank_name', $bank_name);
    $stmt->bindValue(':bank_branch', $bank_branch);
    $stmt->bindValue(':membership_number', $membership_number);
    $stmt->bindValue(':membership_date', $membership_date);
    $stmt->bindValue(':credit_eligible', $credit_eligible);
    $stmt->bindValue(':credit_score', $credit_score);
    $stmt->bindValue(':monthly_contribution', $monthly_contribution);
    $stmt->bindValue(':total_contribution', $total_contribution);
    $stmt->bindValue(':available_balance', $available_balance);
    $stmt->bindValue(':role', $role);
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':profile_image', $profile_image);
    $stmt->bindValue(':password', $hashed_password);
    $stmt->execute();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 