<?php
// Secure route guarding (Allows Administrators, Captains, and Secretaries)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/BlotterManager.php';
require_once '../../classes/ResidentManager.php';

$database = new Database();
$conn = $database->connect();
$blotterManager = new BlotterManager($conn);
$residentManager = new ResidentManager($conn);

// Retrieve and clear flash session banners cleanly
$successMsg = $_SESSION['success_flash'] ?? '';
$errorMsg = $_SESSION['error_flash'] ?? '';
unset($_SESSION['success_flash'], $_SESSION['error_flash']);

// --------------------------------------------------------
// PAGINATION SETUP
// --------------------------------------------------------
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --------------------------------------------------------
// SEARCH & FILTER RETRIEVAL
// --------------------------------------------------------
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$filterIncident = isset($_GET['incident_filter']) ? $_GET['incident_filter'] : '';

$cases = $blotterManager->getBlotters($searchTerm, $filterStatus, $filterIncident, $limit, $offset);
$totalRecords = $blotterManager->getBlottersCount($searchTerm, $filterStatus, $filterIncident);
$totalPages = ceil($totalRecords / $limit);
if ($totalPages < 1) $totalPages = 1;

// Fetch active metrics for counters
$stats = $blotterManager->getBlotterStats();

// Fetch active residents for case assignment menus
$residentsList = $residentManager->getResidents('', '', '', 'Active', 500, 0);

