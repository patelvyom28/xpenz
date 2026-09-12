<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch User Profile Initials for Header
$user_stmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
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
                <span class="badge bg-primary rounded-circle avatar-badge"><?= htmlspecialchars($initials) ?></span>
                <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </header>

        <!-- Scrollable Inner Body -->
        <main class="dashboard-body-content">
            <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                <h2><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>P&L Financial Statement</h2>
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

            <!-- Summary Box -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card card-custom p-3 text-center card-income-border">
                        <small class="text-subtle text-uppercase">Total Revenue</small>
                        <h3 class="text-success fw-bold m-0 mt-1">₹<?= number_format($total_revenue, 2) ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-custom p-3 text-center card-expense-border">
                        <small class="text-subtle text-uppercase">Total Operating Expenses</small>
                        <h3 class="text-danger fw-bold m-0 mt-1">₹<?= number_format($total_operating_expense, 2) ?></h3>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-custom p-3 text-center border-start border-4 <?= $net_profit >= 0 ? 'border-primary' : 'border-warning' ?>">
                        <small class="text-subtle text-uppercase">Net Profit / Savings</small>
                        <h3 class="<?= $net_profit >= 0 ? 'text-primary' : 'text-warning' ?> fw-bold m-0 mt-1">₹<?= number_format($net_profit, 2) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Statement Table -->
            <div class="card card-custom p-4">
                <h5 class="mb-3 fw-bold"><i class="fa-solid fa-list-ol text-info me-2"></i>Income & Expense Breakdown</h5>
                <div class="table-responsive">
                    <table class="table table-dark-custom align-middle">
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
        </main>
    </div>
</div>

<!-- Mobile Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
    <a href="../home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>