<?php
// Secure route guarding (Ensures only authenticated officers manage data writes)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/HouseholdManager.php';

$database = new Database();
$conn = $database->connect();
$householdManager = new HouseholdManager($conn);

$actorId = $_SESSION['user_id'];

// --------------------------------------------------------
// AJAX API ENDPOINTS FOR MODALS
// --------------------------------------------------------
if (isset($_GET['fetch_household'])) {
    header('Content-Type: application/json');
    $household = $householdManager->getHouseholdById((int)$_GET['fetch_household']);
    echo json_encode($household ? $household : ['error' => 'Household record not found']);
    exit;
}

if (isset($_GET['fetch_members'])) {
    header('Content-Type: application/json');
    $members = $householdManager->getHouseholdMembers((int)$_GET['fetch_members']);
    echo json_encode($members);
    exit;
}

// --------------------------------------------------------
// FORM POST REQUEST PROCESSORS
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. REGISTER NEW HOUSEHOLD
    if (isset($_POST['action']) && $_POST['action'] === 'add_household') {
        $household_number = trim($_POST['household_number']);
        $street = trim($_POST['street']);
        $zone_purok = $_POST['zone_purok'];
        $income_bracket = $_POST['income_bracket'];

        if ($householdManager->isHouseholdNumberTaken($household_number)) {
            $_SESSION['error_flash'] = "Registration failed! Household Number '{$household_number}' is already registered.";
        } else {
            $data = [
                'household_number' => $household_number,
                'street' => $street,
                'zone_purok' => $zone_purok,
                'income_bracket' => $income_bracket
            ];
            if ($householdManager->createHousehold($data, $actorId)) {
                $_SESSION['success_flash'] = "Household {$household_number} has been successfully registered!";
            } else {
                $_SESSION['error_flash'] = "Failed to create household. Please check input parameters.";
            }
        }
    }

    // 2. MODIFY EXISTING HOUSEHOLD
    if (isset($_POST['action']) && $_POST['action'] === 'edit_household') {
        $householdId = (int)$_POST['edit_household_id'];
        $household_number = trim($_POST['edit_household_number']);
        $street = trim($_POST['edit_street']);
        $zone_purok = $_POST['edit_zone_purok'];
        $income_bracket = $_POST['edit_income_bracket'];

        if ($householdManager->isHouseholdNumberTaken($household_number, $householdId)) {
            $_SESSION['error_flash'] = "Update failed! Household Number '{$household_number}' is already taken by another household.";
        } else {
            $data = [
                'household_number' => $household_number,
                'street' => $street,
                'zone_purok' => $zone_purok,
                'income_bracket' => $income_bracket
            ];
            if ($householdManager->updateHousehold($householdId, $data, $actorId)) {
                $_SESSION['success_flash'] = "Household ID #{$householdId} details updated successfully.";
            } else {
                $_SESSION['error_flash'] = "Failed to update household details.";
            }
        }
    }
    
    // Redirect back cleanly to clear submission states
    header('Location: index.php');
    exit;
}