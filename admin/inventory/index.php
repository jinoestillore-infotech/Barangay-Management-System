<?php
// Secure route guarding (Allows Administrators, Captains, and Secretaries)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator', 'Barangay Captain', 'Secretary']);

require_once '../../classes/Database.php';
require_once '../../classes/InventoryManager.php';

$database = new Database();
$conn = $database->connect();
$inventoryManager = new InventoryManager($conn);

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
$filterCondition = isset($_GET['condition_filter']) ? $_GET['condition_filter'] : '';

// Retrieve count and paginated items
$items = $inventoryManager->getInventory($searchTerm, $filterCondition, $limit, $offset);
$totalRecords = $inventoryManager->getInventoryCount($searchTerm, $filterCondition);
$totalPages = ceil($totalRecords / $limit);
if ($totalPages < 1) $totalPages = 1;

// Pre-defined condition whitelists matching exactly the DB schema ENUM constraints
$conditions = ['Excellent', 'Good', 'Fair', 'Damaged', 'Unusable'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics & Assets Inventory - Barangay System</title>
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/users.css" rel="stylesheet">
    <style>
        /* Specific inventory conditions and badges mapping styling */
        .badge-good { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .badge-fair { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .badge-damaged { background: rgba(255, 193, 7, 0.15); color: #b58105; }
        .badge-lost { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
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
            <h1 class="fw-bold text-dark mb-1 fs-2">Property & Assets Inventory</h1>
            <p class="text-muted mb-0">Record public-owned utilities, tracking physical quantities, conditions, and locations.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="bi bi-plus-circle-fill me-2"></i> New Property
            </button>
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
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Search and Filters</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-7">
                <label class="form-label text-secondary small fw-bold">Search Assets / Logistics</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by item name, asset code, or storage location..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Filter Condition</label>
                <select name="condition_filter" class="form-select">
                    <option value="">All Physical Conditions</option>
                    <?php foreach ($conditions as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo $filterCondition === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5"><i class="bi bi-filter"></i> Apply Filter</button>
            </div>
        </form>
    </div>

    <!-- Main Table Listing Card -->
    <div class="card page-card">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-box-seam text-primary me-2"></i>Logistics Ledger</h5>
            <span class="badge bg-secondary rounded-pill"><?php echo $totalRecords; ?> Assets Recorded</span>
        </div>
        <div class="table-scroll-container">
            <table class="table table-hover table-custom">
                <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Property Name</th>
                        <th class="text-center">Total Stock</th>
                        <th class="text-center">Available Stock</th>
                        <th>Condition</th>
                        <th>Storage Location</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam-fill fs-1 mb-2 d-block text-black-50"></i>
                                No assets matched your current search filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $row): ?>
                            <?php 
                                // Map condition string to visual badges
                                $condBadge = 'badge-good';
                                if ($row['condition'] === 'Excellent') $condBadge = 'badge-good';
                                if ($row['condition'] === 'Fair') $condBadge = 'badge-fair';
                                if ($row['condition'] === 'Damaged') $condBadge = 'badge-damaged';
                                if ($row['condition'] === 'Unusable') $condBadge = 'badge-lost';

                                // Safety ratio tracker visualizer
                                $ratio = $row['quantity'] > 0 ? ($row['available_quantity'] / $row['quantity']) * 100 : 0;
                                $progressColor = 'bg-success';
                                if ($ratio <= 25) $progressColor = 'bg-danger';
                                elseif ($ratio <= 75) $progressColor = 'bg-warning';
                            ?>
                            <tr>
                                <td>
                                    <span class="fw-bold font-monospace text-dark"><?php echo htmlspecialchars($row['asset_code']); ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['item_name']); ?></div>
                                    <?php if(!empty($row['notes'])): ?>
                                        <div class="text-muted small text-truncate" style="max-width: 250px;"><i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($row['notes']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-semibold text-dark"><?php echo (int)$row['quantity']; ?> pcs</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-block" style="width: 120px;">
                                        <div class="d-flex align-items-center justify-content-between mb-1 small">
                                            <span class="fw-bold text-dark"><?php echo (int)$row['available_quantity']; ?> left</span>
                                            <span class="text-muted text-end"><?php echo round($ratio); ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar <?php echo $progressColor; ?>" role="progressbar" style="width: <?php echo $ratio; ?>%" aria-valuenow="<?php echo $ratio; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $condBadge; ?> fw-bold px-2.5 py-1.5"><?php echo htmlspecialchars($row['condition']); ?></span>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-secondary"><i class="bi bi-geo-alt-fill text-muted me-1"></i><?php echo htmlspecialchars($row['location'] ?? 'Barangay Hall'); ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn-action-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg dropdown-menu-custom">
                                            <li>
                                                <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-edit-item" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#editItemModal">
                                                    <i class="bi bi-pencil-square me-2 text-primary"></i> Modify Asset
                                                </button>
                                            </li>
                                            <?php if ($_SESSION['role'] === 'Administrator' || $_SESSION['role'] === 'Barangay Captain'): ?>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-delete-item" data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['item_name']); ?>" data-bs-toggle="modal" data-bs-target="#deleteItemModal">
                                                        <i class="bi bi-trash3-fill me-2 text-danger"></i> Delete Record
                                                    </button>
                                                </li>
                                            <?php endif; ?>
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
                            <a class="page-link border-0 rounded-circle" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($searchTerm); ?>&condition_filter=<?php echo urlencode($filterCondition); ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <li class="page-item <?php echo $page === $p ? 'active' : ''; ?>">
                                <a class="page-link border-0 rounded-circle <?php echo $page === $p ? 'bg-primary text-white' : 'text-dark'; ?>" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($searchTerm); ?>&condition_filter=<?php echo urlencode($filterCondition); ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link border-0 rounded-circle" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($searchTerm); ?>&condition_filter=<?php echo urlencode($filterCondition); ?>"><i class="bi bi-chevron-right"></i></a>
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

<!-- 1. CATALOG ITEM MODAL -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="addItemModalLabel"><i class="bi bi-box-seam me-2 text-primary"></i>Catalog Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="add_item">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Asset Code / Plate #</label>
                            <input type="text" name="asset_code" class="form-control" placeholder="e.g. BRGY-CH-0041" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Asset Name</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Folding Chairs" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Total Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Available Stock</label>
                            <input type="number" name="available_quantity" class="form-control" min="0" value="1" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Physical Condition</label>
                            <select name="condition" class="form-select" required>
                                <?php foreach ($conditions as $c): ?>
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Storage Location</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g. Health Center">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Additional Notes / Specs</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Identify manufacturers, donor names, or special handling rules..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Catalog Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. MODIFY ITEM DETAILS MODAL -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="editItemModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Modify Property Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Asset Code / Plate #</label>
                            <input type="text" name="asset_code" id="edit_asset_code" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Asset Name</label>
                            <input type="text" name="item_name" id="edit_item_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Total Quantity</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Available Stock</label>
                            <input type="number" name="available_quantity" id="edit_available_quantity" class="form-control" min="0" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Physical Condition</label>
                            <select name="condition" id="edit_condition" class="form-select" required>
                                <?php foreach ($conditions as $c): ?>
                                    <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Storage Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Additional Notes / Specs</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. PERMANENT DELETE WARNING MODAL -->
<div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="deleteItemModalLabel"><i class="bi bi-trash3-fill me-2 text-danger"></i>Delete Logistical Property</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process.php" method="POST">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="delete_id" id="delete_id">
                <div class="modal-body p-4 text-center">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-1 mb-3 d-block animate-bounce"></i>
                    <h5 class="fw-bold mb-2">Are you absolutely sure?</h5>
                    <p class="text-muted small">You are about to permanently delete the asset profile for <strong id="delete_item_name_label" class="text-dark"></strong>. This action cannot be undone and will be logged in the system security audit files.</p>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">Delete Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Auto-dismiss alert notification banners cleanly after 2 seconds
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

// Edit Asset modal details population handler
document.querySelectorAll('.btn-edit-item').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        fetch(`process.php?fetch_item=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    alert('System Notice: ' + data.error);
                } else {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_asset_code').value = data.asset_code;
                    document.getElementById('edit_item_name').value = data.item_name;
                    document.getElementById('edit_quantity').value = data.quantity;
                    document.getElementById('edit_available_quantity').value = data.available_quantity;
                    document.getElementById('edit_condition').value = data.condition;
                    document.getElementById('edit_location').value = data.location;
                    document.getElementById('edit_notes').value = data.notes;
                }
            })
            .catch(err => console.error('Error fetching logistical item data:', err));
    });
});

// Delete Warning populator assignment
document.querySelectorAll('.btn-delete-item').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const itemName = this.getAttribute('data-name');
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_item_name_label').textContent = itemName;
    });
});
</script>

</body>
</html>