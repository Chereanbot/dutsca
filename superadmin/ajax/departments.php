<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$db = getDB();
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'list') {
        $query = trim($_GET['query'] ?? '');
        $sql = "SELECT * FROM departments";
        $params = [];
        if ($query) {
            $sql .= " WHERE name LIKE ? OR description LIKE ?";
            $params = ["%$query%", "%$query%"];
        }
        $sql .= " ORDER BY name ASC";
        $departments = $db->fetchAll($sql, $params);
        echo json_encode(['success' => true, 'departments' => $departments]);
    } elseif ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $dept = $db->fetchOne("SELECT * FROM departments WHERE id = ?", [$id]);
        if ($dept) echo json_encode(['success' => true, 'department' => $dept]);
        else echo json_encode(['success' => false, 'error' => 'Department not found']);
    } elseif ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $head_user_id = $_POST['head_user_id'] !== '' ? intval($_POST['head_user_id']) : null;
        if (!$name) throw new Exception('Department name is required');
        if ($action === 'add') {
            $db->executeQuery("INSERT INTO departments (name, description, head_user_id) VALUES (?, ?, ?)", [$name, $description, $head_user_id]);
            echo json_encode(['success' => true, 'message' => 'Department added successfully']);
        } else {
            $id = intval($_POST['id'] ?? 0);
            $db->executeQuery("UPDATE departments SET name=?, description=?, head_user_id=? WHERE id=?", [$name, $description, $head_user_id, $id]);
            echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $db->executeQuery("DELETE FROM departments WHERE id=?", [$id]);
        echo json_encode(['success' => true, 'message' => 'Department deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 