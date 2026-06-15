<?php
// Secure route guarding (Ensures only Administrators can write modifications)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator']);

require_once '../../classes/Database.php';
require_once '../../classes/UserManager.php';

$database = new Database();
$conn = $database->connect();
$userManager = new UserManager($conn);

$actorId = $_SESSION['user_id'];

// --------------------------------------------------------
// AJAX API ENDPOINTS FOR MODALS
// --------------------------------------------------------
if (isset($_GET['fetch_user'])) {
    header('Content-Type: application/json');
    $userData = $userManager->getUserById((int)$_GET['fetch_user']);
    echo json_encode($userData ? $userData : ['error' => 'User not found']);
    exit;
}

// --------------------------------------------------------
// FORM POST REQUEST PROCESSORS
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW SYSTEM USER
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $fullname = trim($_POST['fullname']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $status = $_POST['status'];

        // Grab dynamic resident_id if selected as a Citizen
        $residentId = ($role === 'Citizen' && !empty($_POST['resident_id'])) ? (int)$_POST['resident_id'] : null;
        
        if ($userManager->isUsernameTaken($username)) {
            $_SESSION['error_flash'] = "Registration failed! The username '{$username}' is already in use.";
        } else {
            $userData = [
                'fullname' => $fullname,
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'status' => $status,
                'resident_id' => $residentId
            ];
            if ($userManager->createUser($userData, $actorId)) {
                $_SESSION['success_flash'] = "System staff account '{$fullname}' was successfully created!";
            } else {
                $_SESSION['error_flash'] = "Unable to process registration. Please double-check input types.";
            }
        }
    }

    // 2. EDIT EXISTING USER
    if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
        $userId = (int)$_POST['edit_user_id'];
        $fullname = trim($_POST['edit_fullname']);
        $role = $_POST['edit_role'];
        $status = $_POST['edit_status'];
        $password = $_POST['edit_password']; // Optional

        // Grab dynamic edit resident_id
        $residentId = ($role === 'Citizen' && !empty($_POST['edit_resident_id'])) ? (int)$_POST['edit_resident_id'] : null;

        $updateData = [
            'fullname' => $fullname,
            'role' => $role,
            'status' => $status,
            'password' => $password,
            'resident_id' => $residentId
        ];

        if ($userManager->updateUser($userId, $updateData, $actorId)) {
            $_SESSION['success_flash'] = "Account for {$fullname} has been successfully modified.";
        } else {
            $_SESSION['error_flash'] = "Unable to update account details. Please try again.";
        }
    }

    // 3. QUICK STATUS OR LOCKOUT CHANGES
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $userId = (int)$_POST['status_user_id'];
        $newStatus = $_POST['new_status'];
        if ($userManager->updateStatus($userId, $newStatus, $actorId)) {
            $_SESSION['success_flash'] = "Status updated successfully.";
        } else {
            $_SESSION['error_flash'] = "Unable to update status.";
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'unlock_account') {
        $userId = (int)$_POST['unlock_user_id'];
        if ($userManager->unlockAccount($userId, $actorId)) {
            $_SESSION['success_flash'] = "Account unlocked. Failed attempt counters have been reset.";
        } else {
            $_SESSION['error_flash'] = "Unable to unlock account.";
        }
    }

    // Redirect back cleanly to maintain UI state
    header('Location: index.php');
    exit;
}