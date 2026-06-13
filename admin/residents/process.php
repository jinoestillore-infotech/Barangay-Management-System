<?php
declare(strict_types=1);

// Secure route guarding (Allows Administrators, Captains, and Secretaries to write modifications)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/ResidentManager.php';

$database = new Database();
$conn = $database->connect();
$residentManager = new ResidentManager($conn);

$actorId = (int)$_SESSION['user_id'];

// --------------------------------------------------------
// AJAX API ENDPOINTS FOR MODALS
// --------------------------------------------------------
if (isset($_GET['fetch_resident'])) {
    header('Content-Type: application/json');
    $residentId = (int)$_GET['fetch_resident'];
    $residentData = $residentManager->getResidentById($residentId);
    echo json_encode($residentData ? $residentData : ['error' => 'Resident profile not found']);
    exit;
}

// --------------------------------------------------------
// FORM POST REQUEST PROCESSORS
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. REGISTER NEW RESIDENT PROFILE
    if (isset($_POST['action']) && $_POST['action'] === 'add_resident') {
        $national_id = !empty($_POST['national_id']) ? trim($_POST['national_id']) : null;
        
        // Check unique National ID constraint early
        if ($national_id !== null && $residentManager->isNationalIdTaken($national_id)) {
            $_SESSION['error_flash'] = "Registration failed! National ID (PhilSys Card) '{$national_id}' is already registered to another resident.";
        } else {
            // Process checkboxes cleanly (if checked, they send "1", otherwise not sent)
            $is_voter = isset($_POST['is_voter']) ? 1 : 0;
            $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
            $pwd_type = ($is_pwd === 1 && !empty($_POST['pwd_type'])) ? trim($_POST['pwd_type']) : null;

            $data = [
                'household_id' => !empty($_POST['household_id']) ? (int)$_POST['household_id'] : null,
                'national_id' => $national_id,
                'first_name' => trim($_POST['first_name']),
                'middle_name' => !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null,
                'last_name' => trim($_POST['last_name']),
                'extension_name' => !empty($_POST['extension_name']) ? trim($_POST['extension_name']) : null,
                'birth_date' => $_POST['birth_date'],
                'birth_place' => !empty($_POST['birth_place']) ? trim($_POST['birth_place']) : null,
                'gender' => $_POST['gender'],
                'civil_status' => $_POST['civil_status'],
                'citizenship' => !empty($_POST['citizenship']) ? trim($_POST['citizenship']) : 'Filipino',
                'religion' => !empty($_POST['religion']) ? trim($_POST['religion']) : null,
                'occupation' => !empty($_POST['occupation']) ? trim($_POST['occupation']) : null,
                'is_voter' => $is_voter,
                'is_pwd' => $is_pwd,
                'pwd_type' => $pwd_type,
                'relationship_to_head' => !empty($_POST['relationship_to_head']) ? trim($_POST['relationship_to_head']) : null,
                'contact_number' => !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null,
                'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
                'status' => 'Active'
            ];

            if ($residentManager->createResident($data, $actorId)) {
                $fullName = trim($_POST['first_name'] . ' ' . $_POST['last_name']);
                $_SESSION['success_flash'] = "Resident profile for '{$fullName}' has been successfully registered!";
            } else {
                $_SESSION['error_flash'] = "Failed to complete registration. Check if household head constraint was violated.";
            }
        }
    }

    // 2. MODIFY EXISTING RESIDENT PROFILE
    if (isset($_POST['action']) && $_POST['action'] === 'edit_resident') {
        $residentId = (int)$_POST['resident_id'];
        $national_id = !empty($_POST['national_id']) ? trim($_POST['national_id']) : null;

        // Check unique National ID constraint (excluding current resident)
        if ($national_id !== null && $residentManager->isNationalIdTaken($national_id, $residentId)) {
            $_SESSION['error_flash'] = "Update failed! National ID (PhilSys Card) '{$national_id}' is already registered to another resident.";
        } else {
            $is_voter = isset($_POST['is_voter']) ? 1 : 0;
            $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
            $pwd_type = ($is_pwd === 1 && !empty($_POST['pwd_type'])) ? trim($_POST['pwd_type']) : null;

            $data = [
                'household_id' => !empty($_POST['household_id']) ? (int)$_POST['household_id'] : null,
                'national_id' => $national_id,
                'first_name' => trim($_POST['first_name']),
                'middle_name' => !empty($_POST['middle_name']) ? trim($_POST['middle_name']) : null,
                'last_name' => trim($_POST['last_name']),
                'extension_name' => !empty($_POST['extension_name']) ? trim($_POST['extension_name']) : null,
                'birth_date' => $_POST['birth_date'],
                'birth_place' => !empty($_POST['birth_place']) ? trim($_POST['birth_place']) : null,
                'gender' => $_POST['gender'],
                'civil_status' => $_POST['civil_status'],
                'citizenship' => !empty($_POST['citizenship']) ? trim($_POST['citizenship']) : 'Filipino',
                'religion' => !empty($_POST['religion']) ? trim($_POST['religion']) : null,
                'occupation' => !empty($_POST['occupation']) ? trim($_POST['occupation']) : null,
                'is_voter' => $is_voter,
                'is_pwd' => $is_pwd,
                'pwd_type' => $pwd_type,
                'relationship_to_head' => !empty($_POST['relationship_to_head']) ? trim($_POST['relationship_to_head']) : null,
                'contact_number' => !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null,
                'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
                'status' => 'Active' // Keeps Active during standard data updates
            ];

            if ($residentManager->updateResident($residentId, $data, $actorId)) {
                $_SESSION['success_flash'] = "Profile updates for ID #{$residentId} have been saved successfully.";
            } else {
                $_SESSION['error_flash'] = "Failed to update profile. Check if household head constraint was violated.";
            }
        }
    }

    // 3. QUICK VITAL STATUS CHANGES (Mark Deceased, Moved Out, Active)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_vital_status') {
        $residentId = (int)$_POST['resident_id'];
        $newStatus = trim($_POST['status']);

        if ($residentManager->updateVitalStatus($residentId, $newStatus, $actorId)) {
            $_SESSION['success_flash'] = "Vital status changed successfully.";
        } else {
            $_SESSION['error_flash'] = "Unable to update vital status.";
        }
    }

    // Redirect back cleanly to maintain directory state
    header('Location: index.php');
    exit;
}
?>