<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../include/Logger.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $health = [
        'cpu_usage' => 0,
        'memory_usage' => 0,
        'disk_usage' => 0
    ];

    // Get CPU usage
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $health['cpu_usage'] = round($load[0] * 100, 1);
    }

    // Get memory usage
    $health['memory_usage'] = round(memory_get_usage(true) / memory_get_peak_usage(true) * 100, 1);

    // Get disk usage
    $health['disk_usage'] = round((disk_total_space("/") - disk_free_space("/")) / disk_total_space("/") * 100, 2);

    echo json_encode([
        'success' => true,
        'data' => $health
    ]);

} catch (Exception $e) {
    error_log('System Health Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get system health metrics'
    ]);
}
?> 