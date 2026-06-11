<?php

class User
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $result = $this->conn->query("
            SELECT *
            FROM users
            ORDER BY fullname
        ");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt
            ->get_result()
            ->fetch_assoc();
    }
}