<?php
// Secure route guarding (Allows Administrators, Captains, Secretaries, and Treasurers)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary', 'Treasurer']);

require_once '../../classes/Database.php';
require_once '../../classes/PaymentManager.php';
require_once '../../classes/ResidentManager.php';

$database = new Database();
$conn = $database->connect();
$paymentManager = new PaymentManager($conn);
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
$filterPurpose = isset($_GET['purpose_filter']) ? $_GET['purpose_filter'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Retrieve count and paginated items
$records = $paymentManager->getPayments($searchTerm, $filterPurpose, $startDate, $endDate, $limit, $offset);
$totalRecords = $paymentManager->getPaymentsCount($searchTerm, $filterPurpose, $startDate, $endDate);
$totalPages = ceil($totalRecords / $limit);
if ($totalPages < 1) $totalPages = 1;

// Fetch aggregate analytics summaries
$financials = $paymentManager->getFinancialSummaries();

// Fetch active residents for the payer picker
$residentsList = $residentManager->getResidents('', '', '', 'Active', 1000, 0);

// Pre-defined whitelisted fee categories
$purposes = [
    'Barangay Clearance Fee',
    'Business Clearance Fee',
    'Certificate of Residency Fee',
    'Certificate of Indigency Fee',
    'Barangay Facility Rental',
    'Logistical Asset Rental',
    'Blotter Filing Fee',
    'Miscellaneous Fee'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Collections & Receipts - Barangay System</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/finance.css" rel="stylesheet">
    <style>
        .card-analytics {
            border-left: 4px solid var(--primary-color) !important;
        }
        .text-currency {
            font-family: 'Courier New', Courier, monospace;
            font-weight: 700;
        }
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
            <h1 class="fw-bold text-dark mb-1 fs-2">Collections & Financial Ledger</h1>
            <p class="text-muted mb-0">Record public collections, register Official Receipts (O.R.), and track barangay revenues.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                <i class="bi bi-receipt-cutoff me-2"></i> Issue New Receipt (O.R.)
            </button>
        </div>
    </div>

    <!-- Live Finance Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card page-card p-4 h-100 mb-0 card-analytics" style="border-left-color: #0d6efd !important;">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 me-3"><i class="bi bi-bank fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Cumulative Revenue</span>
                </div>
                <h3 class="fw-bold text-dark mb-1 text-currency">₱<?php echo number_format($financials['total_revenue'], 2); ?></h3>
                <small class="text-muted">Total Cash Flow Collected</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card page-card p-4 h-100 mb-0 card-analytics" style="border-left-color: #198754 !important;">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-success-subtle text-success rounded-3 me-3"><i class="bi bi-cash-stack fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Collections Today</span>
                </div>
                <h3 class="fw-bold text-dark mb-1 text-currency">₱<?php echo number_format($financials['today_collections'], 2); ?></h3>
                <small class="text-muted">Official Cash Intake Today</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card page-card p-4 h-100 mb-0 card-analytics" style="border-left-color: #0dcaf0 !important;">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-info-subtle text-info rounded-3 me-3"><i class="bi bi-file-earmark-check fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Certificates Portion</span>
                </div>
                <h3 class="fw-bold text-dark mb-1 text-currency">₱<?php echo number_format($financials['clearance_shares'], 2); ?></h3>
                <small class="text-muted">Clearances & Permits Portion</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card page-card p-4 h-100 mb-0 card-analytics" style="border-left-color: #6f42c1 !important;">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-purple text-white rounded-3 me-3" style="background-color: rgba(111, 66, 193, 0.1) !important; color: #6f42c1 !important;"><i class="bi bi-receipt fs-4"></i></div>
                    <span class="text-secondary small fw-bold">Receipts Count</span>
                </div>
                <h3 class="fw-bold text-dark mb-1"><?php echo (int)($financials['total_receipts_issued'] ?? 0); ?></h3>
                <small class="text-muted">COA Official Receipts Issued</small>
            </div>
        </div>
    </div>

    <!-- Dismissible Feedback Banners -->
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

    <!-- Advanced Search, Date, and Category Filters -->
    <div class="card page-card p-4 p-md-5 mb-5">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Filter Transactions Ledger</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-4">
                <label class="form-label text-secondary small fw-bold">Search Keywords</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="O.R. number, payer name..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Fee Classification</label>
                <select name="purpose_filter" class="form-select">
                    <option value="">All Payment Types</option>
                    <?php foreach ($purposes as $purp): ?>
                        <option value="<?php echo $purp; ?>" <?php echo $filterPurpose === $purp ? 'selected' : ''; ?>><?php echo $purp; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Date Bounds (Range)</label>
                <div class="input-group">
                    <input type="date" name="start_date" class="form-control small" value="<?php echo htmlspecialchars($startDate); ?>">
                    <span class="input-group-text bg-light text-muted">-</span>
                    <input type="date" name="end_date" class="form-control small" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
            </div>
            <div class="col-12 col-md-2 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5"><i class="bi bi-filter"></i> Apply Filters</button>
            </div>
        </form>
    </div>

    <!-- Main Payment Records Ledger Table -->
    <div class="card page-card">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-receipt text-primary me-2"></i>Official Receipt Journal</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo $totalRecords; ?> Receipts Filed</span>
        </div>
        <div class="table-scroll-container">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Receipt O.R. #</th>
                        <th>Payer Person Entity</th>
                        <th>Classification Purpose</th>
                        <th>Amount Paid</th>
                        <th>Date Processed</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-cash-stack fs-1 mb-2 d-block text-black-50"></i>
                                No receipt transactions match your current query params.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-dark"><i class="bi bi-hash text-muted me-0.5"></i><?php echo htmlspecialchars($row['or_number']); ?></span>
                                </td>
                                <td>
                                    <?php if ($row['resident_payer']): ?>
                                        <span class="fw-bold text-dark"><i class="bi bi-person-fill text-primary me-1"></i><?php echo htmlspecialchars($row['resident_payer']); ?></span>
                                    <?php else: ?>
                                        <span class="fw-bold text-secondary"><i class="bi bi-building text-muted me-1"></i><?php echo htmlspecialchars($row['payer_name']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light border text-dark fw-bold px-2.5 py-1.5"><i class="bi bi-tag-fill text-muted me-1"></i><?php echo htmlspecialchars($row['payment_for']); ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success text-currency">₱<?php echo number_format((float)$row['amount'], 2); ?></span>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-dark"><?php echo date('M d, Y - h:i A', strtotime($row['payment_date'])); ?></span>
                                </td>
                                <td>
                                    <span class="small text-muted"><i class="bi bi-shield-lock-fill me-1"></i><?php echo htmlspecialchars($row['cashier_name'] ?? 'System Process'); ?></span>
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
                            <a class="page-link border-0 rounded-circle" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchTerm); ?>&purpose_filter=<?php echo urlencode($filterPurpose); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $page === $p ? 'active' : ''; ?>">
                                <a class="page-link border-0 rounded-circle <?php echo $page === $p ? 'bg-primary text-white' : 'text-dark'; ?>" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($searchTerm); ?>&purpose_filter=<?php echo urlencode($filterPurpose); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 rounded-circle" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchTerm); ?>&purpose_filter=<?php echo urlencode($filterPurpose); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>"><i class="bi bi-chevron-right"></i></a>
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

<!-- 1. RECORD PAYMENT MODAL -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="recordPaymentModalLabel"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Record Cash Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="add_payment">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">COA Official Receipt Number (O.R. #)</label>
                        <input type="text" name="or_number" class="form-control font-monospace" placeholder="e.g. OR-2026-9904" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Payer Profile Classification</label>
                        <select id="payer_status" class="form-select" onchange="togglePayerStatus()">
                            <option value="resident">Registered Resident Inhabitant</option>
                            <option value="non_resident">Non-Resident / Local Business Entity</option>
                        </select>
                    </div>

                    <!-- Resident Select wrapper -->
                    <div class="mb-3" id="payer_resident_wrapper">
                        <label class="form-label small fw-bold text-secondary">Search Resident Inhabitant</label>
                        <select name="resident_id" class="form-select">
                            <option value="">Select Resident</option>
                            <?php foreach ($residentsList as $res): ?>
                                <option value="<?php echo $res['id']; ?>"><?php echo htmlspecialchars($res['last_name'] . ', ' . $res['first_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Non-resident fallback input -->
                    <div class="mb-3" id="payer_non_resident_wrapper" style="display: none;">
                        <label class="form-label small fw-bold text-secondary">Payer Entity / Complete Name</label>
                        <input type="text" name="payer_name" class="form-control" placeholder="e.g. Juan De La Cruz / ABC Hardware Inc.">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-secondary">Classification Purpose</label>
                            <select name="purpose" class="form-select" required>
                                <?php foreach ($purposes as $purp): ?>
                                    <option value="<?php echo $purp; ?>"><?php echo $purp; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold text-secondary">Cash Amount Collected (PHP)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">₱</span>
                                <input type="number" step="0.01" min="0.00" name="amount" class="form-control" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Payment Processing Date & Time</label>
                        <input type="datetime-local" name="payment_date" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        <small class="text-muted d-block mt-1">Leave defaults to record transactions on the current exact time.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Log Transaction</button>
                </div>
            </form>
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

// Selector toggle mapping resident and non-resident input nodes
function togglePayerStatus() {
    const status = document.getElementById('payer_status').value;
    const resWrap = document.getElementById('payer_resident_wrapper');
    const nonResWrap = document.getElementById('payer_non_resident_wrapper');

    if (status === 'resident') {
        resWrap.style.display = 'block';
        nonResWrap.style.display = 'none';
        nonResWrap.querySelector('input').removeAttribute('required');
    } else {
        resWrap.style.display = 'none';
        nonResWrap.style.display = 'block';
        nonResWrap.querySelector('input').setAttribute('required', 'required');
    }
}
</script>

</body>
</html>