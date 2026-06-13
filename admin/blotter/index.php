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
// SEARCH, FILTER, AND PAGINATION RETRIEVAL
// --------------------------------------------------------
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$filterIncident = isset($_GET['incident_filter']) ? $_GET['incident_filter'] : '';

$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalCount = $blotterManager->getBlottersCount($searchTerm, $filterStatus, $filterIncident);
$totalPages = ceil($totalCount / $limit);

$cases = $blotterManager->getBlotters($searchTerm, $filterStatus, $filterIncident, $limit, $offset);
$stats = $blotterManager->getBlotterStats();

// Retrieve all active residents for filing selectors
$residents = $residentManager->getResidents('', '', '', 'Active', 1000, 0);

$incidents = ['Theft', 'Physical Injuries', 'Slander/Defamation', 'Boundary Dispute', 'Noise Complaint', 'Trespassing', 'Breach of Peace', 'Threats', 'Others'];
$statuses = ['Active', 'Settled', 'Scheduled for Mediation', 'Referred to Court'];

// Maintain search parameters across pagination clicks
$queryString = http_build_query(array_filter([
    'search' => $searchTerm,
    'status_filter' => $filterStatus,
    'incident_filter' => $filterIncident
]));
$paginationUrl = 'index.php?' . (!empty($queryString) ? $queryString . '&' : '') . 'page=';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blotter & Mediation - Barangay System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/users.css" rel="stylesheet">
    <style>
        .badge-active { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .badge-settled { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .badge-scheduled { background: rgba(255, 193, 7, 0.1); color: #b58105; }
        .badge-referred { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    </style>
</head>
<body>

<div class="container-fluid px-4 dashboard-wrapper">

    <!-- Header block -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 gap-3">
        <div>
            <a href="../dashboard.php" class="back-link d-inline-flex align-items-center mb-3">
                <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
            </a>
            <h1 class="fw-bold text-dark mb-1 fs-2">Blotter & Dispute Registry</h1>
            <p class="text-muted mb-0">Record incident logs, schedule mediation coordinates, and document amicable settlements.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#fileCaseModal">
                <i class="bi bi-journal-plus me-2"></i> File New Complaint
            </button>
        </div>
    </div>

    <!-- Quick statistics blocks -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light border" style="color: #0d6efd;"><i class="bi bi-folder2-open fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Active Cases</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($stats['active'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light border" style="color: #b58105;"><i class="bi bi-calendar-event fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Scheduled Hearing</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($stats['scheduled'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light border" style="color: #198754;"><i class="bi bi-hand-thumbs-up fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Settled Conflicts</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($stats['settled'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light border" style="color: #dc3545;"><i class="bi bi-bank fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Referred to Court</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($stats['referred'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert notifications -->
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

    <!-- Searching & Filter elements -->
    <div class="card page-card p-4 p-md-5 mb-5">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Dispute Filters</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-5">
                <label class="form-label text-secondary small fw-bold">Case Search</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by Case No., Name, or Incident details..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Case Status</label>
                <select name="status_filter" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?php echo $st; ?>" <?php echo $filterStatus === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label text-secondary small fw-bold">Incident Type</label>
                <select name="incident_filter" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($incidents as $inc): ?>
                        <option value="<?php echo $inc; ?>" <?php echo $filterIncident === $inc ? 'selected' : ''; ?>><?php echo $inc; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5 px-3"><i class="bi bi-filter"></i> Apply Filters</button>
            </div>
        </form>
    </div>

    <!-- Main directory database table -->
    <div class="card page-card">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text text-primary me-2"></i>Cases Record</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo $totalCount; ?> Cases logged</span>
        </div>
        <div class="table-scroll-container">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Case Number</th>
                        <th>Parties Involved</th>
                        <th>Incident Type & Coordinates</th>
                        <th>Status</th>
                        <th>Date Registered</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cases)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-1 mb-2 d-block"></i>
                                No conflict cases registered under your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cases as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary font-monospace"><?php echo htmlspecialchars($row['case_number']); ?></span>
                                </td>
                                <td>
                                    <div class="small mb-1">
                                        <span class="text-muted">Comp:</span> 
                                        <strong class="text-dark">
                                            <?php echo htmlspecialchars($row['complainant_non_resident'] ?? $row['resident_complainant']); ?>
                                        </strong>
                                    </div>
                                    <div class="small">
                                        <span class="text-muted">Resp:</span> 
                                        <strong class="text-dark">
                                            <?php echo htmlspecialchars($row['respondent_non_resident'] ?? $row['resident_respondent']); ?>
                                        </strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark mb-1"><?php echo htmlspecialchars($row['incident_type']); ?></div>
                                    <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($row['incident_location']); ?></div>
                                </td>
                                <td>
                                    <?php 
                                        $badgeClass = 'badge-active';
                                        if ($row['status'] === 'Settled') $badgeClass = 'badge-settled';
                                        if ($row['status'] === 'Scheduled for Mediation') $badgeClass = 'badge-scheduled';
                                        if ($row['status'] === 'Referred to Court') $badgeClass = 'badge-referred';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?> fw-bold px-2 py-1.5"><?php echo $row['status']; ?></span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn-action-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg dropdown-menu-custom" style="min-width: 200px;">
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-view-blotter" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#viewCaseModal">
                                                    <i class="bi bi-eye me-2 text-info"></i> View Full Case
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-mediate-case" data-id="<?php echo $row['id']; ?>" data-case="<?php echo $row['case_number']; ?>" data-bs-toggle="modal" data-bs-target="#mediationModal">
                                                    <i class="bi bi-hand-thumbs-up me-2 text-warning"></i> Conciliate Case
                                                </button>
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

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 d-flex flex-column flex-sm-row justify-content-between align-items-center px-4 py-3 border-top gap-3">
                <div class="text-muted small">
                    Showing <span class="fw-semibold"><?php echo $offset + 1; ?></span> to <span class="fw-semibold"><?php echo min($offset + $limit, $totalCount); ?></span> of <span class="fw-semibold"><?php echo $totalCount; ?></span> cases
                </div>
                <nav aria-label="Cases Registry Pagination">
                    <ul class="pagination pagination-custom mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $paginationUrl . ($page - 1); ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $paginationUrl . $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $paginationUrl . ($page + 1); ?>"><i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================
     MODALS SECTION (INCIDENT REPORTING AND SETTLEMENTS)
     ======================================================== -->

<!-- 1. FILE DISPUTE REPORT MODAL -->
<div class="modal fade" id="fileCaseModal" tabindex="-1" aria-labelledby="fileCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="fileCaseModalLabel"><i class="bi bi-journal-plus me-2 text-primary"></i>Log New Dispute Incident</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="add_blotter">
                <div class="modal-body p-4">
                    
                    <!-- COMPLAINANT INPUT CONTROL -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Complainant Registry Type</label>
                            <div class="d-flex gap-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="comp_type" id="comp_res" value="resident" checked>
                                    <label class="form-check-label text-dark fw-semibold" for="comp_res">Registered Resident</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="comp_type" id="comp_non" value="non-resident">
                                    <label class="form-check-label text-dark fw-semibold" for="comp_non">Non-Resident / Visitor</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" id="comp_resident_wrapper">
                            <select name="complainant_id" class="form-select select-resident">
                                <option value="">-- Choose Resident --</option>
                                <?php foreach ($residents as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="comp_nonresident_wrapper">
                            <input type="text" name="complainant_non_resident" class="form-control" placeholder="Input Complainant Full Name">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- RESPONDENT INPUT CONTROL -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Respondent Registry Type</label>
                            <div class="d-flex gap-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="resp_type" id="resp_res" value="resident" checked>
                                    <label class="form-check-label text-dark fw-semibold" for="resp_res">Registered Resident</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="resp_type" id="resp_non" value="non-resident">
                                    <label class="form-check-label text-dark fw-semibold" for="resp_non">Non-Resident / Visitor</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" id="resp_resident_wrapper">
                            <select name="respondent_id" class="form-select select-resident">
                                <option value="">-- Choose Resident --</option>
                                <?php foreach ($residents as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['last_name'] . ', ' . $r['first_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="resp_nonresident_wrapper">
                            <input type="text" name="respondent_non_resident" class="form-control" placeholder="Input Respondent Full Name">
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Incident coordinates and narratives -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Incident Classification</label>
                            <select name="incident_type" class="form-select" required>
                                <?php foreach ($incidents as $inc): ?>
                                    <option value="<?php echo $inc; ?>"><?php echo $inc; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Date & Time of Occurrence</label>
                            <input type="datetime-local" name="incident_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Location of Incident</label>
                        <input type="text" name="incident_location" class="form-control" placeholder="e.g. Purok 4, near Barangay Hall" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Case Narrative / Details</label>
                        <textarea name="details" class="form-control" rows="5" placeholder="Narrate details of dispute report..." required></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">File Complaint</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. MEDIATION / STATUS PROCESS MODAL -->
<div class="modal fade" id="mediationModal" tabindex="-1" aria-labelledby="mediationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="mediationModalLabel">
                    <i class="bi bi-handshake me-2 text-warning"></i>Dispute Conciliation: <span id="mediating_case_no" class="text-primary font-monospace"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="mediate_case">
                <input type="hidden" name="case_id" id="mediate_case_id">
                <div class="modal-body p-4">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Update Case Status</label>
                        <select name="status" class="form-select" required>
                            <option value="Active">Active / Ongoing</option>
                            <option value="Scheduled for Mediation">Scheduled for Mediation</option>
                            <option value="Settled">Settled (Amicable Agreement)</option>
                            <option value="Referred to Court">Referred to Court (Certificate to File Action)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Hearing Schedule or Settlement Terms details</label>
                        <textarea name="settlement_details" class="form-control" rows="5" placeholder="Specify mediation schedule coordinates, or terms of settlement agreement..." required></textarea>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Update Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. VIEW FULL CASE DETAILS MODAL -->
<div class="modal fade" id="viewCaseModal" tabindex="-1" aria-labelledby="viewCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="viewCaseModalLabel">
                    <i class="bi bi-journal-text me-2 text-info"></i>Dispute Summary Log
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="view_case_payload">
                <!-- Loaded dynamically via AJAX Fetch -->
            </div>
            <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Window</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto dismiss alert cards smoothly
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

// Complainant Registry Selection Trigger
const toggleWrapper = (radioId, wrapperShow, wrapperHide) => {
    const radio = document.getElementById(radioId);
    if(radio) {
        radio.addEventListener('change', () => {
            document.getElementById(wrapperShow).classList.remove('d-none');
            document.getElementById(wrapperHide).classList.add('d-none');
            
            // clear nested inputs of the hidden wrapper to ensure accurate processing
            const hideInputs = document.getElementById(wrapperHide).querySelectorAll('input, select');
            hideInputs.forEach(i => i.value = '');
        });
    }
};

toggleWrapper('comp_res', 'comp_resident_wrapper', 'comp_nonresident_wrapper');
toggleWrapper('comp_non', 'comp_nonresident_wrapper', 'comp_resident_wrapper');
toggleWrapper('resp_res', 'resp_resident_wrapper', 'resp_nonresident_wrapper');
toggleWrapper('resp_non', 'resp_nonresident_wrapper', 'resp_resident_wrapper');

// Conciliation populator
document.querySelectorAll('.btn-mediate-case').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const caseNo = this.getAttribute('data-case');
        document.getElementById('mediate_case_id').value = id;
        document.getElementById('mediating_case_no').textContent = caseNo;
    });
});

// View Case details loader (targets process.php API)
document.querySelectorAll('.btn-view-blotter').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const payload = document.getElementById('view_case_payload');
        payload.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-info" role="status"></div></div>';
        
        fetch(`process.php?fetch_blotter=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    payload.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                } else {
                    const comp = data.complainant_non_resident || data.resident_complainant;
                    const resp = data.respondent_non_resident || data.resident_respondent;
                    
                    payload.innerHTML = `
                        <div class="row g-4 mb-4">
                            <div class="col-6">
                                <span class="text-muted d-block small mb-1">Case ID Code</span>
                                <strong class="fs-5 text-primary font-monospace">${data.case_number}</strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-muted d-block small mb-1">Status</span>
                                <span class="badge bg-secondary px-3 py-1.5 fw-bold">${data.status}</span>
                            </div>
                        </div>
                        <div class="card bg-light border-0 p-3 mb-4 rounded-3">
                            <div class="row">
                                <div class="col-6 border-end">
                                    <span class="text-muted small d-block mb-1">Complainant / Accuser</span>
                                    <strong class="text-dark">${comp}</strong>
                                </div>
                                <div class="col-6 ps-4">
                                    <span class="text-muted small d-block mb-1">Respondent / Accused</span>
                                    <strong class="text-dark">${resp}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <h6 class="fw-bold text-dark mb-2">Disputed Narrative & Location</h6>
                            <p class="text-secondary small mb-2"><i class="bi bi-geo-alt-fill me-1 text-danger"></i>${data.incident_location} • Occurrence: ${new Date(data.incident_date).toLocaleString()}</p>
                            <p class="p-3 bg-white border rounded-3 text-dark small" style="white-space: pre-wrap; line-height: 1.5;">${data.details}</p>
                        </div>
                        <div>
                            <h6 class="fw-bold text-dark mb-2">Mediation Logs & Settlement Terms</h6>
                            <p class="p-3 bg-white border rounded-3 text-secondary small" style="white-space: pre-wrap; line-height: 1.5;">${data.settlement_details || 'No settlement updates or hearings recorded yet.'}</p>
                        </div>
                        <div class="text-muted text-end mt-4" style="font-size:0.75rem;">
                            Recorded by <strong>${data.recorded_by_name}</strong> on ${new Date(data.created_at).toLocaleDateString()}
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Error fetching case logs:', err);
                payload.innerHTML = '<div class="alert alert-danger">Error retrieving case files.</div>';
            });
    });
});
</script>

</body>
</html>