<?php

session_start();

require '../classes/Database.php';
require '../classes/Authentication.php';

$database = new Database();
$conn = $database->connect();

$auth = new Authentication($conn);

$username = trim($_POST['username']);
$password = $_POST['password'];

if ($auth->login($username, $password)) {
    header("Location: ../admin/dashboard.php");
    exit;

}

header("Location: login.php?error=1");
exit;