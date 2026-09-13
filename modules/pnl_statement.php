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
    header("Location: pnl_statement.php");
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
    header("Location: pnl_statement.php");
    exit();
}

// Fetch User Profile Initials & Details for Header & Modals
$user_stmt = $pdo->prepare("SELECT name, email, monthly_budget FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
$user_email = !empty($user['email']) ? $user['email'] : 'user@xpenz.com';

$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Filter Year (Default to current year)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch Income Breakdown by Category
$inc_stmt = $pdo->prepare("
    SELECT category, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = :user_id AND type = 'income' AND YEAR(created_at) = :year 
    GROUP BY category
");
$inc_stmt->execute([':user_id' => $user_id, ':year' => $selected_year]);
$income_breakdown = $inc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Expense Breakdown by Category
$exp_stmt = $pdo->prepare("
    SELECT category, SUM(amount) as total 
    FROM transactions 
    WHERE user_id = :user_id AND type = 'expense' AND YEAR(created_at) = :year 
    GROUP BY category
");
$exp_stmt->execute([':user_id' => $user_id, ':year' => $selected_year]);
$expense_breakdown = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Total Calculations
$total_revenue = array_sum(array_column($income_breakdown, 'total'));
$total_operating_expense = array_sum(array_column($expense_breakdown, 'total'));
$net_profit = $total_revenue - $total_operating_expense;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit & Loss Statement - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- External Theme Switcher Engine -->
    <script src="../assets/js/theme.js"></script>

    <style>
        /* Inner boxes hover lift effect */
        .inner-pnl-box {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .inner-pnl-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }

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
    <!-- Desktop Sidebar Navigation -->
    <aside class="sidebar-desktop d-none d-md-flex flex-column py-3 px-3">
        <div class="mb-4 px-2 d-flex align-items-center gap-2">
            <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
        </div>
        
        <nav class="nav flex-column gap-1">
            <a href="../home.php" class="nav-link rounded-3"><i class="fa-solid fa-house me-3"></i>Dashboard</a>
            <a href="transactions.php" class="nav-link rounded-3"><i class="fa-solid fa-list-check me-3"></i>Transactions</a>
            <a href="subscriptions.php" class="nav-link rounded-3"><i class="fa-solid fa-calendar-check me-3"></i>Subscriptions</a>
            <a href="goals.php" class="nav-link rounded-3"><i class="fa-solid fa-bullseye me-3"></i>Goals</a>
            <a href="loans.php" class="nav-link rounded-3"><i class="fa-solid fa-building-columns me-3"></i>Loans</a>
            <a href="analytics.php" class="nav-link rounded-3"><i class="fa-solid fa-chart-line me-3"></i>Analytics</a>
            <a href="pnl_statement.php" class="nav-link active rounded-3"><i class="fa-solid fa-file-invoice-dollar me-3"></i>P&L Statement</a>
        </nav>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-content-wrapper">
        <!-- Top Fixed Header -->
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

        <!-- Scrollable Inner Body -->
        <main class="dashboard-body-content">
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-<?= $_SESSION['msg_type'] ?? 'success'; ?> alert-dismissible fade show mb-4">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <!-- Outer Main Card Box -->
            <div class="card card-custom p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h4 class="m-0 fw-bold text-white"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>P&L Financial Statement</h4>
                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print me-1"></i> Print Statement</button>
                    </div>
                </div>

                <!-- Printable Header -->
                <div class="text-center mb-4">
                    <h2>XPenz — Profit & Loss Statement</h2>
                    <p class="text-subtle m-0">Financial Year: <strong><?= $selected_year ?></strong></p>
                    <small class="text-subtle">Generated on: <?= date('d M Y, h:i A') ?></small>
                    <hr class="border-secondary">
                </div>

                <!-- Summary Boxes (Inner Cards with Hover Effect) -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card card-custom inner-pnl-box p-3 text-center card-income-border border-secondary">
                            <small class="text-subtle text-uppercase">Total Revenue</small>
                            <h3 class="text-success fw-bold m-0 mt-1">₹<?= number_format($total_revenue, 2) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-custom inner-pnl-box p-3 text-center card-expense-border border-secondary">
                            <small class="text-subtle text-uppercase">Total Operating Expenses</small>
                            <h3 class="text-danger fw-bold m-0 mt-1">₹<?= number_format($total_operating_expense, 2) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-custom inner-pnl-box p-3 text-center border-secondary border-start border-4 <?= $net_profit >= 0 ? 'border-primary' : 'border-warning' ?>">
                            <small class="text-subtle text-uppercase">Net Profit / Savings</small>
                            <h3 class="<?= $net_profit >= 0 ? 'text-primary' : 'text-warning' ?> fw-bold m-0 mt-1">₹<?= number_format($net_profit, 2) ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Statement Table (Inner Card Box with Hover Effect) -->
                <div class="card card-custom inner-pnl-box p-4 border-secondary">
                    <h5 class="mb-3 fw-bold"><i class="fa-solid fa-list-ol text-info me-2"></i>Income & Expense Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-dark-custom align-middle m-0">
                            <thead>
                                <tr class="table-active">
                                    <th>Category / Account Head</th>
                                    <th class="text-end">Type</th>
                                    <th class="text-end">Amount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- REVENUE SECTION -->
                                <tr>
                                    <td colspan="3" class="fw-bold text-success opacity-100">1. REVENUE / INCOME</td>
                                </tr>
                                <?php if (count($income_breakdown) > 0): ?>
                                    <?php foreach ($income_breakdown as $inc): ?>
                                        <tr>
                                            <td class="ps-4"><?= htmlspecialchars($inc['category']) ?></td>
                                            <td class="text-end"><span class="badge bg-success">Income</span></td>
                                            <td class="text-end text-success">+ ₹<?= number_format($inc['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="ps-4 text-muted">No income streams recorded for <?= $selected_year ?>.</td></tr>
                                <?php endif; ?>
                                <tr class="fw-bold">
                                    <td class="ps-4">Total Gross Income</td>
                                    <td></td>
                                    <td class="text-end text-success">₹<?= number_format($total_revenue, 2) ?></td>
                                </tr>

                                <!-- EXPENSE SECTION -->
                                <tr>
                                    <td colspan="3" class="fw-bold text-danger opacity-100">2. OPERATING EXPENSES</td>
                                </tr>
                                <?php if (count($expense_breakdown) > 0): ?>
                                    <?php foreach ($expense_breakdown as $exp): ?>
                                        <tr>
                                            <td class="ps-4"><?= htmlspecialchars($exp['category']) ?></td>
                                            <td class="text-end"><span class="badge bg-danger">Expense</span></td>
                                            <td class="text-end text-danger">- ₹<?= number_format($exp['total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="ps-4 text-muted">No operating expenses recorded for <?= $selected_year ?>.</td></tr>
                                <?php endif; ?>
                                <tr class="fw-bold">
                                    <td class="ps-4">Total Operating Expenses</td>
                                    <td></td>
                                    <td class="text-end text-danger">₹<?= number_format($total_operating_expense, 2) ?></td>
                                </tr>

                                <!-- NET PROFIT SUMMARY -->
                                <tr class="table-active fw-bold fs-5">
                                    <td>NET PROFIT / (LOSS)</td>
                                    <td></td>
                                    <td class="text-end <?= $net_profit >= 0 ? 'text-primary' : 'text-warning' ?>">
                                        ₹<?= number_format($net_profit, 2) ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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

<!-- Mobile Fixed Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
    <a href="../home.php" class="active"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="loans.php"><i class="fa-solid fa-building-columns"></i><span>Loans</span></a>
    <a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
    <a href="pnl_statement.php"><i class="fa-solid fa-file-invoice-dollar"></i><span>P&L</span></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>