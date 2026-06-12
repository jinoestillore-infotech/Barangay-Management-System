<?php

require '../includes/auth.php';

$pageTitle = "Dashboard";

include '../includes/header.php';

?>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 p-0">
            <?php include '../includes/sidebar.php'; ?>
        </div>
        <div class="col-lg-10 p-4">
            <h2>Dashboard</h2>
            <p>
                Welcome,
                <?= htmlspecialchars($_SESSION['fullname']) ?>
            </p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>