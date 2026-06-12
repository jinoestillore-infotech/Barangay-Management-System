<?php
/**
 * Database Class
 * Uses PDO for secure, parameterized query execution to prevent SQL Injection.
 */

class Database {
    private $host = "localhost";
    private $db_name = "barangay_management_system";
    private $username = "root";
    private $password = "";
    private $conn = null;

    /**
     * Get the database connection
     * @return PDO|null
     */
    public function connect() {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // Set DSN (Data Source Name)
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            // Configure PDO options for maximum security and reliability
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on SQL errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Log error privately, show user-friendly message
            error_log("Database Connection Error: " . $e->getMessage());
            die("System is temporarily unavailable. Please try again later.");
        }

        return $this->conn;
    }
}
?>