<?php
session_start();

require_once 'classes/Database.php';
require_once 'classes/Authentication.php';

// Instantiate classes
$database = new Database();
$conn = $database->connect();
$auth = new Authentication($conn);

// Standardize Inputs
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->login($username, $password);

    switch ($result) {
        case 'SUCCESS':
            header("Location: admin/dashboard.php");
            exit;
            
        case 'LOCKED':
            header("Location: login.php?error=locked");
            exit;
            
        case 'SUSPENDED':
            header("Location: login.php?error=suspended");
            exit;
            
        case 'INACTIVE':
            header("Location: login.php?error=inactive");
            exit;
            
        case 'INVALID':
        default:
            header("Location: login.php?error=invalid");
            exit;
    }
}

// Redirect home on direct GET hits
header("Location: login.php");
exit;