<?php
declare(strict_types=1);

// Secure route guarding (Allows Administrators, Captains, and Secretaries)
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
if (isset($_GET['fetch_blotter'])) {
    header('Content-Type: application/json');
    $caseId = (int)$_GET['fetch_blotter'];
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
        $compType = $_POST['comp_type'];
        $respType = $_POST['resp_type'];

        $complainantId = $compType === 'resident' ? $_POST['complainant_id'] : null;
        $complainantNR = $compType === 'non-resident' ? $_POST['complainant_non_resident'] : null;
        
        $respondentId = $respType === 'resident' ? $_POST['respondent_id'] : null;
        $respondentNR = $respType === 'non-resident' ? $_POST['respondent_non_resident'] : null;

        // Perform basic validations
        if (($compType === 'resident' && empty($complainantId)) || ($compType === 'non-resident' && empty($complainantNR))) {
            $_SESSION['error_flash'] = "Dispute filing failed! Complainant name/profile is required.";
        } elseif (($respType === 'resident' && empty($respondentId)) || ($respType === 'non-resident' && empty($respondentNR))) {
            $_SESSION['error_flash'] = "Dispute filing failed! Respondent name/profile is required.";
        } else {
            $data = [
                'complainant_id' => $complainantId,
                'complainant_non_resident' => $complainantNR,
                'respondent_id' => $respondentId,
                'respondent_non_resident' => $respondentNR,
                'incident_type' => $_POST['incident_type'],
                'incident_date' => $_POST['incident_date'],
                'incident_location' => $_POST['incident_location'],
                'details' => $_POST['details']
            ];

            if ($blotterManager->createBlotter($data, $actorId)) {
                $_SESSION['success_flash'] = "The dispute complaint was successfully filed and registered!";
            } else {
                $_SESSION['error_flash'] = "Failed to register dispute. Please check inputs.";
            }
        }
    }

    // 2. CONCILIATE / UPDATE CASE STATUS
    if (isset($_POST['action']) && $_POST['action'] === 'mediate_case') {
        $caseId = (int)$_POST['case_id'];
        $status = $_POST['status'];
        $settlementDetails = $_POST['settlement_details'];

        if ($blotterManager->updateStatus($caseId, $status, $settlementDetails, $actorId)) {
            $_SESSION['success_flash'] = "Mediation status and settlement parameters updated successfully!";
        } else {
            $_SESSION['error_flash'] = "Failed to process case status modifications.";
        }
    }

    // Redirect back cleanly to maintain table UI lists
    header('Location: index.php');
    exit;
}
?>