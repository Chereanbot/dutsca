<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}
require_once __DIR__ . '/../../config/database.php';
$db = getDB();
$userId = $_SESSION['user_id'];

// Update profile info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['action'])) {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $department = trim($_POST['department'] ?? '');
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Name is required.']); exit();
    }
    $db->executeQuery('UPDATE users SET name=?, contact_number=?, department=? WHERE id=?', [$name, $contact, $department, $userId]);
    $_SESSION['name'] = $name;
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    exit();
}

// Change password
if ($_POST['action'] === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($new === '' || $confirm === '' || $current === '') {
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']); exit();
    }
    if ($new !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']); exit();
    }
    $stmt = $db->executeQuery('SELECT password FROM users WHERE id=?', [$userId]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($current, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']); exit();
    }
    if (strlen($new) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']); exit();
    }
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $db->executeQuery('UPDATE users SET password=? WHERE id=?', [$hash, $userId]);
    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    exit();
}

// Upload profile picture
if ($_POST['action'] === 'upload_picture') {
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded.']); exit();
    }
    $file = $_FILES['profile_image'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, GIF allowed.']); exit();
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 2MB).']); exit();
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile-superadmin-' . $userId . '-' . time() . '.' . $ext;
    $target = __DIR__ . '/../../assets/images/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file.']); exit();
    }
    $db->executeQuery('UPDATE users SET profile_image=? WHERE id=?', ['/assets/images/' . $filename, $userId]);
    echo json_encode(['success' => true, 'message' => 'Profile picture updated.']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']); 