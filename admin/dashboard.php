<?php

require '../includes/auth.php';

?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>

<h1>Dashboard</h1>

<p>
    Welcome,
    <?= htmlspecialchars($_SESSION['fullname']) ?>
</p>

<p>
    Role:
    <?= htmlspecialchars($_SESSION['role']) ?>
</p>

<a href="../auth/logout.php">
    Logout
</a>

</body>
</html>