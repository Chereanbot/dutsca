<?php
header('Content-Type: application/json');

// Dummy status data (replace with real checks as needed)
$status = [
    'main_server' => 'Operational',
    'database' => 'Operational',
    'authentication_service' => 'Operational',
    'backup_service' => 'Warning', // e.g., last backup was long ago
    'email_service' => 'Down', // e.g., maintenance
];

echo json_encode($status); 