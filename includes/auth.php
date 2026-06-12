<?php
/**
 * Authentication Middleware Check
 * Included on admin pages to verify the user is logged in.
 */

require_once __DIR__ . '/../classes/Authentication.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is properly logged in and session hasn't been hijacked
if (!Authentication::checkSessionValidity()) {
    // Clear compromised or empty session
    $_SESSION = array();
    session_destroy();
    
    // Redirect securely back to login root
    header("Location: ../login.php");
    exit;
}

/**
 * Access Control Helper
 * Usage: authorizeRoles(['Super Admin', 'Secretary']);
 * @param array $allowedRoles
 */
function authorizeRoles($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        // Log unauthorized access attempts
        error_log("Unauthorized Access Attempt: User ID " . ($_SESSION['user_id'] ?? 'Unknown') . " tried accessing a restricted module.");
        
        // Show an elegant access-denied state instead of a blank screen
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Access Denied</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        </head>
        <body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
            <div class="container text-center" style="max-width: 500px;">
                <div class="card border-0 shadow-lg p-4 rounded-4">
                    <div class="text-danger mb-3" style="font-size: 3.5rem;">
                        <i class="bi bi-shield-slash"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Access Restrained</h3>
                    <p class="text-muted">You do not have the required permissions to view this administrative module.</p>
                    <div class="mt-4">
                        <a href="../../admin/dashboard.php" class="btn btn-primary px-4"><i class="bi bi-arrow-left me-2"></i>Return to Dashboard</a>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        exit;
    }
}
?>