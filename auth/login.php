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
    <title>Barangay Management System - Admin Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons for clean, professional iconography -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/login.css" rel="stylesheet">
    
</head>
<body>

<div class="container login-container d-flex justify-content-center align-items-center py-5">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5 px-2">
        <div class="card login-card">
            <div class="card-body p-4 p-md-5">
                
                <!-- System Header Section -->
                <div class="text-center mb-4">
                    <div class="system-logo">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h2 class="system-title mb-1">
                        Barangay Management System
                    </h2>
                    <p class="text-muted small mb-0">
                        Administrator Access Console
                    </p>
                </div>

                <!-- Error Message Alert -->
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                        <div class="small">
                            Invalid username or password.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Login (Untouched backend hooks) -->
                <form action="process_login.php" method="POST">
                    
                    <!-- Username Field -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold small">
                            Username
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input
                                type="text"
                                name="username"
                                class="form-control"
                                placeholder="Enter your username"
                                required
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="mb-4">
                        <label class="form-label text-secondary fw-semibold small">
                            Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control border-end-0"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button
                                type="button"
                                class="btn btn-outline-secondary border-start-0"
                                id="togglePassword"
                                style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;"
                            >
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Login Button -->
                    <button
                        type="submit"
                        class="btn btn-primary w-100 shadow-sm"
                    >
                        Sign In <i class="bi bi-arrow-right-short ms-1"></i>
                    </button>
                </form>

                <!-- Clean Footer Note -->
                <div class="text-center footer-text">
                    &copy; <?php echo date("Y"); ?> Barangay Management System. All rights reserved.
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Password Visibility Toggle Logic -->
<script>
const passwordField = document.getElementById('password');
const toggleButton = document.getElementById('togglePassword');
const toggleIcon = document.getElementById('toggleIcon');

toggleButton.addEventListener('click', () => {
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('bi-eye');
        toggleIcon.classList.add('bi-eye-slash');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('bi-eye-slash');
        toggleIcon.classList.add('bi-eye');
    }
});
</script>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>