<?php
declare(strict_types=1);

// Secure route guarding (Allows Administrators, Captains, and Secretaries to write modifications)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/BlotterManager.php';

$database = new Database();
$conn = $database->connect();
$blotterManager = new BlotterManager($conn);

$actorId = (int)$_SESSION['user_id'];

// --------------------------------------------------------
// AJAX API ENDPOINTS FOR MODALS
// --------------------------------------------------------
if (isset($_GET['fetch_case'])) {
    header('Content-Type: application/json');
    $caseId = (int)$_GET['fetch_case'];
    $caseData = $blotterManager->getBlotterById($caseId);
    echo json_encode($caseData ? $caseData : ['error' => 'Disputed case details not found']);
    exit;
}

// --------------------------------------------------------
// FORM POST REQUEST PROCESSORS
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. FILE NEW COMPLAINT CASE
    if (isset($_POST['action']) && $_POST['action'] === 'add_blotter') {
        $compType = $_POST['comp_type'] ?? 'resident';
        $respType = $_POST['resp_type'] ?? 'resident';

        // Cleanly assign identifiers or fallbacks based on resident status toggles
        $complainantId = ($compType === 'resident' && !empty($_POST['complainant_id'])) ? (int)$_POST['complainant_id'] : null;
        $complainantNR = ($compType === 'non_resident' && !empty($_POST['complainant_non_resident'])) ? trim($_POST['complainant_non_resident']) : null;
        
        $respondentId = ($respType === 'resident' && !empty($_POST['respondent_id'])) ? (int)$_POST['respondent_id'] : null;
        $respondentNR = ($respType === 'non_resident' && !empty($_POST['respondent_non_resident'])) ? trim($_POST['respondent_non_resident']) : null;

        // Perform validation checks before interacting with BlotterManager
        if (($compType === 'resident' && empty($complainantId)) || ($compType === 'non_resident' && empty($complainantNR))) {
            $_SESSION['error_flash'] = "Dispute filing failed! Complainant credentials or non-resident name must be supplied.";
        } elseif (($respType === 'resident' && empty($respondentId)) || ($respType === 'non_resident' && empty($respondentNR))) {
            $_SESSION['error_flash'] = "Dispute filing failed! Respondent credentials or non-resident name must be supplied.";
        } else {
            $data = [
                'complainant_id' => $complainantId,
                'complainant_non_resident' => $complainantNR,
                'respondent_id' => $respondentId,
                'respondent_non_resident' => $respondentNR,
                'incident_type' => $_POST['incident_type'] ?? 'Others',
                'incident_date' => $_POST['incident_date'] ?? date('Y-m-d H:i:s'),
                'incident_location' => $_POST['incident_location'] ?? '',
                'details' => $_POST['details'] ?? ''
            ];

            if ($blotterManager->createBlotter($data, $actorId)) {
                $_SESSION['success_flash'] = "The dispute incident report has been successfully filed and recorded in the Barangay registry!";
            } else {
                $_SESSION['error_flash'] = "An error occurred inside the system while saving the incident report.";
            }
        }
    }

    // 2. SCHEDULE MEDIATION HEARING
    if (isset($_POST['action']) && $_POST['action'] === 'schedule_hearing') {
        $caseId = (int)$_POST['hearing_case_id'];
        $hearingDate = $_POST['hearing_date'];

        if (empty($hearingDate)) {
            $_SESSION['error_flash'] = "Hearing schedule failed! A valid mediation date and time is required.";
        } else {
            // Store the schedule directly into the settlement_details to prevent breaking strict table constraints
            $formattedSchedule = "Mediation scheduled on: " . date('F d, Y - h:i A', strtotime($hearingDate));
            
            if ($blotterManager->updateStatus($caseId, 'Scheduled for Mediation', $formattedSchedule, $actorId)) {
                $_SESSION['success_flash'] = "Mediation hearing date successfully booked and summons notices generated.";
            } else {
                $_SESSION['error_flash'] = "Failed to register mediation schedule in the system.";
            }
        }
    }

    // 3. LOG CASE RESOLUTION
    if (isset($_POST['action']) && $_POST['action'] === 'resolve_case') {
        $caseId = (int)$_POST['resolve_case_id'];
        $status = $_POST['status'] ?? 'Settled';
        $settlementDetails = $_POST['settlement_details'] ?? '';

        if (empty($settlementDetails)) {
            $_SESSION['error_flash'] = "Resolution logging failed! Detailed documentation of the agreement or referral reasons must be supplied.";
        } else {
            if ($blotterManager->updateStatus($caseId, $status, $settlementDetails, $actorId)) {
                $_SESSION['success_flash'] = "Dispute case successfully resolved and archived as '{$status}'.";
            } else {
                $_SESSION['error_flash'] = "An error occurred while attempting to close this case.";
            }
        }
    }

    // Redirect back cleanly to maintain UI states
    header('Location: index.php');
    exit;
}

// Redirect home on direct GET hit attempts
header("Location: index.php");
exit;
?>