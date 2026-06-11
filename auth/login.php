<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../admin/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Barangay Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<style>

body{
    min-height:100vh;
    background:#f5f7fa;
}

.login-container{
    min-height:100vh;
}

.login-card{
    border:none;
    border-radius:20px;
    box-shadow:0 0.5rem 1rem rgba(0,0,0,.15);
}

.system-title{
    font-weight:700;
}

</style>
</head>
<body>
<div class="container login-container d-flex justify-content-center align-items-center">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
        <div class="card login-card">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h2 class="system-title">
                        Barangay Management System
                    </h2>
                    <p class="text-muted mb-0">
                        Administrator Login
                    </p>
                </div>
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        Invalid username or password.
                    </div>
                <?php endif; ?>
                <form action="process_login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">
                            Username
                        </label>
                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            required
                        >
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Password
                        </label>
                        <div class="input-group">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control"
                                required
                            >
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                id="togglePassword"
                            >
                                Show
                            </button>
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Login
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const passwordField =
document.getElementById('password');
const toggleButton =
document.getElementById('togglePassword');

toggleButton.addEventListener('click', () => {
    if(passwordField.type === 'password')
    {
        passwordField.type = 'text';
        toggleButton.textContent = 'Hide';
    }
    else
    {
        passwordField.type = 'password';
        toggleButton.textContent = 'Show';
    }
});
</script>
</body>
</html>