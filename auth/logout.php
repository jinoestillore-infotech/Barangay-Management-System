<?php
session_start();

// Corrected relative paths to access classes
require_once '../classes/Database.php';
require_once '../classes/Authentication.php';

$database = new Database();
$conn = $database->connect();
$auth = new Authentication($conn);

// Securely destroy the session
$auth->logout();

// Redirect back to login interface in the same directory
header("Location: login.php");
exit;
?>