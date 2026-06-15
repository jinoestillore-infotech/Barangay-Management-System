<?php
declare(strict_types=1);

/**
 * Citizen Portal Process Controller
 * Securely handles citizen form submissions for document requests and incident report filings.
 */

require_once '../includes/auth.php';
authorizeRoles(['Citizen']); // Strictly restrict access to users assigned the 'Citizen' role

require_once '../classes/Database.php';

// Instantiate the database connection
$database = new Database();
$conn = $database->connect();

$userId = (int)$_SESSION['user_id'];
$residentId = $_SESSION['resident_id'] ? (int)$_SESSION['resident_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $residentId !== null) {
    
    // 1. PROCESS DOCUMENT REQUEST
    if (isset($_POST['action']) && $_POST['action'] === 'request_clearance') {
        $certType = $_POST['certificate_type'] ?? '';
        $purpose = trim($_POST['purpose'] ?? '');

        // Whitelist validation
        $allowedCerts = [
            'Barangay Clearance',
            'Certificate of Residency',
            'Certificate of Indigency',
            'Business Clearance',
            'Certificate of Good Moral Character'
        ];

        if (!in_array($certType, $allowedCerts, true) || empty($purpose)) {
            $_SESSION['error_flash'] = "Failed to file request. Please select a valid document type and specify your purpose.";
        } else {
            try {
                // Generate a unique 16-character alphanumeric verification token
                $verificationToken = strtoupper(bin2hex(random_bytes(8)));

                // Default issued_by to 1 (System Admin) as a temporary pending handler, status to 'Pending'
                $query = "INSERT INTO certificates (resident_id, certificate_type, purpose, issued_by, status, verification_token) 
                          VALUES (:res_id, :cert_type, :purpose, 1, 'Pending', :token)";
                
                $stmt = $conn->prepare($query);
                $stmt->bindValue(':res_id', $residentId, PDO::PARAM_INT);
                $stmt->bindValue(':cert_type', $certType, PDO::PARAM_STR);
                $stmt->bindValue(':purpose', strip_tags($purpose), PDO::PARAM_STR);
                $stmt->bindValue(':token', $verificationToken, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $_SESSION['success_flash'] = "Your request for a '{$certType}' has been submitted successfully! Track its status in your dashboard below.";
                } else {
                    $_SESSION['error_flash'] = "Unable to process document request. Database communication failed.";
                }
            } catch (Exception $e) {
                error_log("Citizen request error: " . $e->getMessage());
                $_SESSION['error_flash'] = "System error occurred. Please try again later.";
            }
        }
        header("Location: dashboard.php");
        exit;
    }

    // 2. PROCESS INCIDENT / BLOTTER REPORT FILING
    if (isset($_POST['action']) && $_POST['action'] === 'file_blotter') {
        $incidentType = $_POST['incident_type'] ?? '';
        $incidentDate = $_POST['incident_date'] ?? '';
        $location = trim($_POST['incident_location'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $respondent = trim($_POST['respondent_non_resident'] ?? '');

        if (empty($incidentType) || empty($incidentDate) || empty($location) || empty($details)) {
            $_SESSION['error_flash'] = "Incident report rejected. All fields except the respondent name are strictly required.";
        } else {
            try {
                // Auto-generate sequential case code: KP-YYYY-XXXX
                $year = date('Y');
                $countQuery = "SELECT COUNT(id) as total FROM blotter_records WHERE YEAR(created_at) = :year";
                $countStmt = $conn->prepare($countQuery);
                $countStmt->bindValue(':year', $year, PDO::PARAM_STR);
                $countStmt->execute();
                $seqCount = ($countStmt->fetch()['total'] ?? 0) + 1;
                $caseNumber = sprintf("KP-%s-%04d", $year, $seqCount);

                // Insert into blotter_records with status 'Active'
                $query = "INSERT INTO blotter_records (
                            case_number, complainant_id, respondent_non_resident, 
                            incident_type, incident_date, incident_location, details, status, recorded_by
                          ) VALUES (
                            :case_number, :complainant_id, :respondent, 
                            :incident_type, :incident_date, :location, :details, 'Active', 1
                          )";
                
                $stmt = $conn->prepare($query);
                $stmt->bindValue(':case_number', $caseNumber, PDO::PARAM_STR);
                $stmt->bindValue(':complainant_id', $residentId, PDO::PARAM_INT);
                $stmt->bindValue(':respondent', !empty($respondent) ? strip_tags($respondent) : 'Unknown Respondent', PDO::PARAM_STR);
                $stmt->bindValue(':incident_type', strip_tags($incidentType), PDO::PARAM_STR);
                $stmt->bindValue(':incident_date', $incidentDate, PDO::PARAM_STR);
                $stmt->bindValue(':location', strip_tags($location), PDO::PARAM_STR);
                $stmt->bindValue(':details', strip_tags($details), PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $_SESSION['success_flash'] = "Incident report successfully filed! Case assigned Case #{$caseNumber}.";
                } else {
                    $_SESSION['error_flash'] = "Unable to file incident report. Please verify parameters.";
                }
            } catch (Exception $e) {
                error_log("Citizen filing error: " . $e->getMessage());
                $_SESSION['error_flash'] = "Internal system failure while submitting report.";
            }
        }
        header("Location: dashboard.php");
        exit;
    }
}

// Redirect back home on direct access or mismatch conditions
header("Location: dashboard.php");
exit;
?>