<?php
// Secure route guarding (Ensures only Administrators or Captains can configure users)
require_once '../../includes/auth.php';
authorizeRoles(['Administrator']);

require_once '../../classes/Database.php';
require_once '../../classes/UserManager.php';

$database = new Database();
$conn = $database->connect();
$userManager = new UserManager($conn);

$actorId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Handle AJAX User Fetching for Edit Modal
if (isset($_GET['fetch_user'])) {
    header('Content-Type: application/json');
    $userData = $userManager->getUserById((int)$_GET['fetch_user']);
    echo json_encode($userData ? $userData : ['error' => 'User not found']);
    exit;
}

// --------------------------------------------------------
// FORM POST REQUEST PROCESSORS
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW SYSTEM USER
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $fullname = trim($_POST['fullname']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $status = $_POST['status'];

        if ($userManager->isUsernameTaken($username)) {
            $errorMsg = "Registration failed! The username '{$username}' is already in use.";
        } else {
            $userData = [
                'fullname' => $fullname,
                'username' => $username,
                'password' => $password,
                'role' => $role,
                'status' => $status
            ];
            if ($userManager->createUser($userData, $actorId)) {
                $successMsg = "System staff account '{$fullname}' was successfully created!";
            } else {
                $errorMsg = "Unable to process registration. Please double-check input types.";
            }
        }
    }

    // 2. EDIT EXISTING USER
    if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
        $userId = (int)$_POST['edit_user_id'];
        $fullname = trim($_POST['edit_fullname']);
        $role = $_POST['edit_role'];
        $status = $_POST['edit_status'];
        $password = $_POST['edit_password']; // Optional

        $updateData = [
            'fullname' => $fullname,
            'role' => $role,
            'status' => $status,
            'password' => $password
        ];

        if ($userManager->updateUser($userId, $updateData, $actorId)) {
            $successMsg = "Account for  {$fullname} has been successfully modified.";
        } else {
            $errorMsg = "Unable to update account details. Please try again.";
        }
    }

    // 3. QUICK STATUS OR LOCKOUT CHANGES
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $userId = (int)$_POST['status_user_id'];
        $newStatus = $_POST['new_status'];
        if ($userManager->updateStatus($userId, $newStatus, $actorId)) {
            $successMsg = "Status updated successfully.";
        } else {
            $errorMsg = "Unable to update status.";
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'unlock_account') {
        $userId = (int)$_POST['unlock_user_id'];
        if ($userManager->unlockAccount($userId, $actorId)) {
            $successMsg = "Account unlocked. Failed attempt counters have been reset.";
        } else {
            $errorMsg = "Unable to unlock account.";
        }
    }
}

