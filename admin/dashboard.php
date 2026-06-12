<?php

require '../includes/auth.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<style>

body{
    background:#f5f7fa;
}

.sidebar{
    min-height:100vh;
    background:#212529;
}

.sidebar a{
    color:#fff;
    text-decoration:none;
    display:block;
    padding:.75rem 1rem;
    border-radius:.5rem;
}

.sidebar a:hover{
    background:#343a40;
}

.stat-card{
    border:none;
    border-radius:1rem;
    box-shadow:0 .125rem .25rem rgba(0,0,0,.075);
}

</style>

</head>
<body>

<div class="container-fluid">

    <div class="row">

        <div class="col-lg-2 sidebar p-3">

            <h4 class="text-white mb-4">
                BMS
            </h4>

            <a href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>

            <a href="#">
                <i class="bi bi-people"></i>
                Users
            </a>

            <a href="#">
                <i class="bi bi-person-vcard"></i>
                Residents
            </a>

            <a href="#">
                <i class="bi bi-house"></i>
                Households
            </a>

            <a href="#">
                <i class="bi bi-file-earmark-text"></i>
                Certificates
            </a>

            <a href="#">
                <i class="bi bi-exclamation-triangle"></i>
                Blotter
            </a>

            <a href="../auth/logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>

        </div>

        <div class="col-lg-10 p-4">

            <h2>
                Dashboard
            </h2>

            <p>
                Welcome,
                <?= htmlspecialchars($_SESSION['fullname']) ?>
            </p>

            <div class="row g-3">

                <div class="col-md-3">

                    <div class="card stat-card">

                        <div class="card-body">

                            <h6>Total Residents</h6>

                            <h3>0</h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card stat-card">

                        <div class="card-body">

                            <h6>Certificates Issued</h6>

                            <h3>0</h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card stat-card">

                        <div class="card-body">

                            <h6>Blotter Records</h6>

                            <h3>0</h3>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card stat-card">

                        <div class="card-body">

                            <h6>Users</h6>

                            <h3>1</h3>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>