<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Profile & Budget Updates via Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $monthly_budget = floatval($_POST['monthly_budget']);

    if (empty($name) || empty($email)) {
        $_SESSION['msg'] = "Name and Email cannot be empty.";
        $_SESSION['msg_type'] = "danger";
    } else {
        $u_stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, monthly_budget = :budget WHERE id = :id");
        $u_stmt->execute([':name' => $name, ':email' => $email, ':budget' => $monthly_budget, ':id' => $user_id]);
        $_SESSION['msg'] = "Profile & Budget updated successfully!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: transactions.php");
    exit();
}

// Handle Password Change via Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $p_stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
    $p_stmt->execute([':id' => $user_id]);
    $db_user = $p_stmt->fetch();

    if (!password_verify($current_password, $db_user['password'])) {
        $_SESSION['msg'] = "Incorrect current password!";
        $_SESSION['msg_type'] = "danger";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['msg'] = "New passwords do not match!";
        $_SESSION['msg_type'] = "danger";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['msg'] = "New password must be at least 6 characters long.";
        $_SESSION['msg_type'] = "danger";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $up_stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
        $up_stmt->execute([':pass' => $hashed_password, ':id' => $user_id]);

        $_SESSION['msg'] = "Password changed successfully!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: transactions.php");
    exit();
}

// Fetch User Profile
$user_stmt = $pdo->prepare("SELECT name, email, monthly_budget FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
$user_email = !empty($user['email']) ? $user['email'] : 'user@xpenz.com';

$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Filter Variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Build Dynamic SQL Query
$query = "SELECT * FROM transactions WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if (!empty($search)) {
    $query .= " AND description LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $query .= " AND category = :category";
    $params[':category'] = $category;
}

if (!empty($type)) {
    $query .= " AND type = :type";
    $params[':type'] = $type;
}

if (!empty($from_date)) {
    $query .= " AND DATE(created_at) >= :from_date";
    $params[':from_date'] = $from_date;
}

if (!empty($to_date)) {
    $query .= " AND DATE(created_at) <= :to_date";
    $params[':to_date'] = $to_date;
}

$query .= " ORDER BY created_at DESC";

