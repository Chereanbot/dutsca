<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get database connection
$db = getDB();
$response = ['success' => false, 'error' => null];

try {
    $action = $_POST['action'] ?? ($_GET['action'] ?? null);

    switch ($action) {
        case 'create_backup':
            $response = createBackup($db);
            break;

        case 'schedule_backup':
            $response = scheduleBackup($db);
            break;

        case 'verify_backup':
            $backupId = $_POST['backup_id'] ?? null;
            if (!$backupId) {
                throw new Exception('Backup ID is required');
            }
            $response = verifyBackup($db, $backupId);
            break;

        case 'delete_backup':
            $backupId = $_POST['backup_id'] ?? null;
            if (!$backupId) {
                throw new Exception('Backup ID is required');
            }
            $response = deleteBackup($db, $backupId);
            break;

        case 'download':
            $backupId = $_GET['id'] ?? null;
            if (!$backupId) {
                throw new Exception('Backup ID is required');
            }
            downloadBackup($db, $backupId);
            exit(); // downloadBackup handles the response

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit();

function createBackup($db) {
    try {
        $logs = [];
        $logs[] = "Starting backup process...";
        
        // Get all table names
        $tables = $db->fetchAll("SHOW TABLES FROM " . DB_NAME);
        $tableCount = count($tables);
        $logs[] = "Found {$tableCount} tables to backup";
        
        // Create backup ID
        $backupId = 'backup_' . date('YmdHis') . '_' . substr(uniqid(), -5);
        $backupSize = 0;
        
        // Create backup directory if it doesn't exist
        $backupDir = __DIR__ . '/../backups';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
            $logs[] = "Created backup directory";
        }
        
        // Create backup file
        $backupFile = $backupDir . '/' . $backupId . '.sql';
        $handle = fopen($backupFile, 'w');
        
        if ($handle) {
            // Write header
            fwrite($handle, "-- DUTSCA Database Backup\n");
            fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n\n");
            
            // Backup each table
            foreach ($tables as $tableRow) {
                $tableName = reset($tableRow);
                $logs[] = "Backing up table: {$tableName}";
                
                // Get create table statement
                $createTableResult = $db->fetchOne("SHOW CREATE TABLE `{$tableName}`");
                if ($createTableResult && isset($createTableResult['Create Table'])) {
                    fwrite($handle, "\n-- Table structure for {$tableName}\n");
                    fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                    fwrite($handle, $createTableResult['Create Table'] . ";\n\n");
                    
                    // Get table data
                    $rows = $db->fetchAll("SELECT * FROM `{$tableName}`");
                    if (!empty($rows)) {
                        fwrite($handle, "-- Dumping data for {$tableName}\n");
                        foreach ($rows as $row) {
                            $values = array_map(function($value) {
                                if (is_null($value)) return 'NULL';
                                if (is_numeric($value)) return $value;
                                return "'" . addslashes($value) . "'";
                            }, $row);
                            fwrite($handle, "INSERT INTO `{$tableName}` VALUES (" . implode(',', $values) . ");\n");
                        }
                    }
                }
            }
            
            fclose($handle);
            $backupSize = filesize($backupFile);
            $logs[] = "Backup file created: " . basename($backupFile);
            
            // Compress backup
            if ($backupSize > 0) {
                $compressed = gzcompressFile($backupFile);
                if ($compressed) {
                    unlink($backupFile); // Remove uncompressed file
                    $backupSize = filesize($backupFile . '.gz');
                    $logs[] = "Backup file compressed";
                }
            }
            
            // Record in database
            $db->executeQuery(
                "INSERT INTO backups (id, size, status, description, encrypted, compressed, created_by) 
                 VALUES (?, ?, 'completed', ?, ?, ?, ?)",
                [$backupId, $backupSize, 'Manual backup', false, true, $_SESSION['user_id']]
            );
            
            $logs[] = "Backup recorded in database";
            
            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'logs' => $logs
            ];
        } else {
            throw new Exception("Could not create backup file");
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function scheduleBackup($db) {
    try {
        $scheduleType = $_POST['schedule_type'] ?? '';
        $description = $_POST['description'] ?? '';
        $notifyOnCompletion = isset($_POST['notify_on_completion']);

        if ($scheduleType === 'once') {
            $scheduledAt = $_POST['scheduled_at'] ?? '';
            if (empty($scheduledAt)) {
                throw new Exception('Scheduled date and time is required');
            }

            $db->executeQuery(
                "INSERT INTO backup_schedules (type, scheduled_at, description, notify_on_completion, created_by) 
                 VALUES ('once', ?, ?, ?, ?)",
                [$scheduledAt, $description, $notifyOnCompletion, $_SESSION['user_id']]
            );
        } else {
            $frequency = $_POST['frequency'] ?? '';
            $scheduleTime = $_POST['schedule_time'] ?? '';
            
            if (empty($frequency) || empty($scheduleTime)) {
                throw new Exception('Frequency and time are required');
            }

            $scheduleData = [
                'frequency' => $frequency,
                'time' => $scheduleTime
            ];

            // Add day for monthly backups
            if ($frequency === 'monthly') {
                $scheduleData['day'] = $_POST['schedule_day'] ?? 1;
            }
            
            // Add weekday for weekly backups
            if ($frequency === 'weekly') {
                $scheduleData['weekday'] = $_POST['schedule_weekday'] ?? 1;
            }

            $db->executeQuery(
                "INSERT INTO backup_schedules (type, schedule_data, description, notify_on_completion, created_by) 
                 VALUES ('recurring', ?, ?, ?, ?)",
                [json_encode($scheduleData), $description, $notifyOnCompletion, $_SESSION['user_id']]
            );
        }

        return [
            'success' => true,
            'message' => 'Backup scheduled successfully'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function verifyBackup($db, $backupId) {
    try {
        $backup = $db->fetchOne("SELECT * FROM backups WHERE id = ?", [$backupId]);
        if (!$backup) {
            throw new Exception('Backup not found');
        }

        $backupFile = __DIR__ . '/../backups/' . $backupId . '.sql.gz';
        if (!file_exists($backupFile)) {
            throw new Exception('Backup file not found');
        }

        // Verify file integrity
        $handle = gzopen($backupFile, 'r');
        if (!$handle) {
            throw new Exception('Failed to open backup file');
        }

        $isValid = true;
        $error = null;

        while (!gzeof($handle)) {
            $line = gzgets($handle);
            if ($line === false) {
                $isValid = false;
                $error = 'File corruption detected';
                break;
            }
        }
        gzclose($handle);

        // Update verification status
        $db->executeQuery(
            "UPDATE backups SET verify_result = ?, verify_at = NOW() WHERE id = ?",
            [$isValid ? 'valid' : 'invalid', $backupId]
        );

        return [
            'success' => true,
            'message' => $isValid ? 'Backup verified successfully' : 'Backup verification failed: ' . $error
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function deleteBackup($db, $backupId) {
    try {
        $backup = $db->fetchOne("SELECT * FROM backups WHERE id = ?", [$backupId]);
        if (!$backup) {
            throw new Exception('Backup not found');
        }

        // Delete physical file
        $backupFile = __DIR__ . '/../backups/' . $backupId . '.sql.gz';
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }

        // Update database record
        $db->executeQuery(
            "UPDATE backups SET status = 'deleted', deleted_at = NOW(), deleted_by = ? WHERE id = ?",
            [$_SESSION['user_id'], $backupId]
        );

        return [
            'success' => true,
            'message' => 'Backup deleted successfully'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function downloadBackup($db, $backupId) {
    try {
        $backup = $db->fetchOne("SELECT * FROM backups WHERE id = ?", [$backupId]);
        if (!$backup) {
            throw new Exception('Backup not found');
        }

        $backupFile = __DIR__ . '/../backups/' . $backupId . '.sql.gz';
        if (!file_exists($backupFile)) {
            throw new Exception('Backup file not found');
        }

        // Set headers for download
        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
        header('Content-Length: ' . filesize($backupFile));
        header('Cache-Control: no-cache');
        
        // Output file
        readfile($backupFile);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

function gzcompressFile($source) {
    $dest = $source . '.gz';
    $mode = 'wb9';
    $error = false;

    if ($fp_out = gzopen($dest, $mode)) {
        if ($fp_in = fopen($source, 'rb')) {
            while (!feof($fp_in)) {
                gzwrite($fp_out, fread($fp_in, 1024 * 512));
            }
            fclose($fp_in);
        } else {
            $error = true;
        }
        gzclose($fp_out);
    } else {
        $error = true;
    }
    
    return !$error;
}
?> 