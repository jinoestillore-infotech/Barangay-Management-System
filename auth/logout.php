<?php

session_start();

require '../classes/Database.php';
require '../classes/Authentication.php';

$database = new Database();
$conn = $database->connect();

$auth = new Authentication($conn);

$auth->logout();

header("Location: login.php");
exit;