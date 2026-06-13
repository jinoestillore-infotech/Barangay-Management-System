<?php
// Secure route guarding (Allows Administrators, Captains, and Secretaries)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/BlotterManager.php';

$database = new Database();
$conn = $database->connect();
$blotterManager = new BlotterManager($conn);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'summons'; // 'summons' or 'certification'

$case = $blotterManager->getBlotterById($id);

if (!$case) {
    die("Error: Case record not found.");
}

// Format Name Helpers
$complainant = $case['complainant_non_resident'] ? $case['complainant_non_resident'] : $case['resident_complainant'];
$respondent = $case['respondent_non_resident'] ? $case['respondent_non_resident'] : $case['resident_respondent'];
$hearing_display = $case['incident_date'] ? date('F d, Y \a\t g:i A', strtotime($case['incident_date'])) : 'N/A';
$scheduled_display = $case['incident_date'] ? date('F d, Y \a\t g:i A', strtotime($case['incident_date'])) : 'N/A'; // Hearing date fallback
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Document - Case #<?php echo htmlspecialchars($case['case_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Georgia', 'Times New Roman', serif;
            color: #1e293b;
        }
        .print-canvas {
            background: #ffffff;
            width: 8.5in;
            min-height: 11in;
            padding: 1in;
            margin: 2rem auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
        }
        .header-seal {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.4;
        }
        .doc-title {
            font-family: 'Arial', sans-serif;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2rem;
            margin-bottom: 2rem;
            text-decoration: underline;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 250px;
            margin-top: 3rem;
            text-align: center;
            font-weight: bold;
        }
        @media print {
            body {
                background-color: #ffffff;
                margin: 0;
            }
            .print-canvas {
                width: 100%;
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            .btn-print-ctrl {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Interactive Control Panel (Floating) -->
<div class="container text-center my-3 btn-print-ctrl">
    <button onclick="window.print();" class="btn btn-dark shadow-sm px-4 me-2">
        <i class="bi bi-printer-fill me-2"></i> Print Document
    </button>
    <button onclick="window.close();" class="btn btn-outline-secondary px-4">
        Close Window
    </button>
</div>

<div class="print-canvas">
    <!-- Republic of the Philippines Header -->
    <div class="text-center header-seal">
        <div>Republic of the Philippines</div>
        <div>Province of Central Visayas</div>
        <div class="fw-bold text-uppercase">Municipality of Inabanga</div>
        <div class="fw-bold text-uppercase text-primary" style="font-size: 1.1rem;">Barangay Saa</div>
        <div class="mt-2 text-muted" style="font-size: 0.75rem; font-style: italic;">Office of the Lupon Tagapamayapa</div>
        <hr class="my-3" style="border-top: 2px double #000;">
    </div>

    <!-- Case Meta Block -->
    <div class="row mt-4">
        <div class="col-6">
            <div><strong>Complainant(s):</strong></div>
            <div class="ps-3 text-uppercase fw-bold"><?php echo htmlspecialchars($complainant); ?></div>
        </div>
        <div class="col-6 text-end">
            <div><strong>Case Number:</strong></div>
            <div class="fw-bold font-monospace"><?php echo htmlspecialchars($case['case_number']); ?></div>
            <div class="small text-muted">Date Filed: <?php echo date('M d, Y', strtotime($case['created_at'])); ?></div>
        </div>
    </div>

    <div class="row mt-2">
        <div class="col-12 text-center my-2">
            <span class="fw-bold">- Versus -</span>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div><strong>Respondent(s):</strong></div>
            <div class="ps-3 text-uppercase fw-bold"><?php echo htmlspecialchars($respondent); ?></div>
        </div>
    </div>

    <hr class="my-4">

    <?php if ($type === 'summons'): ?>
        <!-- SUMMONS NOTICE TEMPLATE -->
        <div class="text-center doc-title h3">Summons (Patawag)</div>
        
        <p class="mt-4" style="text-indent: 50px; text-align: justify; line-height: 1.6;">
            To the above-named Respondent, you are hereby summoned and required to appear in person before me, the Lupon Chairman of Barangay Poblacion, on 
            <strong class="text-dark"><?php echo $scheduled_display; ?></strong> at the Barangay Hall, to answer and undergo mediation regarding the filed complaint of 
            <strong><?php echo htmlspecialchars($case['incident_type']); ?></strong>.
        </p>

        <p class="mt-3" style="text-indent: 50px; text-align: justify; line-height: 1.6;">
            Please be warned that your failure or refusal to appear in compliance with this summons without justifiable cause shall be deemed a waiver of your right to mediation/conciliation, and may result in legal consequences under RA 7160.
        </p>

        <p class="mt-4">Given under my hand this <?php echo date('jS'); ?> day of <?php echo date('F, Y'); ?>.</p>

    <?php else: ?>
        <!-- CERTIFICATE TO FILE ACTION TEMPLATE -->
        <div class="text-center doc-title h3">Certificate to File Action</div>
        <div class="text-center fw-bold text-muted mb-4" style="font-size: 0.85rem; margin-top: -1.5rem;">(Pakatunayan sa Paghahabla)</div>
        
        <p class="mt-4" style="text-indent: 50px; text-align: justify; line-height: 1.6;">
            This is to certify that the above-captioned dispute regarding <strong><?php echo htmlspecialchars($case['incident_type']); ?></strong> was filed, and mediation proceedings were duly conducted. 
        </p>

        <p class="mt-3" style="text-indent: 50px; text-align: justify; line-height: 1.6;">
            However, despite diligent efforts by the Lupon Tagapamayapa, no amicable settlement was reached between the parties. Therefore, the corresponding dispute remains unresolved and is hereby endorsed for formal judicial resolution.
        </p>

        <p class="mt-3" style="text-indent: 50px; text-align: justify; line-height: 1.6;">
            This certification is issued in compliance with Chapter VII, Section 412 of the Local Government Code of 1991 (Republic Act No. 7160), to enable the Complainant to file the appropriate case before the competent courts of law.
        </p>

        <p class="mt-4">Issued this <?php echo date('jS'); ?> day of <?php echo date('F, Y'); ?>.</p>
    <?php endif; ?>

    <!-- Signature Section -->
    <div class="row" style="margin-top: 6rem;">
        <div class="col-6">
            <div class="signature-line ms-0">
                Barangay Secretary
            </div>
            <div class="small text-muted text-center" style="width: 250px;">Attested By</div>
        </div>
        <div class="col-6 d-flex justify-content-end">
            <div>
                <div class="signature-line me-0">
                    Barangay Captain
                </div>
                <div class="small text-muted text-center">Lupon Chairman</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>