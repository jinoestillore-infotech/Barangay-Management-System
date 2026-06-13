<?php
// Secure route guarding (Allows Administrators, Captains, and Secretaries)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/HouseholdManager.php';

$database = new Database();
$conn = $database->connect();
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
$filterIncome = isset($_GET['income_filter']) ? $_GET['income_filter'] : '';

$households = $householdManager->getHouseholds($searchTerm, $filterPurok, $filterIncome);

// Whitelists for dynamic dropdown generation
$puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7', 'Zone A', 'Zone B', 'Zone C', 'Sitio Centro', 'Sitio Pag-asa'];
$incomes = ['Low Income', 'Lower Middle Income', 'Middle Income', 'Upper Middle Income', 'High Income', 'Indigent / N/A'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Management - Barangay System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/users.css" rel="stylesheet">
    <style>
        .badge-head { background: rgba(43, 76, 126, 0.1); color: var(--primary-color); }
        .badge-member { background: rgba(100, 116, 139, 0.1); color: #64748b; }
    </style>
</head>
<body>

<div class="container-fluid px-4 dashboard-wrapper">

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 gap-3">
        <div>
            <a href="../dashboard.php" class="back-link d-inline-flex align-items-center mb-3">
                <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
            </a>
            <h1 class="fw-bold text-dark mb-1 fs-2">Household Registry (RBI)</h1>
            <p class="text-muted mb-0">Record physical household clusters, assign purok locations, and trace resident groups.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addHouseholdModal">
                <i class="bi bi-house-add-fill me-2"></i> Register New Household
            </button>
        </div>
    </div>

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

    <div class="card page-card p-4 p-md-5 mb-5">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Search and Filters</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-5">
                <label class="form-label text-secondary small fw-bold">Search Household</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by number or street address..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Filter Purok/Zone</label>
                <select name="purok_filter" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($puroks as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $filterPurok === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label text-secondary small fw-bold">Filter Income Bracket</label>
                <select name="income_filter" class="form-select">
                    <option value="">All Brackets</option>
                    <?php foreach ($incomes as $i): ?>
                        <option value="<?php echo $i; ?>" <?php echo $filterIncome === $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5"><i class="bi bi-filter"></i> Apply Filter</button>
            </div>
        </form>
    </div>

    <div class="card page-card">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-house-gear-fill text-primary me-2"></i>Household Directory</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo count($households); ?> Households Registered</span>
        </div>
        <div class="table-scroll-container">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Household Number</th>
                        <th>Location (Purok & Street)</th>
                        <th>Household Head</th>
                        <th class="text-center">Member Count</th>
                        <th>Income Bracket</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($households)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-house-dash fs-1 mb-2 d-block"></i>
                                No household records match your current criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($households as $row): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-dark"><i class="bi bi-hash text-muted me-1"></i><?php echo htmlspecialchars($row['household_number']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['zone_purok']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($row['street']); ?></div>
                                </td>
                                <td class="text-muted">
                                    <?php if ($row['household_head']): ?>
                                        <span class="text-dark fw-semibold"><i class="bi bi-person-badge-fill text-primary me-1"></i><?php echo htmlspecialchars($row['household_head']); ?></span>
                                    <?php else: ?>
                                        <span class="text-black-50 small"><i class="bi bi-person-dash me-1"></i>No Head Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light border text-dark fw-bold px-2.5 py-1.5"><?php echo (int)$row['total_members']; ?> Members</span>
                                </td>
                                <td>
                                    <span class="small fw-medium text-secondary"><?php echo htmlspecialchars($row['income_bracket']); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn-action-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg dropdown-menu-custom">
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-view-members" data-id="<?php echo $row['id']; ?>" data-num="<?php echo htmlspecialchars($row['household_number']); ?>" data-bs-toggle="modal" data-bs-target="#viewMembersModal">
                                                    <i class="bi bi-people me-2 text-info"></i> View Inhabitants
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-edit-household" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#editHouseholdModal">
                                                    <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Details
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
    </div>
</div>

<div class="modal fade" id="addHouseholdModal" tabindex="-1" aria-labelledby="addHouseholdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="addHouseholdModalLabel"><i class="bi bi-house-add-fill me-2 text-primary"></i>Register Household</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="add_household">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Household Number / Sticker ID</label>
                        <input type="text" name="household_number" class="form-control" placeholder="e.g. HH-2026-0042" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Street / Block / Address Details</label>
                        <input type="text" name="street" class="form-control" placeholder="e.g. 123 Mahogany St." required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Purok / Zone Selector</label>
                            <select name="zone_purok" class="form-select" required>
                                <?php foreach ($puroks as $p): ?>
                                    <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Income Classification</label>
                            <select name="income_bracket" class="form-select" required>
                                <?php foreach ($incomes as $i): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Create Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editHouseholdModal" tabindex="-1" aria-labelledby="editHouseholdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="editHouseholdModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Modify Household Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="edit_household">
                <input type="hidden" name="edit_household_id" id="edit_household_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Household Number / Sticker ID</label>
                        <input type="text" name="edit_household_number" id="edit_household_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Street / Block / Address Details</label>
                        <input type="text" name="edit_street" id="edit_street" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Purok / Zone Selector</label>
                            <select name="edit_zone_purok" id="edit_zone_purok" class="form-select" required>
                                <?php foreach ($puroks as $p): ?>
                                    <option value="<?php echo $p; ?>"><?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Income Classification</label>
                            <select name="edit_income_bracket" id="edit_income_bracket" class="form-select" required>
                                <?php foreach ($incomes as $i): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endforeach; ?>
                            </select>
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

<div class="modal fade" id="viewMembersModal" tabindex="-1" aria-labelledby="viewMembersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="viewMembersModalLabel">
                    <i class="bi bi-people-fill me-2 text-info"></i>Household Members: <span id="members_hh_number" class="text-primary font-monospace"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Inhabitant Name</th>
                                <th>Relationship to Head</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Classifications</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="members_table_body">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Window</button>
            </div>
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

// FIXED: AJAX queries now target process.php to fetch database payloads
document.querySelectorAll('.btn-edit-household').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        fetch(`process.php?fetch_household=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    document.getElementById('edit_household_id').value = data.id;
                    document.getElementById('edit_household_number').value = data.household_number;
                    document.getElementById('edit_street').value = data.street;
                    document.getElementById('edit_zone_purok').value = data.zone_purok;
                    document.getElementById('edit_income_bracket').value = data.income_bracket;
                }
            })
            .catch(err => console.error('Error fetching household data:', err));
    });
});

document.querySelectorAll('.btn-view-members').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const hhNum = this.getAttribute('data-num');
        
        document.getElementById('members_hh_number').textContent = hhNum;
        const tableBody = document.getElementById('members_table_body');
        tableBody.innerHTML = `<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-info" role="status"></div></td></tr>`;
        
        fetch(`process.php?fetch_members=${id}`)
            .then(res => res.json())
            .then(data => {
                tableBody.innerHTML = '';
                if (data.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-people-fill me-1"></i> No residents linked.</td></tr>`;
                    return;
                }
                
                data.forEach(m => {
                    const birthYear = new Date(m.birth_date).getFullYear();
                    const currentYear = new Date().getFullYear();
                    const age = currentYear - birthYear;

                    let classifications = '';
                    if (m.is_senior == 1) classifications += '<span class="badge bg-warning text-dark me-1 small">Senior</span>';
                    if (m.is_pwd == 1) classifications += '<span class="badge bg-purple text-white me-1 small" style="background:#6f42c1;">PWD</span>';
                    if (classifications === '') classifications = '<span class="text-black-50 small">-</span>';

                    const relBadge = m.relationship_to_head === 'Head' ? 'badge-head' : 'badge-member';
                    const statusBadge = m.status === 'Active' ? 'badge-active' : 'badge-inactive';

                    tableBody.innerHTML += `
                        <tr>
                            <td><span class="fw-bold text-dark">${m.first_name} ${m.middle_name ? m.middle_name[0] + '.' : ''} ${m.last_name}</span></td>
                            <td><span class="badge ${relBadge} fw-semibold">${m.relationship_to_head}</span></td>
                            <td>${m.gender}</td>
                            <td>${age} yrs old</td>
                            <td>${classifications}</td>
                            <td><span class="badge ${statusBadge} fw-bold">${m.status}</span></td>
                        </tr>
                    `;
                });
            })
            .catch(err => {
                console.error('Error fetching members:', err);
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">Error loading members.</td></tr>`;
            });
    });
});
</script>

</body>
</html>