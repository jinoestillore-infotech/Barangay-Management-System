<?php

class Database
{
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "barangay_management_system";

    public $conn;

    public function connect()
    {
        $this->conn = new mysqli(
            $this->host,
            $this->username,
            $this->password,
            $this->database
        );
        if ($this->conn->connect_error) {
            die(
                "Connection Failed: " .
                $this->conn->connect_error
            );
        }
        $this->conn->set_charset("utf8mb4");
        return $this->conn;
    }
}