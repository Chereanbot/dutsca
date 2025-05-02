<?php
class Database {
    private $host = "localhost";
    private $db_name = "dutsca";
    private $username = "root";
    private $password = "";
    private $conn = null;

    // Get database connection
    public function getConnection() {
        try {
            if ($this->conn === null) {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password
                );
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->conn->exec("SET NAMES utf8");
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    // Close database connection
    public function closeConnection() {
        $this->conn = null;
    }

    // Helper function to execute queries
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw new Exception("Database query failed. Please try again later.");
        }
    }

    // Helper function to fetch a single row
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->executeQuery($sql, $params);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("Fetch Error: " . $e->getMessage());
            throw new Exception("Failed to fetch data. Please try again later.");
        }
    }

    // Helper function to fetch all rows
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->executeQuery($sql, $params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("Fetch Error: " . $e->getMessage());
            throw new Exception("Failed to fetch data. Please try again later.");
        }
    }

    // Helper function to get last inserted ID
    public function getLastInsertId() {
        return $this->getConnection()->lastInsertId();
    }

    // Helper function to start a transaction
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }

    // Helper function to commit a transaction
    public function commit() {
        return $this->getConnection()->commit();
    }

    // Helper function to rollback a transaction
    public function rollback() {
        return $this->getConnection()->rollBack();
    }
}

// Create a function to get database instance
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}
?> 