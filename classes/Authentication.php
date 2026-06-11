<?php

class Authentication
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function login(
        $username,
        $password
    )
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->bind_param(
            "s",
            $username
        );
        $stmt->execute();
        $user = $stmt
            ->get_result()
            ->fetch_assoc();
        if (
            $user &&
            password_verify(
                $password,
                $user['password']
            )
        ) {
            session_regenerate_id(true);
            $_SESSION['user_id']
                = $user['id'];
            $_SESSION['fullname']
                = $user['fullname'];
            $_SESSION['role']
                = $user['role'];
            return true;
        }
        return false;
    }
}