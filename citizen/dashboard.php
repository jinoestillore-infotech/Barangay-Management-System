<?php
/**
 * Citizen Portal Dashboard
 * Fully secure, interactive, and beautifully designed user portal for registered residents.
 * Allows requesting certificates, tracking blotters, and viewing personal financial logs.
 */

require_once '../includes/auth.php';
authorizeRoles(['Citizen']); // Strictly restrict access to users assigned the 'Citizen' role

require_once '../classes/Database.php';

// Instantiate the database connection
$database = new Database();
$conn = $database->connect();

$userId = (int)$_SESSION['user_id'];
$residentId = $_SESSION['resident_id'] ? (int)$_SESSION['resident_id'] : null;

// Initialize alert banners
$successMsg = $_SESSION['success_flash'] ?? '';
$errorMsg = $_SESSION['error_flash'] ?? '';
unset($_SESSION['success_flash'], $_SESSION['error_flash']);

// --------------------------------------------------------
// PROCESS USER POST ACTIONS (Self-contained controllers)
// --------------------------------------------------------
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

// --------------------------------------------------------
// RETRIEVE USER PROFILE & HISTORICAL RECORDS
// --------------------------------------------------------
$residentProfile = null;
$clearances = [];
$blotters = [];
$payments = [];

$stats = [
    'requests' => 0,
    'pending' => 0,
    'blotters' => 0,
    'payments' => 0.00
];

