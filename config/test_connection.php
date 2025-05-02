<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = getDB();
    $connection = $db->getConnection();
    echo "<div style='color: green; font-family: Arial, sans-serif; padding: 20px;'>";
    echo "<h2>✅ Database Connection Successful!</h2>";
    echo "<p><strong>Database Name:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
    echo "<p><strong>Connection Status:</strong> Connected</p>";
    
    // Test query to check if tables exist
    $tables = $db->fetchAll("SHOW TABLES");
    echo "<h3>Existing Tables:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>" . current($table) . "</li>";
    }
    echo "</ul>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; padding: 20px;'>";
    echo "<h2>❌ Database Connection Failed!</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Please check:</strong></p>";
    echo "<ul>";
    echo "<li>XAMPP is running (MySQL service is started)</li>";
    echo "<li>Database 'dutsca' exists</li>";
    echo "<li>Database credentials are correct</li>";
    echo "</ul>";
    echo "</div>";
}
?> 