// --------------------------------------------------------
// SEARCH & FILTER RETRIEVAL
// --------------------------------------------------------
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterRole = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$filterStatus = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$users = $userManager->getUsers($searchTerm, $filterRole, $filterStatus);
$auditLogs = $userManager->getSecurityLogs(15); // Show last 15 security log entries
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Barangay System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../../assets/css/users.css" rel="stylesheet">
    
    <!-- Clean UX/UI Overrides to resolve "eek" margins and improve scrollability -->
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

        /* Spacious Containers */
        .dashboard-wrapper {
            padding-top: 2.5rem;
            padding-bottom: 5rem;
        }

        /* Beautiful Cards with Balanced Margins & Soft Shadows */
        .page-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            margin-bottom: 1.75rem; /* Balanced gap between rows */
        }

        .page-card:hover {
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.05);
        }

        /* Card Headers with elegant spacing */
        .card-header-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.5rem 1.75rem;
            border-top-left-radius: 16px !important;
            border-top-right-radius: 16px !important;
        }

        .form-control, .form-select {
            border-color: #e2e8f0;
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(43, 76, 126, 0.15);
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }

        /* Unified Scrollable Card Containers */
        .scrollable-card-body {
            max-height: 500px;
            overflow-y: auto;
        }

        /* Professional Sticky Headers inside the Scrollable Table Wrapper */
        .table-scroll-container {
            max-height: 500px;
            overflow-y: auto;
        }

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
            padding: 1.1rem 1.25rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: inset 0 -1px 0 #e2e8f0;
        }

        .table-custom td {
            vertical-align: middle;
            padding: 1.1rem 1.25rem;
            font-size: 0.95rem;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Badges */
        .badge-active { background: rgba(25, 135, 84, 0.1); color: #198754; }
        .badge-inactive { background: rgba(108, 117, 125, 0.1); color: #6c757d; }
        .badge-suspended { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        /* Security Log List Spacing */
        .log-item {
            padding: 1.25rem 1.75rem !important;
            border-bottom: 1px solid #f1f5f9 !important;
            transition: background-color 0.15s ease;
        }
        
        .log-item:hover {
            background-color: #f8fafc;
        }

        /* Breadcrumb/Back button spacing */
        .back-link {
            text-decoration: none;
            color: #64748b;
            font-weight: 600;
            transition: color 0.15s ease;
        }
        
        .back-link:hover {
            color: var(--primary-color);
        }

        /* Sleek Modern Dropdown Menus */
        .dropdown-menu-custom {
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02) !important;
            border-radius: 12px;
            padding: 0.5rem;
        }

        .dropdown-item-custom {
            border-radius: 8px;
            padding: 0.55rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        .dropdown-item-custom:hover {
            background-color: #f1f5f9;
            color: var(--secondary-color);
        }

        /* Custom toggle action with clean circle background */
        .btn-action-trigger {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: #64748b;
            transition: all 0.2s ease;
        }

        .btn-action-trigger:hover, .btn-action-trigger:focus {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-action-trigger::after {
            display: none !important; /* Hide caret arrow */
        }
    </style>
</head>
<body>

<div class="container dashboard-wrapper">

    <!-- Header Block with Balanced Whitespace -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-5 gap-3">
        <div>
            <a href="../dashboard.php" class="back-link d-inline-flex align-items-center mb-3">
                <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
            </a>
            <h1 class="fw-bold text-dark mb-1 fs-2">System User Management</h1>
            <p class="text-muted mb-0">Create, configure, and monitor secure administrative credentials.</p>
        </div>
        <div class="align-self-start align-self-md-center">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-2"></i> Register New Staff
            </button>
        </div>
    </div>

    <!-- Dismissible Feedback Alerts with consistent shadows -->
    <?php if(!empty($successMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
            <i class="bi bi-check-circle-fill me-3 fs-4 text-success animate__animated animate__fadeIn"></i>
            <div class="fw-medium me-5"><?php echo htmlspecialchars($successMsg); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if(!empty($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center p-3 mb-4 rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4 text-danger animate__animated animate__fadeIn"></i>
            <div class="fw-medium me-5"><?php echo htmlspecialchars($errorMsg); ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Filters Form -->
    <div class="card page-card p-4 p-md-5 mb-5">
        <h5 class="fw-bold text-dark mb-4"><i class="bi bi-funnel-fill text-primary me-2"></i>Search and Filters</h5>
        <form method="GET" action="index.php" class="row g-4">
            <div class="col-12 col-md-5">
                <label class="form-label text-secondary small fw-bold">Search User</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name or username..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label text-secondary small fw-bold">Filter Role</label>
                <select name="role_filter" class="form-select">
                    <option value="">All Roles</option>
                    <option value="Administrator" <?php echo $filterRole === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                    <option value="Barangay Captain" <?php echo $filterRole === 'Barangay Captain' ? 'selected' : ''; ?>>Barangay Captain</option>
                    <option value="Secretary" <?php echo $filterRole === 'Secretary' ? 'selected' : ''; ?>>Secretary</option>
                    <option value="Treasurer" <?php echo $filterRole === 'Treasurer' ? 'selected' : ''; ?>>Treasurer</option>
                    <option value="Staff" <?php echo $filterRole === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label text-secondary small fw-bold">Filter Status</label>
                <select name="status_filter" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $filterStatus === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $filterStatus === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Suspended" <?php echo $filterStatus === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-grid align-items-end">
                <button type="submit" class="btn btn-dark py-2.5"><i class="bi bi-filter"></i> Apply</button>
            </div>
        </form>
    </div>

    <!-- Main Content Layout (Increased gutters to clear "eek" look) -->
    <div class="row g-4 g-lg-5">
        
        <!-- Table Column (Scrollable Card) -->
        <div class="col-12 col-xl-8">
            <div class="card page-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Staff Directory</h5>
                    <span class="badge bg-secondary rounded-pill"><?php echo count($users); ?> Users</span>
                </div>
                <!-- Fixed Height Scrollable Container with Sticky Table Headers -->
                <div class="table-scroll-container">
                    <table class="table table-hover table-custom">
                        <thead>
                            <tr>
                                <th>Name / Username</th>
                                <th>System Role</th>
                                <th>Status</th>
                                <th>Last Online</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-x fs-1 mb-2 d-block"></i>
                                        No active staff accounts match your current query.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($users as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                            <div class="text-muted small">@<?php echo htmlspecialchars($row['username']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light border text-dark fw-semibold">
                                                <i class="bi bi-shield-check text-primary me-1"></i><?php echo $row['role']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = 'badge-inactive';
                                                if ($row['status'] === 'Active') $statusClass = 'badge-active';
                                                if ($row['status'] === 'Suspended') $statusClass = 'badge-suspended';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?> fw-bold px-2 py-1.5"><?php echo $row['status']; ?></span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo $row['last_login'] ? date('M d, g:i A', strtotime($row['last_login'])) : '<span class="text-black-50">Never logged in</span>'; ?>
                                        </td>
                                        <td class="text-end">
                                            <!-- Modern Minimalist Actions Button with Professional Dropdown Menu -->
                                            <div class="dropdown d-inline-block">
                                                <button class="btn-action-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg dropdown-menu-custom">
                                                    <!-- Edit Action -->
                                                    <li>
                                                        <button class="dropdown-item dropdown-item-custom d-flex align-items-center btn-edit-user" data-id="<?php echo $row['id']; ?>" data-bs-toggle="modal" data-bs-target="#editUserModal">
                                                            <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Profile
                                                        </button>
                                                    </li>
                                                    
                                                    <!-- Status quick toggle settings -->
                                                    <li><hr class="dropdown-divider my-1"></li>
                                                    <li>
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="status_user_id" value="<?php echo $row['id']; ?>">
                                                            <?php if ($row['status'] !== 'Active'): ?>
                                                                <button type="submit" name="new_status" value="Active" class="dropdown-item dropdown-item-custom d-flex align-items-center text-success">
                                                                    <i class="bi bi-check-circle me-2"></i> Activate Account
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($row['status'] !== 'Inactive'): ?>
                                                                <button type="submit" name="new_status" value="Inactive" class="dropdown-item dropdown-item-custom d-flex align-items-center text-secondary">
                                                                    <i class="bi bi-slash-circle me-2"></i> Deactivate Account
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($row['status'] !== 'Suspended'): ?>
                                                                <button type="submit" name="new_status" value="Suspended" class="dropdown-item dropdown-item-custom d-flex align-items-center text-danger">
                                                                    <i class="bi bi-lock me-2"></i> Suspend Account
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </li>

                                                    <!-- Dynamic Account Unlock Trigger -->
                                                    <?php if ($row['failed_attempts'] > 0 || ($row['lock_until'] && strtotime($row['lock_until']) > time())): ?>
                                                        <li><hr class="dropdown-divider my-1"></li>
                                                        <li>
                                                            <form method="POST" action="">
                                                                <input type="hidden" name="action" value="unlock_account">
                                                                <input type="hidden" name="unlock_user_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="dropdown-item dropdown-item-custom d-flex align-items-center text-warning fw-semibold">
                                                                    <i class="bi bi-unlock-fill me-2"></i> Unlock Lockout
                                                                </button>
                                                            </form>
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
            </div>
        </div>

        <!-- Audit Logs Column (Scrollable Card) -->
        <div class="col-12 col-xl-4">
            <div class="card page-card h-100">
                <div class="card-header-custom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-activity text-danger me-2"></i>Security Logs</h5>
                </div>
                <!-- Custom Scrollable Body aligned with the Directory table height -->
                <div class="scrollable-card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($auditLogs)): ?>
                            <div class="text-center p-5 text-muted small">No security events found in audit records.</div>
                        <?php else: ?>
                            <?php foreach ($auditLogs as $log): ?>
                                <div class="list-group-item bg-transparent log-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-dark-subtle text-dark small font-monospace">@<?php echo htmlspecialchars($log['username'] ?? 'System'); ?></span>
                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('M d, g:i A', strtotime($log['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 small text-dark fw-medium" style="font-size: 0.85rem; line-height: 1.4;"><?php echo htmlspecialchars($log['action']); ?></p>
                                    <small class="text-black-50" style="font-size: 0.75rem;"><i class="bi bi-hdd-network me-1"></i>IP: <?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ========================================================
     MODALS SECTION (REGISTER AND MODIFY USER DIALOGUES)
     ======================================================== -->

<!-- 1. REGISTER STAFF MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="addUserModalLabel"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Register Staff Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Full Name</label>
                        <input type="text" name="fullname" class="form-control" placeholder="Firstname Lastname" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Staff Username (alphanumeric)" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Temporary Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="Staff">Staff</option>
                                <option value="Secretary">Secretary</option>
                                <option value="Treasurer">Treasurer</option>
                                <option value="Barangay Captain">Barangay Captain</option>
                                <option value="Administrator">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Initial Status</label>
                            <select name="status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 p-4 rounded-bottom-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Register User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. EDIT STAFF MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 p-4 pb-2">
                <h5 class="modal-title fw-bold text-dark" id="editUserModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Modify Account Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Account Owner (Full Name)</label>
                        <input type="text" name="edit_fullname" id="edit_fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">System Login Username</label>
                        <input type="text" id="edit_username" class="form-control bg-light" disabled>
                        <span class="text-muted small fs-7 d-block mt-1">Username values cannot be modified once generated.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Update Password</label>
                        <input type="password" name="edit_password" class="form-control" placeholder="Leave empty to keep existing password">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Assigned Role</label>
                            <select name="edit_role" id="edit_role" class="form-select" required>
                                <option value="Staff">Staff</option>
                                <option value="Secretary">Secretary</option>
                                <option value="Treasurer">Treasurer</option>
                                <option value="Barangay Captain">Barangay Captain</option>
                                <option value="Administrator">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Active Status</label>
                            <select name="edit_status" id="edit_status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
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

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script logic to populate Edit Modal with AJAX and auto-dismiss alerts -->
<script>
// Auto-dismiss alerts after 2 seconds
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 2000);
    });
});

document.querySelectorAll('.btn-edit-user').forEach(button => {
    button.addEventListener('click', function() {
        const userId = this.getAttribute('data-id');
        
        // Fetch specific user data
        fetch(`index.php?fetch_user=${userId}`)
            .then(response => response.json())
            .then(data => {
                if(data.error) {
                    alert('Error: ' + data.error);
                } else {
                    document.getElementById('edit_user_id').value = data.id;
                    document.getElementById('edit_fullname').value = data.fullname;
                    document.getElementById('edit_username').value = data.username;
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('edit_status').value = data.status;
                }
            })
            .catch(error => {
                console.error('AJAX Fetch Error:', error);
            });
    });
});
</script>

</body>
</html>