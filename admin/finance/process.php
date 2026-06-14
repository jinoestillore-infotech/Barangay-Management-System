<?php
declare(strict_types=1);

// Secure route guarding (Ensures only Administrators, Captains, and Treasurers process financials)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Treasurer']);

require_once '../../classes/Database.php';
require_once '../../classes/PaymentManager.php';

$database = new Database();
$conn = $database->connect();
$paymentManager = new PaymentManager($conn);

$actorId = (int)$_SESSION['user_id'];

// --------------------------------------------------------
// FORM POST REQUEST PROCESSOR
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. FILE NEW CASHERING ENTRY (RECEIPT LOGGING)
    if (isset($_POST['action']) && $_POST['action'] === 'add_payment') {
        $or_number = trim($_POST['or_number']);

        if ($paymentManager->isOrNumberTaken($or_number)) {
            $_SESSION['error_flash'] = "Transaction failed! Official Receipt Number (O.R. #) '{$or_number}' has already been processed and recorded.";
        } else {
            // FIXED: Changed formatting to Y-m-d H:i:s to preserve hour and minute timestamps in the database
            $data = [
                'or_number' => $or_number,
                'resident_id' => !empty($_POST['resident_id']) ? (int)$_POST['resident_id'] : null,
                'payer_name' => !empty($_POST['payer_name']) ? trim($_POST['payer_name']) : null,
                'payment_for' => $_POST['purpose'], 
                'amount' => $_POST['amount'],
                'payment_date' => !empty($_POST['payment_date']) ? date('Y-m-d H:i:s', strtotime($_POST['payment_date'])) : date('Y-m-d H:i:s')
            ];

            if ($paymentManager->createPayment($data, $actorId)) {
                $_SESSION['success_flash'] = "O.R. #{$or_number} issued and filed in the financial registry successfully.";
            } else {
                $_SESSION['error_flash'] = "An error occurred inside the database. Payment could not be recorded.";
            }
        }
    }
    
    // Redirect back cleanly to clear raw POST submissions
    header('Location: index.php');
    exit;
}

// Redirect home on direct GET attempts
header("Location: index.php");
exit;
?>