// Pre-defined Whitelists matching schema
$incidents = ['Theft', 'Physical Injuries', 'Slander/Defamation', 'Boundary Dispute', 'Noise Complaint', 'Trespassing', 'Breach of Peace', 'Threats', 'Others'];
$statuses = ['Active', 'Scheduled for Mediation', 'Settled', 'Referred to Court'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Dispute Registry - Barangay System</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/blotter.css" rel="stylesheet">
    <style>
        /* Specific Status Styling Overrides matching Official KP Guidelines */
        .badge-active { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .badge-scheduled { background: rgba(255, 193, 7, 0.15); color: #b58105; }
        .badge-settled { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .badge-court { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    </style>
</head>
<body>

<div class="container-fluid px-4 dashboard-wrapper">

    <!-- Header Block -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 gap-3">
        <div>
            <a href="../dashboard.php" class="back-link d-inline-flex align-items-center mb-3">
                <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
            </a>
            <h1 class="fw-bold text-dark mb-1 fs-2">Dispute & Mediation Registry</h1>
            <p class="text-muted mb-0">Record civilian conflicts, schedule mediation hearings, and document amicable settlements.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCaseModal">
                <i class="bi bi-journal-plus me-2"></i> File New Complaint
            </button>
        </div>
    </div>

    <!-- Live Status Overview Cards -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card page-card p-4 h-100 mb-0">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 me-3"><i class="bi bi-folder-fill fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Active Records</span>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?php echo (int)($stats['active'] ?? 0); ?></h3>
                <small class="text-muted">Investigation Pending</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card page-card p-4 h-100 mb-0">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-warning-subtle text-warning rounded-3 me-3"><i class="bi bi-calendar2-event-fill fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Scheduled Mediation</span>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?php echo (int)($stats['scheduled'] ?? 0); ?></h3>
                <small class="text-muted">Lupon Hearings Booked</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card page-card p-4 h-100 mb-0">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-success-subtle text-success rounded-3 me-3"><i class="bi bi-check-circle-fill fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Amicably Settled</span>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?php echo (int)($stats['settled'] ?? 0); ?></h3>
                <small class="text-muted">Settlements Concluded</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card page-card p-4 h-100 mb-0">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-danger-subtle text-danger rounded-3 me-3"><i class="bi bi-building-fill-exclamation fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Court Referrals</span>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?php echo (int)($stats['referred'] ?? 0); ?></h3>
                <small class="text-muted">Certificates Issued</small>
            </div>
        </div>
    </div>

    <!-- Alerts Banners -->
    <?php if(!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-3 fs-4 text-success"></i>
            <div class="fw-medium me-5"><?php echo htmlspecialchars($successMsg); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if(!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4 text-danger"></i>
            <div class="fw-medium me-5"><?php echo htmlspecialchars($errorMsg); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filters Form -->
    <div class="card page-card p-4 p-md-5 mb-5">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Filter Court Blotters</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-5">
                <label class="form-label text-secondary small fw-bold">Search Dispute Cases</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search Case #, complainant, or respondent name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Incident Classification</label>
                <select name="incident_filter" class="form-select">
                    <option value="">All Incident Types</option>
                    <?php foreach ($incidents as $inc): ?>
                        <option value="<?php echo $inc; ?>" <?php echo $filterIncident === $inc ? 'selected' : ''; ?>><?php echo $inc; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label text-secondary small fw-bold">Case Status</label>
                <select name="status_filter" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $stat): ?>
                        <option value="<?php echo $stat; ?>" <?php echo $filterStatus === $stat ? 'selected' : ''; ?>><?php echo $stat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5"><i class="bi bi-filter"></i> Search</button>
            </div>
        </form>
    </div>

    <!-- Main Dispute Table Card -->
    <div class="card page-card">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Cases Directory</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo $totalRecords; ?> Total Cases</span>
        </div>
        <div class="table-scroll-container">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Case Number</th>
                        <th>Complainant vs Respondent</th>
                        <th>Incident Details</th>
                        <th>Mediation Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-file-earmark-diff fs-1 mb-2 d-block"></i>
                                No conflict incidents match your selected filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cases as $row): ?>
                            <?php
                                $comp = $row['complainant_non_resident'] ? $row['complainant_non_resident'] : $row['resident_complainant'];
                                $resp = $row['respondent_non_resident'] ? $row['respondent_non_resident'] : $row['resident_respondent'];
                                
                                $statusBadgeClass = 'badge-active';
                                if ($row['status'] === 'Scheduled for Mediation') $statusBadgeClass = 'badge-scheduled';
                                if ($row['status'] === 'Settled') $statusBadgeClass = 'badge-settled';
                                if ($row['status'] === 'Referred to Court') $statusBadgeClass = 'badge-court';
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-dark"><?php echo htmlspecialchars($row['case_number']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($comp); ?></div>
                                    <div class="text-muted small">Versus</div>
                                    <div class="fw-bold text-secondary"><?php echo htmlspecialchars($resp); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light border text-dark fw-bold mb-1"><i class="bi bi-tag-fill text-muted me-1"></i><?php echo htmlspecialchars($row['incident_type']); ?></span>
                                    <div class="text-muted small text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars($row['incident_location']); ?></div>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Scheduled for Mediation' && !empty($row['settlement_details'])): ?>
                                        <span class="text-warning small fw-semibold"><i class="bi bi-calendar-event text-warning me-1"></i><?php echo htmlspecialchars(str_replace("Mediation scheduled on: ", "", $row['settlement_details'])); ?></span>
                                    <?php else: ?>
                                        <span class="text-black-50 small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $statusBadgeClass; ?> fw-bold px-2.5 py-1.5"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn-action-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg dropdown-menu-custom">
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-view-case" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#viewCaseModal">
                                                    <i class="bi bi-eye me-2 text-info"></i> View Details
                                                </button>
                                            </li>
                                            <?php if ($row['status'] === 'Active' || $row['status'] === 'Scheduled for Mediation'): ?>
                                                <li>
                                                    <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-schedule-hearing" data-id="<?php echo $row['id']; ?>" data-case="<?php echo htmlspecialchars($row['case_number']); ?>" data-bs-toggle="modal" data-bs-target="#scheduleHearingModal">
                                                        <i class="bi bi-calendar-check me-2 text-warning"></i> Schedule Hearing
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-resolve-case" data-id="<?php echo $row['id']; ?>" data-case="<?php echo htmlspecialchars($row['case_number']); ?>" data-bs-toggle="modal" data-bs-target="#resolveCaseModal">
                                                        <i class="bi bi-bookmark-check me-2 text-success"></i> Log Resolution
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                            <!-- Print Summons and print Certificate options -->
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <a href="print.php?id=<?php echo $row['id']; ?>&type=summons" target="_blank" class="dropdown-item dropdown-item-custom d-flex align-items-center">
                                                    <i class="bi bi-printer me-2 text-dark"></i> Print Summons Notice
                                                </a>
                                            </li>
                                            <li>
                                                <a href="print.php?id=<?php echo $row['id']; ?>&type=certification" target="_blank" class="dropdown-item dropdown-item-custom d-flex align-items-center">
                                                    <i class="bi bi-file-earmark-pdf me-2 text-danger"></i> Print Court Certificate
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Navigation Footer -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0 gap-1">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 rounded-circle" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchTerm); ?>&status_filter=<?php echo urlencode($filterStatus); ?>&incident_filter=<?php echo urlencode($filterIncident); ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $page === $p ? 'active' : ''; ?>">
                                <a class="page-link border-0 rounded-circle <?php echo $page === $p ? 'bg-primary text-white' : 'text-dark'; ?>" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($searchTerm); ?>&status_filter=<?php echo urlencode($filterStatus); ?>&incident_filter=<?php echo urlencode($filterIncident); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 rounded-circle" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchTerm); ?>&status_filter=<?php echo urlencode($filterStatus); ?>&incident_filter=<?php echo urlencode($filterIncident); ?>"><i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================
     MODALS SECTION
     ======================================================== -->

<!-- 1. FILE NEW COMPLAINT MODAL -->
<div class="modal fade" id="addCaseModal" tabindex="-1" aria-labelledby="addCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="addCaseModalLabel"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>File New Blotter Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="add_blotter">
                <div class="modal-body p-4">
                    <!-- Complainant details -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Complainant Status</label>
                            <select id="complainant_status" name="comp_type" class="form-select" onchange="toggleComplainantInput()">
                                <option value="resident">Registered Resident</option>
                                <option value="non_resident">Non-Resident / Civilian</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <div id="comp_resident_wrapper">
                                <label class="form-label small fw-bold text-secondary">Search Resident Complainant</label>
                                <select name="complainant_id" class="form-select">
                                    <option value="">Select Inhabitant</option>
                                    <?php foreach ($residentsList as $r): ?>
                                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="comp_non_resident_wrapper" style="display: none;">
                                <label class="form-label small fw-bold text-secondary">Complainant Full Name (Non-Resident)</label>
                                <input type="text" name="complainant_non_resident" class="form-control" placeholder="Firstname Lastname">
                            </div>
                        </div>
                    </div>

                    <!-- Respondent details -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Respondent Status</label>
                            <select id="respondent_status" name="resp_type" class="form-select" onchange="toggleRespondentInput()">
                                <option value="resident">Registered Resident</option>
                                <option value="non_resident">Non-Resident / Civilian</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <div id="resp_resident_wrapper">
                                <label class="form-label small fw-bold text-secondary">Search Resident Respondent</label>
                                <select name="respondent_id" class="form-select">
                                    <option value="">Select Inhabitant</option>
                                    <?php foreach ($residentsList as $r): ?>
                                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="resp_non_resident_wrapper" style="display: none;">
                                <label class="form-label small fw-bold text-secondary">Respondent Full Name (Non-Resident)</label>
                                <input type="text" name="respondent_non_resident" class="form-control" placeholder="Firstname Lastname">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 text-muted">

                    <!-- Incident Metadata -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Incident Type</label>
                            <select name="incident_type" class="form-select" required>
                                <?php foreach ($incidents as $inc): ?>
                                    <option value="<?php echo $inc; ?>"><?php echo $inc; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Incident Date & Time</label>
                            <input type="datetime-local" name="incident_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Incident Location / Area</label>
                        <input type="text" name="incident_location" class="form-control" placeholder="e.g. Purok 3, Barangay Basketball Court" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Details / Incident Statement Narrative</label>
                        <textarea name="details" class="form-control" rows="4" placeholder="Describe the complaints, circumstances, and dispute elements thoroughly..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Register Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. VIEW CASE DETAILS MODAL -->
<div class="modal fade" id="viewCaseModal" tabindex="-1" aria-labelledby="viewCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="viewCaseModalLabel"><i class="bi bi-briefcase me-2 text-info"></i>Incident Report Summary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-12 col-md-6 border-end">
                        <h6 class="text-secondary fw-bold small uppercase tracking-wide mb-3">Case Specifications</h6>
                        <table class="table table-borderless table-sm small">
                            <tr><td class="text-muted" width="120">Case Number:</td><td class="fw-bold text-dark font-monospace" id="v_case_num">-</td></tr>
                            <tr><td class="text-muted">Incident Type:</td><td class="fw-bold" id="v_inc_type">-</td></tr>
                            <tr><td class="text-muted">Location:</td><td id="v_location">-</td></tr>
                            <tr><td class="text-muted">Incident Date:</td><td id="v_inc_date">-</td></tr>
                            <tr><td class="text-muted">Current Status:</td><td><span class="badge" id="v_status">-</span></td></tr>
                        </table>
                    </div>
                    <div class="col-12 col-md-6">
                        <h6 class="text-secondary fw-bold small uppercase tracking-wide mb-3">Disputing Parties</h6>
                        <table class="table table-borderless table-sm small">
                            <tr><td class="text-muted" width="100">Complainant:</td><td class="fw-bold text-primary" id="v_complainant">-</td></tr>
                            <tr><td class="text-muted">Respondent:</td><td class="fw-bold text-danger" id="v_respondent">-</td></tr>
                        </table>
                    </div>
                </div>
                <hr class="my-4">
                <div class="mb-3">
                    <h6 class="text-secondary fw-bold small mb-2">Complainant's Narrative & Details</h6>
                    <div class="p-3 bg-light rounded-3 text-dark small" style="white-space: pre-line; line-height: 1.5;" id="v_details">
                        -
                    </div>
                </div>
                <div id="settlement_display_block" style="display: none;">
                    <h6 class="text-secondary fw-bold small mb-2" id="settlement_title">Settlement Agreements & Directives</h6>
                    <div class="p-3 bg-success-subtle border border-success-subtle text-success-emphasis rounded-3 small" style="white-space: pre-line; line-height: 1.5;" id="v_settlement">
                        -
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Detail Window</button>
            </div>
        </div>
    </div>
</div>

<!-- 3. SCHEDULE HEARING MODAL -->
<div class="modal fade" id="scheduleHearingModal" tabindex="-1" aria-labelledby="scheduleHearingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="scheduleHearingModalLabel"><i class="bi bi-calendar2-week me-2 text-warning"></i>Schedule Mediation Hearing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="schedule_hearing">
                <input type="hidden" name="hearing_case_id" id="hearing_case_id">
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Schedule a hearing of amicable settlement before the Lupon Chairman for Case #<span class="fw-bold font-monospace" id="hearing_case_num_label"></span>.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Proposed Hearing Date & Time</label>
                        <input type="datetime-local" name="hearing_date" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Schedule Summon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. RESOLVE CASE MODAL -->
<div class="modal fade" id="resolveCaseModal" tabindex="-1" aria-labelledby="resolveCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="resolveCaseModalLabel"><i class="bi bi-bookmark-check-fill me-2 text-success"></i>Log Case Resolution</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="resolve_case">
                <input type="hidden" name="resolve_case_id" id="resolve_case_id">
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Update resolution logs and close dispute sessions for Case #<span class="fw-bold font-monospace" id="resolve_case_num_label"></span>.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Final Status / Endorsement</label>
                        <select name="status" class="form-select" required>
                            <option value="Settled">Settled (Amicable Settlement Agreement)</option>
                            <option value="Referred to Court">Referred to Court (Certificate Issued)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Settlement Details & Agreements</label>
                        <textarea name="settlement_details" class="form-control" rows="4" placeholder="Detail the amicable points agreed upon, or reasons for court referral..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4">Save Resolution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-dismiss alert cards smoothly
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

// Complainant state toggle selector
function toggleComplainantInput() {
    const status = document.getElementById('complainant_status').value;
    const resWrapper = document.getElementById('comp_resident_wrapper');
    const nonResWrapper = document.getElementById('comp_non_resident_wrapper');

    if (status === 'resident') {
        resWrapper.style.display = 'block';
        nonResWrapper.style.display = 'none';
        nonResWrapper.querySelector('input').removeAttribute('required');
    } else {
        resWrapper.style.display = 'none';
        nonResWrapper.style.display = 'block';
        nonResWrapper.querySelector('input').setAttribute('required', 'required');
    }
}

// Respondent state toggle selector
function toggleRespondentInput() {
    const status = document.getElementById('respondent_status').value;
    const resWrapper = document.getElementById('resp_resident_wrapper');
    const nonResWrapper = document.getElementById('resp_non_resident_wrapper');

    if (status === 'resident') {
        resWrapper.style.display = 'block';
        nonResWrapper.style.display = 'none';
        nonResWrapper.querySelector('input').removeAttribute('required');
    } else {
        resWrapper.style.display = 'none';
        nonResWrapper.style.display = 'block';
        nonResWrapper.querySelector('input').setAttribute('required', 'required');
    }
}

// Case details modal populator with advanced JSON and text diagnostic catches
document.querySelectorAll('.btn-view-case').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        
        fetch(`process.php?fetch_case=${id}`)
            .then(res => res.text()) // Get raw response body first to avoid unhandled crashes
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.error) {
                        alert("System Notice: " + data.error);
                    } else {
                        document.getElementById('v_case_num').textContent = data.case_number;
                        document.getElementById('v_inc_type').textContent = data.incident_type;
                        document.getElementById('v_location').textContent = data.incident_location;
                        
                        // Handle potential date parsing exceptions gracefully
                        let formattedDate = 'N/A';
                        if (data.incident_date) {
                            const parsedDate = new Date(data.incident_date);
                            formattedDate = isNaN(parsedDate.getTime()) ? data.incident_date : parsedDate.toLocaleString();
                        }
                        document.getElementById('v_inc_date').textContent = formattedDate;
                        
                        const statusSpan = document.getElementById('v_status');
                        statusSpan.textContent = data.status;
                        statusSpan.className = 'badge';
                        if (data.status === 'Active') statusSpan.classList.add('badge-active');
                        if (data.status === 'Scheduled for Mediation') statusSpan.classList.add('badge-scheduled');
                        if (data.status === 'Settled') statusSpan.classList.add('badge-settled');
                        if (data.status === 'Referred to Court') statusSpan.classList.add('badge-court');

                        document.getElementById('v_complainant').textContent = data.complainant_non_resident ? data.complainant_non_resident : data.resident_complainant;
                        document.getElementById('v_respondent').textContent = data.respondent_non_resident ? data.respondent_non_resident : data.resident_respondent;
                        document.getElementById('v_details').textContent = data.details;

                        const settlementBlock = document.getElementById('settlement_display_block');
                        if (data.status === 'Settled' || data.status === 'Referred to Court') {
                            settlementBlock.style.display = 'block';
                            document.getElementById('settlement_title').textContent = data.status === 'Settled' ? 'Settlement Agreements' : 'Referral Instructions';
                            document.getElementById('v_settlement').textContent = data.settlement_details;
                        } else {
                            settlementBlock.style.display = 'none';
                        }
                    }
                } catch (e) {
                    console.error("Failed to parse JSON response:", text);
                    alert("Database / PHP Error Occurred! Please check the console or your PHP error logs for more information.\n\nRaw Server Response:\n" + text.substring(0, 300));
                }
            })
            .catch(err => {
                console.error('Error fetching details:', err);
                alert("Network communication error. Please try again.");
            });
    });
});

// Case Hearing schedulings modal values assignment
document.querySelectorAll('.btn-schedule-hearing').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const caseNum = this.getAttribute('data-case');
        document.getElementById('hearing_case_id').value = id;
        document.getElementById('hearing_case_num_label').textContent = caseNum;
    });
});

// Case resolution modal values assignment
document.querySelectorAll('.btn-resolve-case').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const caseNum = this.getAttribute('data-case');
        document.getElementById('resolve_case_id').value = id;
        document.getElementById('resolve_case_num_label').textContent = caseNum;
    });
});
</script>

</body>
</html>