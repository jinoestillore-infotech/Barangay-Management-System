<?php
require_once '../includes/auth.php';
require_once '../classes/Database.php';
require_once '../classes/Dashboard.php';

// Instantiate database connection
$database = new Database();
$conn = $database->connect();

// Instantiate Dashboard class and load stats
$dashboard = new Dashboard($conn);
$stats = $dashboard->getQuickStats();

// Extract metrics for readability in HTML
$totalResidents = $stats['total_residents'];
$activeBlotters = $stats['active_blotters'];
$pendingCerts    = $stats['pending_certs'];
$activeStaff     = $stats['active_staff'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Barangay Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-custom py-2 py-sm-3">
    <div class="container">
        <a class="navbar-brand navbar-brand-custom d-flex align-items-center fs-6 fs-sm-5 fs-md-4" href="dashboard.php">
            <i class="bi bi-shield-check text-primary me-2 fs-4 fs-sm-3"></i>
            <span class="d-inline-block">Barangay Management System</span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <!-- User Menu -->
            <div class="dropdown">
                <button class="user-profile-btn dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="text-end d-none d-sm-block">
                        <div class="fw-semibold text-dark small"><?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                        <div class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                    </div>
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-weight: 600; font-size: 0.9rem;">
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

<!-- Main Container -->
<div class="container py-4 py-md-5">
    
    <!-- Welcome Header banner -->
    <div class="row mb-4 mb-md-5 align-items-center">
        <div class="col-12 col-md-8 text-center text-md-start">
            <h1 class="fw-bold text-dark mb-1 fs-2 fs-md-1">Mabuhay, <?php echo htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]); ?>!</h1>
            <p class="text-muted mb-0 small-mobile">Here is the active operational overview for your Barangay administration portal.</p>
        </div>
        <div class="col-12 col-md-4 text-center text-md-end mt-3 mt-md-0">
            <span class="badge bg-white border text-dark p-2 px-3 rounded-pill shadow-sm d-inline-flex align-items-center">
                <i class="bi bi-calendar3 text-primary me-2"></i>
                <?php echo date('F d, Y'); ?>
            </span>
        </div>
    </div>

    <!-- Quick Statistics Panel -->
    <div class="row g-3 g-md-4 mb-4 mb-md-5">
        <!-- Residents Stat -->
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                <div class="bg-icon-primary rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                    <i class="bi bi-people fs-5 fs-sm-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem; @media(min-width: 576px) { font-size: 0.875rem; }">Residents</h6>
                    <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo number_format($totalResidents); ?></h4>
                </div>
            </div>
        </div>
        <!-- Pending Certs Stat -->
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                <div class="bg-icon-warning rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                    <i class="bi bi-file-earmark-text fs-5 fs-sm-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Pending</h6>
                    <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo number_format($pendingCerts); ?></h4>
                </div>
            </div>
        </div>
        <!-- Active Blotter Case Stat -->
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                <div class="bg-icon-danger rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                    <i class="bi bi-exclamation-octagon fs-5 fs-sm-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Blotters</h6>
                    <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo number_format($activeBlotters); ?></h4>
                </div>
            </div>
        </div>
        <!-- Active Staff Stat -->
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stat-card p-3 d-flex flex-row align-items-center h-100">
                <div class="bg-icon-success rounded-3 p-2 p-sm-3 me-2 me-sm-3 d-flex align-items-center justify-content-center">
                    <i class="bi bi-person-badge fs-5 fs-sm-4"></i>
                </div>
                <div>
                    <h6 class="text-muted small mb-0 mb-sm-1" style="font-size: 0.75rem;">Staff</h6>
                    <h4 class="fw-bold mb-0 fs-5 fs-sm-3"><?php echo number_format($activeStaff); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN LAUNCHPAD GRID -->
    <h5 class="fw-bold text-dark text-center text-md-start mb-4">Operational Administrative Launchpad</h5>
    <div class="row g-3 g-md-4">
        
        <!-- MODULE 1: USER MANAGEMENT (ADMIN ONLY RESTRICTION) -->
        <?php if ($_SESSION['role'] === 'Administrator' || $_SESSION['role'] === 'Barangay Captain'): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="users/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-primary">
                    <i class="bi bi-person-gear"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">User Management</h5>
                <p class="text-muted small mb-0 flex-grow-1">Pagdumala sa mga account sa mga kawani, seguridad sa sistema, ug mga katungdanan sa matag user.</p>
                <div class="mt-3 text-primary small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>
        <?php endif; ?>

        <!-- MODULE 2: RESIDENT PROFILES -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="../admin/residents/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-success">
                    <i class="bi bi-people"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Resident Information</h5>
                <p class="text-muted small mb-0 flex-grow-1">Pagdumala sa mga profile sa residente, panimalay, Senior Citizens, PWDs, ug mga rekord sa botante.</p>
                <div class="mt-3 text-success small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>

        <!-- MODULE 3: HOUSEHOLD REGISTRY (RBI) -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="households/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-primary">
                    <i class="bi bi-house-gear"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Household Registry</h5>
                <p class="text-muted small mb-0 flex-grow-1">Pagdumala sa Registry of Barangay Inhabitants (RBI), mga Purok assignment, ug detalye sa ulo sa panimalay.</p>
                <div class="mt-3 text-primary small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>

        <!-- MODULE 4: CERTIFICATES & CLEARANCES -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="../admin/certificates/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-warning">
                    <i class="bi bi-file-earmark-medical"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Document Issuance</h5>
                <p class="text-muted small mb-0 flex-grow-1">Paghimo ug pag-imprenta sa mga dokumento sama sa clearance, indigency, residency, ug pag-verify pinaagi sa QR code.</p>
                <div class="mt-3 text-warning small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>

        <!-- MODULE 5: BLOTTER & INCIDENTS -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="../admin/blotter/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-danger">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Blotter & Mediation</h5>
                <p class="text-muted small mb-0 flex-grow-1">Pagrekord sa mga insidente sa barangay, kahimtang sa mediation, iskedyul, ug mga ebidensya.</p>
                <div class="mt-3 text-danger small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>

        <!-- MODULE 6: FINANCIAL MANAGEMENT -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="../admin/finance/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-info">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Financial Records</h5>
                <p class="text-muted small mb-0 flex-grow-1">Pagdumala sa koleksyon sa bayronon, mga resibo, kita, ug pinansyal nga rekord.</p>
                <div class="mt-3 text-info small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>

        <!-- MODULE 7: INVENTORY & ASSETS -->
        <div class="col-12 col-sm-6 col-lg-4">
            <a href="../admin/inventory/index.php" class="card module-card p-3 p-md-4">
                <div class="module-icon bg-icon-purple">
                    <i class="bi bi-box-seam"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Asset & Logistics</h5>
                <p class="text-muted small mb-0 flex-grow-1">Pagdumala sa mga kabtangan sa barangay, pag-ayo sa kagamitan, ug mga rekord sa pagpahulam ug pagbalik.</p>
                <div class="mt-3 text-purple small fw-semibold">Launch Module <i class="bi bi-arrow-right ms-1"></i></div>
            </a>
        </div>

    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>