// CSV Export Handler with Filters
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=xpenz_filtered_report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Description', 'Category', 'Type', 'Amount (INR)']);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            date('d M Y, h:i A', strtotime($row['created_at'])),
            $row['description'],
            $row['category'],
            strtoupper($row['type']),
            $row['amount']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch Filtered Transactions
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Fetch Categories for Dropdown
$cat_stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :user_id");
$cat_stmt->execute([':user_id' => $user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <script src="../assets/js/theme.js"></script>
    
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; color: #000000 !important; height: auto !important; overflow: auto !important; }
            .card-custom { background: none !important; border: none !important; color: #000 !important; }
            .table { color: #000 !important; }
            .table-dark-custom th, .table-dark-custom td { color: #000 !important; border-bottom: 1px solid #ccc !important; }
            .dashboard-layout { display: block !important; height: auto !important; }
            .sidebar-desktop, .top-header, .mobile-bottom-nav { display: none !important; }
            .main-content-wrapper { margin-left: 0 !important; height: auto !important; overflow: visible !important; }
            .dashboard-body-content { overflow: visible !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-dark text-white">

<div class="dashboard-layout">
    <!-- Fixed Desktop Sidebar -->
    <aside class="sidebar-desktop d-none d-md-flex flex-column py-3 px-3">
        <div class="mb-4 px-2 d-flex align-items-center gap-2">
            <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
        </div>
        
        <nav class="nav flex-column gap-1">
            <a href="../home.php" class="nav-link rounded-3"><i class="fa-solid fa-house me-3"></i>Dashboard</a>
            <a href="transactions.php" class="nav-link active rounded-3"><i class="fa-solid fa-list-check me-3"></i>Transactions</a>
            <a href="subscriptions.php" class="nav-link rounded-3"><i class="fa-solid fa-calendar-check me-3"></i>Subscriptions</a>
            <a href="goals.php" class="nav-link rounded-3"><i class="fa-solid fa-bullseye me-3"></i>Goals</a>
            <a href="loans.php" class="nav-link rounded-3"><i class="fa-solid fa-building-columns me-3"></i>Loans</a>
            <a href="analytics.php" class="nav-link rounded-3"><i class="fa-solid fa-chart-line me-3"></i>Analytics</a>
            <a href="pnl_statement.php" class="nav-link rounded-3"><i class="fa-solid fa-file-invoice-dollar me-3"></i>P&L Statement</a>
        </nav>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper">
        <!-- Fixed Top Header -->
        <header class="top-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2 d-md-none">
                <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <!-- Google-Style Profile Dropdown Menu -->
                <div class="dropdown">
                    <span class="badge bg-primary rounded-circle avatar-badge dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <?= htmlspecialchars($initials) ?>
                    </span>
                    <ul class="dropdown-menu dropdown-menu-end card-custom p-2 shadow-lg border-secondary" aria-labelledby="profileDropdown" style="min-width: 260px; background-color: var(--card-bg) !important;">
                        <li class="px-3 py-2 border-bottom border-secondary mb-2 text-center">
                            <span class="badge bg-primary rounded-circle p-3 fs-4 avatar-badge mb-1" style="width: 50px; height: 50px; display: inline-flex; align-items: center; justify-content: center;"><?= htmlspecialchars($initials) ?></span>
                            <span class="fw-bold d-block text-white mt-1"><?= htmlspecialchars($user_name) ?></span>
                            <small class="text-subtle"><?= htmlspecialchars($user_email) ?></small>
                        </li>
                        <li><button type="button" class="dropdown-item rounded-2 text-white py-2 mb-1 bg-transparent border-0 w-100 text-start" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit Profile & Budget</button></li>
                        <li><button type="button" class="dropdown-item rounded-2 text-white py-2 mb-2 bg-transparent border-0 w-100 text-start" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="fa-solid fa-key me-2 text-warning"></i> Change Password</button></li>
                        <li><hr class="dropdown-divider border-secondary my-1"></li>
                        <li><a class="dropdown-item rounded-2 text-danger py-2 mt-1" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Scrollable Body Content -->
        <main class="dashboard-body-content">
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-<?= $_SESSION['msg_type'] ?? 'success'; ?> alert-dismissible fade show mb-4">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <div class="d-flex justify-content-center align-items-center mb-4 no-print position-relative">
                <h2 class="text-center m-0 w-100"><i class="fa-solid fa-receipt me-2 text-primary"></i>Transaction History</h2>
                <div class="d-flex gap-2 position-absolute end-0">
                    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#importCsvModal"><i class="fa-solid fa-file-import me-1"></i> Import CSV</button>
                    <a href="transactions.php?export=csv&<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel me-1"></i> Export CSV</a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print me-1"></i> Print / Save PDF</button>
                </div>
            </div>

            <!-- Filter Bar Card -->
            <div class="card card-custom p-3 mb-4 no-print">
                <form method="GET" action="transactions.php" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-subtle small">Search Keyword</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="e.g. Salary, Recharge" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-subtle small">Category</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-subtle small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Income</option>
                            <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expense</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-subtle small">From Date</label>
                        <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-subtle small">To Date</label>
                        <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply Filter"><i class="fa-solid fa-filter"></i></button>
                        <a href="transactions.php" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="fa-solid fa-rotate-right"></i></a>
                    </div>
                </form>
            </div>

            <!-- Printable Header -->
            <div class="d-none d-print-block mb-4 text-center">
                <h2>XPenz — Financial Report</h2>
                <p>Generated on: <?= date('d M Y, h:i A') ?></p>
                <hr>
            </div>

            <div class="card card-custom p-3">
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover align-middle m-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th class="text-center no-print">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($t['description']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($t['category']) ?></span></td>
                                        <td>
                                            <span class="badge <?= $t['type'] === 'income' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= ucfirst($t['type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="<?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                                <?= $t['type'] === 'income' ? '+' : '-' ?> ₹<?= number_format($t['amount'], 2) ?>
                                            </strong>
                                        </td>
                                        <td class="text-center no-print">
                                            <a href="edit_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fa-solid fa-pen"></i></a>
                                            <a href="delete_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete transaction?');"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No transactions matching your filter criteria.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ================= MODAL POPUPS ================= -->

<!-- 1. Edit Profile & Budget Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold" id="editProfileModalLabel"><i class="fa-solid fa-user-pen me-2 text-primary"></i>Edit Profile & Budget</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="text-center mb-3">
                        <span class="badge bg-primary rounded-circle p-3 fs-3 avatar-badge" style="width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center;"><?= htmlspecialchars($initials) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Monthly Expense Budget Limit (₹)</label>
                        <input type="number" step="0.01" name="monthly_budget" class="form-control" value="<?= htmlspecialchars($user['monthly_budget']) ?>">
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold" id="changePasswordModalLabel"><i class="fa-solid fa-key me-2 text-warning"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="change_password" value="1">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm"><i class="fa-solid fa-shield-halved me-1"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Import CSV Modal -->
<div class="modal fade" id="importCsvModal" tabindex="-1" aria-labelledby="importCsvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold" id="importCsvModalLabel"><i class="fa-solid fa-file-import me-2 text-success"></i>Bulk Transaction Import</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="import_csv.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-subtle small">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <strong>Note:</strong> CSV File column structure must be:<br>
                        <code>Description, Category, Type (income/expense), Amount</code>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa-solid fa-cloud-arrow-up me-1"></i> Start Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
    <a href="../home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php" class="active"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>