if ($residentId !== null) {
    try {
        // 1. Fetch Resident Demographic Data and Address
        $resQuery = "SELECT r.*, h.household_number, h.street, h.zone_purok 
                     FROM residents r
                     LEFT JOIN households h ON r.household_id = h.id
                     WHERE r.id = :res_id AND r.status = 'Active' LIMIT 1";
        $stmt = $conn->prepare($resQuery);
        $stmt->bindValue(':res_id', $residentId, PDO::PARAM_INT);
        $stmt->execute();
        $residentProfile = $stmt->fetch() ?: null;

        if ($residentProfile) {
            // 2. Fetch Document Requests
            $certQuery = "SELECT c.*, u.fullname as approver 
                          FROM certificates c 
                          LEFT JOIN users u ON c.issued_by = u.id 
                          WHERE c.resident_id = :res_id 
                          ORDER BY c.issued_at DESC";
            $stmt = $conn->prepare($certQuery);
            $stmt->bindValue(':res_id', $residentId, PDO::PARAM_INT);
            $stmt->execute();
            $clearances = $stmt->fetchAll() ?: [];

            // 3. Fetch Incident Cases (filed by this citizen as complainant)
            $blotterQuery = "SELECT b.*, u.fullname as clerk 
                             FROM blotter_records b 
                             LEFT JOIN users u ON b.recorded_by = u.id 
                             WHERE b.complainant_id = :res_id 
                             ORDER BY b.created_at DESC";
            $stmt = $conn->prepare($blotterQuery);
            $stmt->bindValue(':res_id', $residentId, PDO::PARAM_INT);
            $stmt->execute();
            $blotters = $stmt->fetchAll() ?: [];

            // 4. Fetch Personal Financial Payments History
            $paymentQuery = "SELECT p.*, u.fullname as collector 
                             FROM payments p 
                             LEFT JOIN users u ON p.received_by = u.id 
                             WHERE p.resident_id = :res_id 
                             ORDER BY p.payment_date DESC";
            $stmt = $conn->prepare($paymentQuery);
            $stmt->bindValue(':res_id', $residentId, PDO::PARAM_INT);
            $stmt->execute();
            $payments = $stmt->fetchAll() ?: [];

            // 5. Calculate Stats Counters
            $stats['requests'] = count($clearances);
            $stats['pending'] = count(array_filter($clearances, fn($c) => $c['status'] === 'Pending'));
            $stats['blotters'] = count($blotters);
            $stats['payments'] = array_reduce($payments, fn($sum, $p) => $sum + (float)$p['amount'], 0.00);
        }
    } catch (PDOException $e) {
        error_log("Citizen dashboard data retrieval crash: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - Barangay Portal</title>
    <!-- Bootstrap 5 CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2b4c7e;
            --secondary-color: #1a3052;
            --bg-neutral: #f8fafc;
            --text-dark: #1e293b;
        }

        body {
            background-color: var(--bg-neutral);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text-dark);
        }

        .navbar-custom {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            border-bottom: 1px solid #e2e8f0;
        }

        .navbar-brand-custom {
            font-weight: 700;
            color: var(--secondary-color);
            letter-spacing: -0.5px;
        }

        .user-profile-btn {
            background: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0;
        }

        .stat-card {
            border: none;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .module-card {
            border: none;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            color: inherit;
            cursor: pointer;
        }

        .module-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 1rem;
        }

        .bg-icon-primary { background: rgba(43, 76, 126, 0.1); color: #2b4c7e; }
        .bg-icon-success { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .bg-icon-warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .bg-icon-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .bg-icon-info { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }

        .table-custom {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-custom td {
            vertical-align: middle;
            padding: 1rem 1.25rem;
            font-size: 0.95rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge-active { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .badge-pending { background: rgba(255, 193, 7, 0.15); color: #b58105; }
        .badge-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .badge-info { background: rgba(13, 202, 240, 0.15); color: #0d6efd; }
    </style>
</head>
<body>

<!-- Navigation Header -->
<nav class="navbar navbar-expand-lg navbar-custom py-2 py-sm-3">
    <div class="container">
        <a class="navbar-brand navbar-brand-custom d-flex align-items-center fs-6 fs-sm-5" href="dashboard.php">
            <i class="bi bi-shield-check text-primary me-2 fs-4"></i>
            <span>Citizen E-Services Portal</span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <div class="dropdown">
                <button class="user-profile-btn dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="text-end d-none d-sm-block">
                        <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                        <div class="text-muted text-uppercase" style="font-size: 0.65rem; font-weight: 700;"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                    </div>
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-weight: 600;">
                        <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow mt-2" aria-labelledby="userMenuButton">
                    <li><span class="dropdown-item-text text-muted small">Signed in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger d-flex align-items-center" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4 py-md-5">

    <!-- Unlinked Guardrail Warning -->
    <?php if ($residentId === null): ?>
        <div class="card border-0 shadow-sm p-4 mb-5 rounded-4 bg-white">
            <div class="d-flex align-items-start text-danger mb-3">
                <i class="bi bi-exclamation-triangle-fill fs-1 me-3"></i>
                <div>
                    <h4 class="fw-bold text-dark">Portal Credentials Unlinked</h4>
                    <p class="text-muted mb-0">Your portal account is currently not connected to an official physical resident profile inside the Barangay Registry (RBI).</p>
                </div>
            </div>
            <div class="p-3 bg-danger-subtle rounded-3 text-danger-emphasis small mb-3">
                <i class="bi bi-info-circle-fill me-1"></i> You cannot apply for official clearances, file incident report summons, or view your tax/payment history online until your record is linked.
            </div>
            <p class="mb-0 small text-muted"><strong>How to resolve:</strong> Please visit the Barangay Hall Secretariat or Hall Clerk and present a valid government-issued identification card to map your account instantly.</p>
        </div>
    <?php else: ?>

        <!-- Welcome Banner -->
        <div class="row mb-4 mb-md-5 align-items-center">
            <div class="col-12 col-md-8 text-center text-md-start">
                <h1 class="fw-bold text-dark mb-1 fs-2 fs-md-1">Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]); ?>!</h1>
                <p class="text-muted mb-0">Apply for certifications, trace ongoing summons, and coordinate with barangay officials online.</p>
            </div>
            <div class="col-12 col-md-4 text-center text-md-end mt-3 mt-md-0">
                <span class="badge bg-white border text-dark p-2 px-3 rounded-pill shadow-sm d-inline-flex align-items-center">
                    <i class="bi bi-shield-check text-success me-2"></i> Verified Resident Inhabitant
                </span>
            </div>
        </div>

        <!-- Alert Notification Messages -->
        <?php if(!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
                <i class="bi bi-check-circle-fill me-3 fs-4 text-success"></i>
                <div class="fw-medium"><?php echo htmlspecialchars($successMsg); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-4 text-danger"></i>
                <div class="fw-medium"><?php echo htmlspecialchars($errorMsg); ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Overview Panel (Cards matching admin design) -->
        <div class="row g-3 g-md-4 mb-5">
            <div class="col-6 col-lg-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                    <div class="bg-icon-primary rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-file-earmark-medical fs-5 fs-sm-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Document Apps</h6>
                        <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo $stats['requests']; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                    <div class="bg-icon-warning rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-hourglass-split fs-5 fs-sm-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Pending Appr.</h6>
                        <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo $stats['pending']; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                    <div class="bg-icon-danger rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-gavel fs-5 fs-sm-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Blotter Cases</h6>
                        <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo $stats['blotters']; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                    <div class="bg-icon-success rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                        <i class="bi bi-cash-coin fs-5 fs-sm-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Total Fees Paid</h6>
                        <h4 class="fw-bold mb-0 fs-6 fs-sm-4">₱<?php echo number_format($stats['payments'], 2); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interactive Launchpad Grid Modules -->
        <h5 class="fw-bold text-dark text-center text-md-start mb-4">E-Services Launchpad</h5>
        <div class="row g-3 g-md-4 mb-5">
            
            <!-- Module 1: Request Clearance Certificate -->
            <div class="col-12 col-md-4">
                <div class="card module-card p-4" data-bs-toggle="modal" data-bs-target="#requestDocumentModal">
                    <div class="module-icon bg-icon-primary">
                        <i class="bi bi-file-earmark-plus"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Request Document</h5>
                    <p class="text-muted small mb-0 flex-grow-1">Apply for a Barangay Clearance, Indigency, Residency, or Business clearance instantly online.</p>
                    <div class="mt-3 text-primary small fw-semibold">Apply Online <i class="bi bi-arrow-right ms-1"></i></div>
                </div>
            </div>

            <!-- Module 2: File Incident Blotter Complaint -->
            <div class="col-12 col-md-4">
                <div class="card module-card p-4" data-bs-toggle="modal" data-bs-target="#reportIncidentModal">
                    <div class="module-icon bg-icon-danger">
                        <i class="bi bi-exclamation-octagon"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Report Incident / File Case</h5>
                    <p class="text-muted small mb-0 flex-grow-1">File conflict incidents directly into the Barangay Lupon Mediation queue to request formal summons.</p>
                    <div class="mt-3 text-danger small fw-semibold">File Report <i class="bi bi-arrow-right ms-1"></i></div>
                </div>
            </div>

            <!-- Module 3: View Personal Demographic Data Profile -->
            <div class="col-12 col-md-4">
                <div class="card module-card p-4" data-bs-toggle="modal" data-bs-target="#viewProfileModal">
                    <div class="module-icon bg-icon-info">
                        <i class="bi bi-person-lines-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">My Official Profile</h5>
                    <p class="text-muted small mb-0 flex-grow-1">Check your registered RBI demographic details, household relationships, and voter status.</p>
                    <div class="mt-3 text-info small fw-semibold">View Record <i class="bi bi-arrow-right ms-1"></i></div>
                </div>
            </div>

        </div>

        <!-- Historical Records Tabbed Panel Directory -->
        <div class="card border-0 shadow-sm mb-5 rounded-4">
            <div class="card-header bg-white border-0 p-4 pb-0">
                <h5 class="fw-bold text-dark mb-3">Portal Transaction Ledgers</h5>
                <ul class="nav nav-tabs border-0" id="portalTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-dark py-2.5" id="clearances-tab" data-bs-toggle="tab" data-bs-target="#clearances" type="button" role="tab" aria-controls="clearances" aria-selected="true">
                            <i class="bi bi-file-earmark-check me-2 text-primary"></i>My Clearances (<?php echo count($clearances); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-dark py-2.5" id="incidents-tab" data-bs-toggle="tab" data-bs-target="#incidents" type="button" role="tab" aria-controls="incidents" aria-selected="false">
                            <i class="bi bi-shield-exclamation me-2 text-danger"></i>Reported Cases (<?php echo count($blotters); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-dark py-2.5" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button" role="tab" aria-controls="finance" aria-selected="false">
                            <i class="bi bi-receipt me-2 text-success"></i>Official Receipts (<?php echo count($payments); ?>)
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="tab-content p-4" id="portalTabsContent">
                
                <!-- Tab Panel 1: Document Requests -->
                <div class="tab-pane fade show active" id="clearances" role="tabpanel" aria-labelledby="clearances-tab">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>Requested Purpose</th>
                                    <th>Filed At</th>
                                    <th>Status</th>
                                    <th>O.R. Reference</th>
                                    <th>Verification Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($clearances)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-file-earmark-medical fs-2 mb-2 d-block"></i>
                                            You have not requested any clearances or certificates yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clearances as $c): ?>
                                        <?php 
                                            $badgeClass = 'badge-pending';
                                            if ($c['status'] === 'Approved' || $c['status'] === 'Issued') $badgeClass = 'badge-active';
                                            if ($c['status'] === 'Rejected') $badgeClass = 'badge-danger';
                                        ?>
                                        <tr>
                                            <td><span class="fw-bold text-dark"><?php echo htmlspecialchars($c['certificate_type']); ?></span></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($c['purpose']); ?></td>
                                            <td class="small"><?php echo date('M d, Y - h:i A', strtotime($c['issued_at'])); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?> fw-bold px-2 py-1.5"><?php echo $c['status']; ?></span></td>
                                            <td>
                                                <?php if (!empty($c['or_number'])): ?>
                                                    <span class="font-monospace text-dark fw-semibold small"><i class="bi bi-receipt text-success me-1"></i><?php echo htmlspecialchars($c['or_number']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-black-50 small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-light border text-dark font-monospace small px-2 py-1"><?php echo htmlspecialchars($c['verification_token']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Panel 2: Blotters filed by the resident -->
                <div class="tab-pane fade" id="incidents" role="tabpanel" aria-labelledby="incidents-tab">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>Case Number</th>
                                    <th>Incident Class</th>
                                    <th>Filing Date</th>
                                    <th>Incident Location</th>
                                    <th>Accused/Respondent</th>
                                    <th>Case Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($blotters)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-gavel fs-2 mb-2 d-block"></i>
                                            No incident reports or cases recorded under your name.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($blotters as $b): ?>
                                        <?php 
                                            $badgeClass = 'badge-info';
                                            if ($b['status'] === 'Settled') $badgeClass = 'badge-active';
                                            if ($b['status'] === 'Referred to Court') $badgeClass = 'badge-danger';
                                            if ($b['status'] === 'Scheduled for Mediation') $badgeClass = 'badge-pending';
                                        ?>
                                        <tr>
                                            <td><span class="fw-bold font-monospace text-dark"><?php echo htmlspecialchars($b['case_number']); ?></span></td>
                                            <td><span class="badge bg-light border text-dark fw-bold"><?php echo htmlspecialchars($b['incident_type']); ?></span></td>
                                            <td class="small"><?php echo date('M d, Y - h:i A', strtotime($b['incident_date'])); ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($b['incident_location']); ?></td>
                                            <td class="fw-semibold text-secondary small"><?php echo htmlspecialchars($b['respondent_non_resident']); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?> fw-bold px-2 py-1.5"><?php echo $b['status']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Panel 3: Financial Receipts ledger matching their profile -->
                <div class="tab-pane fade" id="finance" role="tabpanel" aria-labelledby="finance-tab">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>O.R. Number</th>
                                    <th>Fee Purpose</th>
                                    <th>Cash Collected</th>
                                    <th>Payment Date</th>
                                    <th>Receiving Officer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-receipt fs-2 mb-2 d-block"></i>
                                            No official payment records logged under your resident profile.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><span class="fw-bold font-monospace text-dark"><i class="bi bi-hash text-muted me-0.5"></i><?php echo htmlspecialchars($p['or_number']); ?></span></td>
                                            <td><span class="badge bg-light border text-dark fw-semibold"><?php echo htmlspecialchars($p['payment_for']); ?></span></td>
                                            <td class="fw-bold text-success">₱<?php echo number_format($p['amount'], 2); ?></td>
                                            <td class="small"><?php echo date('M d, Y - h:i A', strtotime($p['payment_date'])); ?></td>
                                            <td class="text-muted small"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($p['collector'] ?? 'Treasury Clerk'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

    <?php endif; ?>
</div>

<!-- ========================================================
     MODALS SECTION
     ======================================================== -->

<!-- 1. REQUEST DOCUMENT MODAL -->
<div class="modal fade" id="requestDocumentModal" tabindex="-1" aria-labelledby="requestDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="requestDocumentModalLabel">
                    <i class="bi bi-file-earmark-text-fill text-primary me-2"></i>Apply for Clearance/Certificate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dashboard.php" method="POST">
                <input type="hidden" name="action" value="request_clearance">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Document / Clearance Selection</label>
                        <select name="certificate_type" class="form-select" required>
                            <option value="">-- Choose Clearance Type --</option>
                            <option value="Barangay Clearance">Barangay Clearance</option>
                            <option value="Certificate of Residency">Certificate of Residency</option>
                            <option value="Certificate of Indigency">Certificate of Indigency</option>
                            <option value="Business Clearance">Business Clearance</option>
                            <option value="Certificate of Good Moral Character">Certificate of Good Moral Character</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">State Purpose of Document Request</label>
                        <textarea name="purpose" class="form-control" rows="4" placeholder="e.g. Employment requirements, Local ID application, Scholarship, Loan..." required></textarea>
                        <small class="text-muted d-block mt-1">This will be review by Barangay Secretaries during preparation.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Submit Clearance App</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. REPORT INCIDENT / BLOTTER MODAL -->
<div class="modal fade" id="reportIncidentModal" tabindex="-1" aria-labelledby="reportIncidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="reportIncidentModalLabel">
                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>File Formal Incident Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="dashboard.php" method="POST">
                <input type="hidden" name="action" value="file_blotter">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Incident Classification</label>
                            <select name="incident_type" class="form-select" required>
                                <option value="Theft">Theft / Larceny</option>
                                <option value="Physical Injuries">Physical Injuries</option>
                                <option value="Slander/Defamation">Slander/Defamation</option>
                                <option value="Boundary Dispute">Boundary Dispute</option>
                                <option value="Noise Complaint">Noise Complaint</option>
                                <option value="Trespassing">Trespassing</option>
                                <option value="Breach of Peace">Breach of Peace</option>
                                <option value="Threats">Threats</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Date & Time of Incident</label>
                            <input type="datetime-local" name="incident_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Respondent Name (Accused party)</label>
                        <input type="text" name="respondent_non_resident" class="form-control" placeholder="Complete Name / Blank if Unknown">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Incident Location Address</label>
                        <input type="text" name="incident_location" class="form-control" placeholder="Purok, Street Name, Area..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Comprehensive Details / Narrative Statement</label>
                        <textarea name="details" class="form-control" rows="4" placeholder="Briefly detail what transpired..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Submit Incident Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. VIEW RESIDENT DEMOGRAPHIC PROFILE MODAL -->
<div class="modal fade" id="viewProfileModal" tabindex="-1" aria-labelledby="viewProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="viewProfileModalLabel">
                    <i class="bi bi-person-bounding-box text-info me-2"></i>Official Demographic Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php if ($residentProfile !== null): ?>
                    <div class="row g-4">
                        <div class="col-12 col-md-6 border-end">
                            <h6 class="text-secondary fw-bold small uppercase mb-3">Identity Details</h6>
                            <table class="table table-borderless table-sm small">
                                <tr><td class="text-muted" width="130">National ID:</td><td class="fw-bold font-monospace"><?php echo !empty($residentProfile['national_id']) ? htmlspecialchars($residentProfile['national_id']) : 'Not Registered'; ?></td></tr>
                                <tr><td class="text-muted">Full Name:</td><td class="fw-bold"><?php echo htmlspecialchars($residentProfile['first_name'] . ' ' . ($residentProfile['middle_name'] ?? '') . ' ' . $residentProfile['last_name'] . ' ' . ($residentProfile['extension_name'] ?? '')); ?></td></tr>
                                <tr><td class="text-muted">Civil Status:</td><td><?php echo htmlspecialchars($residentProfile['civil_status']); ?></td></tr>
                                <tr><td class="text-muted">Gender:</td><td><?php echo htmlspecialchars($residentProfile['gender']); ?></td></tr>
                                <tr><td class="text-muted">Birthdate:</td><td><?php echo date('F d, Y', strtotime($residentProfile['birth_date'])); ?></td></tr>
                                <tr><td class="text-muted">Contact Info:</td><td><?php echo htmlspecialchars($residentProfile['contact_number'] ?? 'N/A'); ?></td></tr>
                            </table>
                        </div>
                        <div class="col-12 col-md-6">
                            <h6 class="text-secondary fw-bold small uppercase mb-3">Household Geographics</h6>
                            <table class="table table-borderless table-sm small">
                                <tr><td class="text-muted" width="130">Household Code:</td><td class="fw-bold text-primary"><?php echo htmlspecialchars($residentProfile['household_number'] ?? 'RBI-Unlinked'); ?></td></tr>
                                <tr><td class="text-muted">Relation to Head:</td><td class="fw-semibold text-secondary"><?php echo htmlspecialchars($residentProfile['relationship_to_head'] ?? 'Inhabitant'); ?></td></tr>
                                <tr><td class="text-muted">Purok Sector:</td><td><?php echo htmlspecialchars($residentProfile['zone_purok'] ?? 'N/A'); ?></td></tr>
                                <tr><td class="text-muted">Street:</td><td><?php echo htmlspecialchars($residentProfile['street'] ?? 'N/A'); ?></td></tr>
                                <tr><td class="text-muted">Registered Voter:</td><td><?php echo $residentProfile['is_voter'] == 1 ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-muted">No</span>'; ?></td></tr>
                                <tr><td class="text-muted">Sector Flags:</td><td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <?php if ($residentProfile['is_senior'] == 1): ?><span class="badge bg-warning text-dark">Senior</span><?php endif; ?>
                                        <?php if ($residentProfile['is_pwd'] == 1): ?><span class="badge bg-purple text-white" style="background:#6f42c1;">PWD</span><?php endif; ?>
                                        <?php if ($residentProfile['is_senior'] == 0 && $residentProfile['is_pwd'] == 0): ?><span class="text-black-50">-</span><?php endif; ?>
                                    </div>
                                </td></tr>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-danger"><i class="bi bi-shield-lock-fill fs-2 mb-2 d-block"></i> Demo profile not found. Contact office admin.</div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Profile</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-dismiss alert banners smoothly
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = "opacity 0.5s ease-out, transform 0.5s ease-out";
            alert.style.opacity = "0";
            alert.style.transform = "translateY(-10px)";
            setTimeout(() => { alert.remove(); }, 500);
        }, 2000);
    });
});
</script>

</body>
</html>