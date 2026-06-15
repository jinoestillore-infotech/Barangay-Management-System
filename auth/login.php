<?php
session_start();

// Redirect if session already exists based on role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Citizen') {
        header("Location: ../citizen/dashboard.php");
    } else {
        header("Location: ../admin/dashboard.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barangay Portal Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons for clean, professional iconography -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Clean, customized CSS stylesheet reference (Going up one level to root) -->
    <link href="../assets/css/login.css" rel="stylesheet">
</head>
<body>

<div class="container login-container d-flex justify-content-center align-items-center py-5">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5 px-2">
        <div class="card login-card">
            <div class="card-body p-4 p-md-5">
                
                <!-- System Header Section (Unified for Officials & Citizens) -->
                <div class="text-center mb-4">
                    <div class="system-logo">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h2 class="system-title mb-1">
                        Barangay Portal
                    </h2>
                    <p class="text-muted small mb-0">
                        E-Services & Administrative Access
                    </p>
                </div>

                <!-- Rich Error Messages Handling -->
                <?php if(isset($_GET['error'])): ?>
                    <?php 
                        $errorType = $_GET['error'];
                        $alertClass = 'alert-danger';
                        $iconClass = 'bi-exclamation-triangle-fill';
                        $errorMessage = 'Invalid username or password.';

                        if ($errorType === 'locked') {
                            $errorMessage = 'This account is temporarily locked for 15 minutes due to multiple failed attempts.';
                        } elseif ($errorType === 'suspended') {
                            $errorMessage = 'Your account has been suspended. Please contact the system administrator.';
                        } elseif ($errorType === 'inactive') {
                            $errorMessage = 'Your account is inactive. Access is limited.';
                        }
                    ?>
                    <div class="alert <?php echo $alertClass; ?> d-flex align-items-center mb-4 border-0 shadow-sm" role="alert">
                        <i class="bi <?php echo $iconClass; ?> me-2 flex-shrink-0"></i>
                        <div class="small fw-medium">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Login -->
                <form action="process_login.php" method="POST">
                    
                    <!-- Username Field -->
                    <div class="mb-3">
                        <label class="form-label text-secondary fw-semibold small">
                            Username / Account ID
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

                <div class="mt-4 text-center">
                    <p class="small text-muted mb-0">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        Citizens can claim their official portal account credentials from the Barangay Hall.
                    </p>
                </div>

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