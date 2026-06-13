<?php
// Secure route guarding (Allows Administrators, Captains, and Secretaries)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/ResidentManager.php';
require_once '../../classes/HouseholdManager.php';

$database = new Database();
$conn = $database->connect();
$residentManager = new ResidentManager($conn);
$householdManager = new HouseholdManager($conn);

// Retrieve and clear flash session banners cleanly
$successMsg = $_SESSION['success_flash'] ?? '';
$errorMsg = $_SESSION['error_flash'] ?? '';
unset($_SESSION['success_flash'], $_SESSION['error_flash']);

// --------------------------------------------------------
// SEARCH & FILTER RETRIEVAL
// --------------------------------------------------------
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterPurok = isset($_GET['purok_filter']) ? $_GET['purok_filter'] : '';
$filterClass = isset($_GET['class_filter']) ? $_GET['class_filter'] : '';
$filterStatus = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// --------------------------------------------------------
// PAGINATION SETUP
// --------------------------------------------------------
$limit = 10; // Maximum items per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Pull matching total inhabitants count
$totalCount = $residentManager->getResidentsCount($searchTerm, $filterPurok, $filterClass, $filterStatus);
$totalPages = ceil($totalCount / $limit);

// Pull paginated slice
$residents = $residentManager->getResidents($searchTerm, $filterPurok, $filterClass, $filterStatus, $limit, $offset);
$demographics = $residentManager->getDemographicStats();

// Pull all registered households for the linkage selector dropdown
$households = $householdManager->getHouseholds();

// Dropdown lists
$puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7', 'Zone A', 'Zone B', 'Zone C', 'Sitio Centro', 'Sitio Pag-asa'];
$genders = ['Male', 'Female', 'Other'];
$civilStatuses = ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'];
$statuses = ['Active', 'Deceased', 'Moved Out'];

