<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../include/Logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$db = getDB();
$logger = Logger::getInstance();
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

function respond($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

if ($action === 'list') {
    $backups = $db->fetchAll('SELECT * FROM backups ORDER BY created_at DESC LIMIT 50');
    respond(['success' => true, 'backups' => $backups]);
}

if ($action === 'create') {
    // Get database name from config
    $dbConfig = parse_ini_file(__DIR__ . '/../../config/config.ini');
    $dbName = $dbConfig['db_name'];
    
    // Create backup directory if it doesn't exist
    $backupDir = __DIR__ . '/../../backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Generate backup filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/' . $dbName . '_' . $timestamp . '.sql';
    
    // Build mysqldump command
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s',
        escapeshellarg($dbConfig['db_host']),
        escapeshellarg($dbConfig['db_user']),
        escapeshellarg($dbConfig['db_pass']),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    
    // Execute backup command
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0) {
        // Update last backup time in settings
        $db->execute("
            INSERT INTO settings (name, value) 
            VALUES ('last_backup_time', NOW())
            ON DUPLICATE KEY UPDATE value = NOW()
        ");
        
        // Log the backup
        $logger->logActivity(
            $_SESSION['user_id'],
            'system',
            'backup',
            'Database backup created successfully'
        );
        
        respond([
            'success' => true,
            'message' => 'Backup completed successfully',
            'file' => basename($backupFile)
        ]);
    } else {
        throw new Exception('Backup command failed');
    }
}

if ($action === 'download' && isset($_GET['id'])) {
    $backup = $db->fetchOne('SELECT * FROM backups WHERE id = ?', [$_GET['id']]);
    if ($backup && file_exists(__DIR__ . '/../../backups/' . $backup['file_path'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
        readfile(__DIR__ . '/../../backups/' . $backup['file_path']);
        exit();
    } else {
        http_response_code(404);
        echo 'File not found.';
        exit();
    }
}

if ($action === 'restore' && isset($_POST['id'])) {
    $backup = $db->fetchOne('SELECT * FROM backups WHERE id = ?', [$_POST['id']]);
    if ($backup && file_exists(__DIR__ . '/../../backups/' . $backup['file_path'])) {
        // Example: restore DB (ensure permissions and path)
        $cmd = sprintf('mysql -u%s -p%s %s < %s', DB_USER, DB_PASS, DB_NAME, escapeshellarg(__DIR__ . '/../../backups/' . $backup['file_path']));
        exec($cmd, $output, $result);
        if ($result === 0) {
            respond(['success' => true, 'message' => 'Backup restored successfully.']);
        } else {
            respond(['success' => false, 'message' => 'Restore failed.']);
        }
    } else {
        respond(['success' => false, 'message' => 'Backup file not found.']);
    }
}

if ($action === 'delete' && isset($_POST['id'])) {
    $backup = $db->fetchOne('SELECT * FROM backups WHERE id = ?', [$_POST['id']]);
    if ($backup) {
        $file = __DIR__ . '/../../backups/' . $backup['file_path'];
        if (file_exists($file)) unlink($file);
        $db->executeQuery('DELETE FROM backups WHERE id = ?', [$_POST['id']]);
        respond(['success' => true, 'message' => 'Backup deleted.']);
    } else {
        respond(['success' => false, 'message' => 'Backup not found.']);
    }
}

respond(['success' => false, 'message' => 'Invalid action.']); 