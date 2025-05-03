<?php
// This script is designed to be run from the command line
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Parse command line arguments
$options = getopt('', ['schedule-id:', 'description:', 'notify:']);
$scheduleId = $options['schedule-id'] ?? null;
$description = $options['description'] ?? 'Scheduled backup';
$notify = isset($options['notify']) ? filter_var($options['notify'], FILTER_VALIDATE_BOOLEAN) : true;

try {
    $db = getDB();
    
    // Start logging
    $logs = [];
    $logs[] = date('Y-m-d H:i:s') . " - Starting scheduled backup...";
    
    // Get schedule details if schedule ID is provided
    if ($scheduleId) {
        $schedule = $db->fetchOne("SELECT * FROM backup_schedules WHERE id = ?", [$scheduleId]);
        if ($schedule) {
            $description = $schedule['description'] ?: $description;
            $notify = $schedule['notify_on_completion'];
        }
    }
    
    // Create backup ID
    $backupId = 'backup_' . date('YmdHis') . '_' . substr(uniqid(), -5);
    $logs[] = "Generated backup ID: {$backupId}";
    
    // Create backup directory if it doesn't exist
    $backupDir = __DIR__ . '/../backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
        $logs[] = "Created backup directory";
    }
    
    // Get all tables
    $tables = $db->fetchAll("SHOW TABLES FROM " . DB_NAME);
    $logs[] = "Found " . count($tables) . " tables to backup";
    
    // Create backup file
    $backupFile = $backupDir . '/' . $backupId . '.sql';
    $handle = fopen($backupFile, 'w');
    
    if ($handle) {
        // Write header
        fwrite($handle, "-- DUTSCA Database Backup (Scheduled)\n");
        fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Description: " . $description . "\n\n");
        
        // Backup each table
        foreach ($tables as $tableRow) {
            $tableName = reset($tableRow);
            $logs[] = "Processing table: {$tableName}";
            
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
                unlink($backupFile);
                $backupSize = filesize($backupFile . '.gz');
                $logs[] = "Backup file compressed";
            }
        }
        
        // Record in database
        $db->executeQuery(
            "INSERT INTO backups (id, size, status, description, encrypted, compressed, created_by, created_at) 
             VALUES (?, ?, 'completed', ?, ?, ?, NULL, NOW())",
            [$backupId, $backupSize, $description, false, true]
        );
        
        $logs[] = "Backup recorded in database";
        
        // Clean up old backups based on retention policy
        $retentionDays = 30; // Default retention period
        $configResult = $db->fetchOne("SELECT value FROM settings WHERE name = 'backup_config'");
        if ($configResult) {
            $config = json_decode($configResult['value'], true);
            if (isset($config['retention'])) {
                $retentionDays = (int)$config['retention'];
            }
        }
        
        // Delete old backups
        $oldBackups = $db->fetchAll(
            "SELECT id FROM backups 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
             AND status != 'deleted'",
            [$retentionDays]
        );
        
        foreach ($oldBackups as $oldBackup) {
            $oldBackupFile = $backupDir . '/' . $oldBackup['id'] . '.sql.gz';
            if (file_exists($oldBackupFile)) {
                unlink($oldBackupFile);
            }
            $db->executeQuery(
                "UPDATE backups SET status = 'deleted', deleted_at = NOW() WHERE id = ?",
                [$oldBackup['id']]
            );
            $logs[] = "Deleted old backup: " . $oldBackup['id'];
        }
        
        // Send notification if enabled
        if ($notify) {
            // Get admin users to notify
            $admins = $db->fetchAll(
                "SELECT email FROM users WHERE role = 'superadmin' AND status = 'active'"
            );
            
            foreach ($admins as $admin) {
                // Send email notification
                $to = $admin['email'];
                $subject = "Backup Completed: {$backupId}";
                $message = "A scheduled backup has been completed successfully.\n\n";
                $message .= "Backup ID: {$backupId}\n";
                $message .= "Description: {$description}\n";
                $message .= "Size: " . formatSize($backupSize) . "\n";
                $message .= "Created At: " . date('Y-m-d H:i:s') . "\n";
                
                mail($to, $subject, $message);
                $logs[] = "Notification sent to: {$to}";
            }
        }
        
        // Log success
        $logs[] = "Backup completed successfully";
        writeToLog($logs);
        exit(0);
    } else {
        throw new Exception("Could not create backup file");
    }
} catch (Exception $e) {
    $logs[] = "ERROR: " . $e->getMessage();
    writeToLog($logs);
    exit(1);
}

function writeToLog($logs) {
    $logFile = __DIR__ . '/../logs/backup.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(
        $logFile,
        implode("\n", $logs) . "\n\n",
        FILE_APPEND
    );
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

function formatSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?> 