// Helper to keep query parameters in pagination links
$queryString = http_build_query(array_filter([
    'search' => $searchTerm,
    'purok_filter' => $filterPurok,
    'class_filter' => $filterClass,
    'status_filter' => $filterStatus
]));
$paginationUrl = 'index.php?' . (!empty($queryString) ? $queryString . '&' : '') . 'page=';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registry - Barangay System</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/users.css" rel="stylesheet">
    <style>
        /* Contextual colors for demographic summaries */
        .bg-light-primary { background: rgba(43, 76, 126, 0.08); color: var(--primary-color); }
        .bg-light-success { background: rgba(25, 135, 84, 0.08); color: #198754; }
        .bg-light-warning { background: rgba(255, 193, 7, 0.08); color: #b58105; }
        .bg-light-purple { background: rgba(111, 66, 193, 0.08); color: #6f42c1; }
        .bg-light-danger { background: rgba(220, 53, 69, 0.08); color: #dc3545; }
        
        .badge-head { background: rgba(43, 76, 126, 0.1); color: var(--primary-color); }
        .badge-member { background: rgba(100, 116, 139, 0.1); color: #64748b; }

        /* Custom styling for pagination buttons */
        .pagination-custom .page-link {
            color: var(--primary-color);
            border-color: #e2e8f0;
            padding: 0.5rem 0.85rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .pagination-custom .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #ffffff;
        }
        .pagination-custom .page-link:focus {
            box-shadow: 0 0 0 3px rgba(43, 76, 126, 0.15);
        }
    </style>
</head>
<body>

<div class="container-fluid px-4 dashboard-wrapper">

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 gap-3">
        <div>
            <a href="../dashboard.php" class="back-link d-inline-flex align-items-center mb-3">
                <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
            </a>
            <h1 class="fw-bold text-dark mb-1 fs-2">Resident Registry (RBI)</h1>
            <p class="text-muted mb-0">Manage individual demographic profiles, dynamic age filters, vital statuses, and family relationships.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addResidentModal">
                <i class="bi bi-person-plus-fill me-2"></i> Register Resident
            </button>
        </div>
    </div>

    <!-- Summary Statistics Dashboard Card -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light-primary"><i class="bi bi-people-fill fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Active Inhabitants</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($demographics['total_active'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light-warning"><i class="bi bi-person-badge-fill fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Senior Citizens</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($demographics['seniors'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light-purple"><i class="bi bi-heart-pulse-fill fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">PWD Sector</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($demographics['pwds'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center bg-white rounded-3">
                <div class="p-3 rounded-circle me-3 bg-light-success"><i class="bi bi-clipboard2-check-fill fs-4"></i></div>
                <div>
                    <div class="text-muted small fw-medium">Registered Voters</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo number_format($demographics['voters'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dismissible Feedback Alerts -->
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
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Filter Registry</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-4">
                <label class="form-label text-secondary small fw-bold">Search Inhabitants</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, ID, or phone..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label text-secondary small fw-bold">Purok / Location</label>
                <select name="purok_filter" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($puroks as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $filterPurok === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-4 col-md-2">
                <label class="form-label text-secondary small fw-bold">Demographics</label>
                <select name="class_filter" class="form-select">
                    <option value="">All Residents</option>
                    <option value="Senior" <?php echo $filterClass === 'Senior' ? 'selected' : ''; ?>>Senior Citizens</option>
                    <option value="PWD" <?php echo $filterClass === 'PWD' ? 'selected' : ''; ?>>PWD Sector</option>
                    <option value="Voter" <?php echo $filterClass === 'Voter' ? 'selected' : ''; ?>>Registered Voters</option>
                </select>
            </div>
            <div class="col-12 col-sm-4 col-md-2">
                <label class="form-label text-secondary small fw-bold">Vital Status</label>
                <select name="status_filter" class="form-select">
                    <option value="Active" <?php echo $filterStatus === 'Active' || $filterStatus === '' ? 'selected' : ''; ?>>Active</option>
                    <option value="Deceased" <?php echo $filterStatus === 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                    <option value="Moved Out" <?php echo $filterStatus === 'Moved Out' ? 'selected' : ''; ?>>Moved Out</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5 px-3"><i class="bi bi-filter"></i></button>
            </div>
        </form>
    </div>

    <!-- Main Inhabitants Directory Card -->
    <div class="card page-card">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Inhabitants Registry</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo $totalCount; ?> Residents Registered</span>
        </div>
        <div class="table-scroll-container">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>National ID</th>
                        <th>Geographics (Household / Purok)</th>
                        <th>Civil Status</th>
                        <th>Classifications</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($residents)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-person-exclamation fs-1 mb-2 d-block"></i>
                                No resident profiles match your current search constraints.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($residents as $row): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['extension_name'] ?? '')); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <?php echo $row['gender'] . ' • ' . (date_diff(date_create($row['birth_date']), date_create('today'))->y) . ' yrs old'; ?>
                                    </div>
                                </td>
                                <td class="font-monospace text-secondary small">
                                    <?php echo !empty($row['national_id']) ? htmlspecialchars(substr($row['national_id'], 0, 4) . ' - XXXX - XXXX') : '<span class="text-black-50">N/A</span>'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['household_number'])): ?>
                                        <div class="fw-semibold text-dark"><i class="bi bi-house-fill text-muted me-1"></i><?php echo htmlspecialchars($row['household_number']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($row['zone_purok']) . ' (' . htmlspecialchars($row['relationship_to_head'] ?? 'Member') . ')'; ?></div>
                                    <?php else: ?>
                                        <span class="text-black-50 small"><i class="bi bi-geo-alt me-1"></i>Unlinked Household</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small fw-medium text-dark"><?php echo htmlspecialchars($row['civil_status']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <?php if ($row['is_senior'] == 1): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1 small">Senior</span>
                                        <?php endif; ?>
                                        <?php if ($row['is_pwd'] == 1): ?>
                                            <span class="badge bg-purple text-white px-2 py-1 small" style="background:#6f42c1;">PWD</span>
                                        <?php endif; ?>
                                        <?php if ($row['is_voter'] == 1): ?>
                                            <span class="badge bg-success text-white px-2 py-1 small">Voter</span>
                                        <?php endif; ?>
                                        <?php if ($row['is_senior'] == 0 && $row['is_pwd'] == 0 && $row['is_voter'] == 0): ?>
                                            <span class="text-black-50 small">-</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn-action-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg dropdown-menu-custom">
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-edit-resident" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#editResidentModal">
                                                    <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Profile
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <form action="process.php" method="POST">
                                                    <input type="hidden" name="action" value="toggle_vital_status">
                                                    <input type="hidden" name="resident_id" value="<?php echo $row['id']; ?>">
                                                    <?php if ($row['status'] !== 'Active'): ?>
                                                        <button type="submit" name="status" value="Active" class="dropdown-item dropdown-item-custom d-flex align-items-center text-success">
                                                            <i class="bi bi-check-circle me-2"></i> Mark as Active
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($row['status'] !== 'Deceased'): ?>
                                                        <button type="submit" name="status" value="Deceased" class="dropdown-item dropdown-item-custom d-flex align-items-center text-danger" onclick="return confirm('Mark this resident as Deceased? This will deactivate their profile.');">
                                                            <i class="bi bi-heartbreak-fill me-2"></i> Mark as Deceased
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($row['status'] !== 'Moved Out'): ?>
                                                        <button type="submit" name="status" value="Moved Out" class="dropdown-item dropdown-item-custom d-flex align-items-center text-secondary">
                                                            <i class="bi bi-arrow-right-circle me-2"></i> Mark as Moved Out
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
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

        <!-- UI Server-Side Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-0 d-flex flex-column flex-sm-row justify-content-between align-items-center px-4 py-3 border-top gap-3">
                <div class="text-muted small">
                    Showing <span class="fw-semibold"><?php echo $offset + 1; ?></span> to <span class="fw-semibold"><?php echo min($offset + $limit, $totalCount); ?></span> of <span class="fw-semibold"><?php echo $totalCount; ?></span> entries
                </div>
                <nav aria-label="Residents Directory Pagination">
                    <ul class="pagination pagination-custom mb-0">
                        <!-- Previous Page Anchor Button -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $paginationUrl . ($page - 1); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <!-- Numerical Page Selection Buttons -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $paginationUrl . $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Next Page Anchor Button -->
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $paginationUrl . ($page + 1); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========================================================
     MODALS SECTION (RESIDENT MANAGEMENT DIALOGS)
     ======================================================== -->

<!-- 1. REGISTER RESIDENT MODAL -->
<div class="modal fade" id="addResidentModal" tabindex="-1" aria-labelledby="addResidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="addResidentModalLabel"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Register Inhabitant Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="add_resident">
                <div class="modal-body p-4">
                    
                    <!-- Form Row 1: National Identification & Linked Household -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">National ID (PhilSys Card)</label>
                            <input type="text" name="national_id" class="form-control" placeholder="XXXX-XXXX-XXXX-XXXX">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Link Household Container (RBI)</label>
                            <select name="household_id" class="form-select">
                                <option value="">-- No Linked Household --</option>
                                <?php foreach ($households as $hh): ?>
                                    <option value="<?php echo $hh['id']; ?>">
                                        <?php echo htmlspecialchars($hh['household_number'] . ' - ' . $hh['street'] . ', ' . $hh['zone_purok']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Form Row 2: Complete Name Split -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">First Name</label>
                            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" placeholder="Middle Name">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary">Last Name</label>
                            <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label small fw-bold text-secondary">Extension</label>
                            <input type="text" name="extension_name" class="form-control" placeholder="e.g. Jr, III">
                        </div>
                    </div>

                    <!-- Form Row 3: Vital Demographics -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Birth Date</label>
                            <input type="date" name="birth_date" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label small fw-bold text-secondary">Birth Place</label>
                            <input type="text" name="birth_place" class="form-control" placeholder="City or Municipality">
                        </div>
                    </div>

                    <!-- Form Row 4: Genders, Civil, & Citizenship -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Gender</label>
                            <select name="gender" class="form-select" required>
                                <?php foreach ($genders as $g): ?>
                                    <option value="<?php echo $g; ?>"><?php echo $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Civil Status</label>
                            <select name="civil_status" class="form-select" required>
                                <?php foreach ($civilStatuses as $cs): ?>
                                    <option value="<?php echo $cs; ?>"><?php echo $cs; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Citizenship</label>
                            <input type="text" name="citizenship" class="form-control" value="Filipino" required>
                        </div>
                    </div>

                    <!-- Form Row 5: Local affiliations -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Religion</label>
                            <input type="text" name="religion" class="form-control" placeholder="e.g. Roman Catholic">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Occupation</label>
                            <input type="text" name="occupation" class="form-control" placeholder="Job Title / Profession">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Relationship to Household Head</label>
                            <select name="relationship_to_head" class="form-select">
                                <option value="">-- No relationship (Unlinked) --</option>
                                <option value="Head">Head of Household</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Son">Son</option>
                                <option value="Daughter">Daughter</option>
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Relative">Relative</option>
                                <option value="Helper">Kasambahay / Helper</option>
                                <option value="Other">Other Resident</option>
                            </select>
                        </div>
                    </div>

                    <!-- Form Row 6: Contact & Demographics Checkboxes -->
                    <div class="row g-3 mb-4 align-items-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="e.g. 09171234567">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="resident@email.com">
                        </div>
                    </div>

                    <!-- Demographics Flags & PWD category toggle -->
                    <div class="row g-3 align-items-center border-top pt-3">
                        <div class="col-12 col-md-4 d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_voter" value="1" id="voterFlag">
                                <label class="form-check-label small fw-bold" for="voterFlag">Registered Voter</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_pwd" value="1" id="pwdFlag">
                                <label class="form-check-label small fw-bold text-purple" for="pwdFlag" style="color:#6f42c1;">PWD Flag</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <input type="text" name="pwd_type" id="pwdTypeInput" class="form-control" placeholder="Specify disability type (Enabled only if PWD Flag is checked)" disabled>
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Register Resident</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. EDIT RESIDENT MODAL -->
<div class="modal fade" id="editResidentModal" tabindex="-1" aria-labelledby="editResidentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="editResidentModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Modify Inhabitant Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="edit_resident">
                <input type="hidden" name="resident_id" id="edit_resident_id">
                <div class="modal-body p-4">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">National ID (PhilSys Card)</label>
                            <input type="text" name="national_id" id="edit_national_id" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Link Household Container (RBI)</label>
                            <select name="household_id" id="edit_household_id" class="form-select">
                                <option value="">-- No Linked Household --</option>
                                <?php foreach ($households as $hh): ?>
                                    <option value="<?php echo $hh['id']; ?>">
                                        <?php echo htmlspecialchars($hh['household_number'] . ' - ' . $hh['street'] . ', ' . $hh['zone_purok']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">First Name</label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary">Middle Name</label>
                            <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-bold text-secondary">Last Name</label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label small fw-bold text-secondary">Extension</label>
                            <input type="text" name="extension_name" id="edit_extension_name" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Birth Date</label>
                            <input type="date" name="birth_date" id="edit_birth_date" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label small fw-bold text-secondary">Birth Place</label>
                            <input type="text" name="birth_place" id="edit_birth_place" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Gender</label>
                            <select name="gender" id="edit_gender" class="form-select" required>
                                <?php foreach ($genders as $g): ?>
                                    <option value="<?php echo $g; ?>"><?php echo $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Civil Status</label>
                            <select name="civil_status" id="edit_civil_status" class="form-select" required>
                                <?php foreach ($civilStatuses as $cs): ?>
                                    <option value="<?php echo $cs; ?>"><?php echo $cs; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Citizenship</label>
                            <input type="text" name="citizenship" id="edit_citizenship" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Religion</label>
                            <input type="text" name="religion" id="edit_religion" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Occupation</label>
                            <input type="text" name="occupation" id="edit_occupation" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-bold text-secondary">Relationship to Household Head</label>
                            <select name="relationship_to_head" id="edit_relationship_to_head" class="form-select">
                                <option value="">-- No relationship (Unlinked) --</option>
                                <option value="Head">Head of Household</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Son">Son</option>
                                <option value="Daughter">Daughter</option>
                                <option value="Father">Father</option>
                                <option value="Mother">Mother</option>
                                <option value="Relative">Relative</option>
                                <option value="Helper">Kasambahay / Helper</option>
                                <option value="Other">Other Resident</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Contact Number</label>
                            <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 align-items-center border-top pt-3">
                        <div class="col-12 col-md-4 d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_voter" value="1" id="edit_voterFlag">
                                <label class="form-check-label small fw-bold" for="edit_voterFlag">Registered Voter</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_pwd" value="1" id="edit_pwdFlag">
                                <label class="form-check-label small fw-bold text-purple" for="edit_pwdFlag" style="color:#6f42c1;">PWD Flag</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-8">
                            <input type="text" name="pwd_type" id="edit_pwdTypeInput" class="form-control" placeholder="Specify disability type">
                        </div>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Update Details</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alert notification cards smoothly
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

// Enable / Disable PWD Category fields dynamically based on checkbox state
const handlePwdState = (checkboxId, inputId) => {
    const chk = document.getElementById(checkboxId);
    const inp = document.getElementById(inputId);
    if (chk && inp) {
        chk.addEventListener('change', () => {
            inp.disabled = !chk.checked;
            if(!chk.checked) inp.value = '';
        });
    }
};

handlePwdState('pwdFlag', 'pwdTypeInput');
handlePwdState('edit_pwdFlag', 'edit_pwdTypeInput');

// Edit Resident modal dynamic values populator via fetch (Targets process.php)
document.querySelectorAll('.btn-edit-resident').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        fetch(`process.php?fetch_resident=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    document.getElementById('edit_resident_id').value = data.id;
                    document.getElementById('edit_national_id').value = data.national_id || '';
                    document.getElementById('edit_household_id').value = data.household_id || '';
                    document.getElementById('edit_first_name').value = data.first_name;
                    document.getElementById('edit_middle_name').value = data.middle_name || '';
                    document.getElementById('edit_last_name').value = data.last_name;
                    document.getElementById('edit_extension_name').value = data.extension_name || '';
                    document.getElementById('edit_birth_date').value = data.birth_date;
                    document.getElementById('edit_birth_place').value = data.birth_place || '';
                    document.getElementById('edit_gender').value = data.gender;
                    document.getElementById('edit_civil_status').value = data.civil_status;
                    document.getElementById('edit_citizenship').value = data.citizenship;
                    document.getElementById('edit_religion').value = data.religion || '';
                    document.getElementById('edit_occupation').value = data.occupation || '';
                    document.getElementById('edit_relationship_to_head').value = data.relationship_to_head || '';
                    document.getElementById('edit_contact_number').value = data.contact_number || '';
                    document.getElementById('edit_email').value = data.email || '';
                    
                    const editPwdChk = document.getElementById('edit_pwdFlag');
                    const editPwdInp = document.getElementById('edit_pwdTypeInput');
                    
                    document.getElementById('edit_voterFlag').checked = (data.is_voter == 1);
                    editPwdChk.checked = (data.is_pwd == 1);
                    editPwdInp.value = data.pwd_type || '';
                    editPwdInp.disabled = (data.is_pwd != 1);
                }
            })
            .catch(err => console.error('Error fetching resident database file:', err));
    });
});
</script>

</body>
</html>