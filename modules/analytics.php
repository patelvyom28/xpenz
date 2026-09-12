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

// Monthly Income vs Expense Trend (Current Year)
$trend_stmt = $pdo->prepare("
    SELECT 
        MONTH(created_at) as month_num,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = :user_id AND YEAR(created_at) = YEAR(CURRENT_DATE())
    GROUP BY MONTH(created_at)
    ORDER BY month_num ASC
");
$trend_stmt->execute([':user_id' => $user_id]);
$raw_trends = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize 12 Months
$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$income_data = array_fill(0, 12, 0);
$expense_data = array_fill(0, 12, 0);

foreach ($raw_trends as $row) {
    $idx = (int)$row['month_num'] - 1;
    $income_data[$idx] = (float)$row['total_income'];
    $expense_data[$idx] = (float)$row['total_expense'];
}

// Cash Flow Projection (Next 3 Months Based on Average)
$avg_income = count(array_filter($income_data)) > 0 ? array_sum($income_data) / count(array_filter($income_data)) : 0;
$avg_expense = count(array_filter($expense_data)) > 0 ? array_sum($expense_data) / count(array_filter($expense_data)) : 0;

$projection_labels = array_slice($months, (int)date('n') - 1, 3);
if (count($projection_labels) < 3) {
    $projection_labels = array_merge($projection_labels, array_slice($months, 0, 3 - count($projection_labels)));
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Analytics - XPenz</title>
    
    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- External Theme Lock Engine & Chart.js -->
    <script src="../assets/js/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-dark text-white">

<div class="dashboard-layout">
    <!-- Desktop YouTube-Style Sidebar Navigation -->
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
            <a href="analytics.php" class="nav-link active rounded-3"><i class="fa-solid fa-chart-line me-3"></i>Analytics</a>
            <a href="pnl_statement.php" class="nav-link rounded-3"><i class="fa-solid fa-file-invoice-dollar me-3"></i>P&L Statement</a>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fs-4 fw-bold mb-0"><i class="fa-solid fa-chart-line me-2 text-primary"></i>Visual Analytics & Cash Flow Projections</h2>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="card card-custom p-3 h-100">
                        <h5 class="mb-3 fw-bold fs-6"><i class="fa-solid fa-chart-area me-2 text-info"></i>Annual Comparative Trends (<?= date('Y') ?>)</h5>
                        <div style="height: 300px;">
                            <canvas id="annualTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card card-custom p-3 h-100">
                        <h5 class="mb-3 fw-bold fs-6"><i class="fa-solid fa-wand-magic-sparkles me-2 text-warning"></i>Smart Run-Rate Projections</h5>
                        <div class="p-2">
                            <small class="text-subtle d-block">Monthly Avg Income</small>
                            <h4 class="text-success fw-bold">₹<?= number_format($avg_income, 2) ?></h4>
                            <hr class="border-secondary my-3">
                            <small class="text-subtle d-block">Monthly Avg Expense</small>
                            <h4 class="text-danger fw-bold">₹<?= number_format($avg_expense, 2) ?></h4>
                            <hr class="border-secondary my-3">
                            <small class="text-subtle d-block">Projected Monthly Savings</small>
                            <h4 class="text-primary fw-bold">₹<?= number_format($avg_income - $avg_expense, 2) ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-custom p-3 mb-4">
                <h5 class="mb-3 fw-bold fs-6"><i class="fa-solid fa-chart-bar me-2 text-success"></i>Next 3-Month Cash Flow Projection</h5>
                <div style="height: 250px;">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Mobile Fixed Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
    <a href="../home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="analytics.php" class="active"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
</div>

<script>
    const months = <?= json_encode($months) ?>;
    const incomeData = <?= json_encode($income_data) ?>;
    const expenseData = <?= json_encode($expense_data) ?>;
    const textColor = '#e6edf3';
    const gridColor = '#30363d';

    // Annual Trend Chart
    new Chart(document.getElementById('annualTrendChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                { label: 'Income', data: incomeData, borderColor: '#2ea043', backgroundColor: 'rgba(46, 160, 67, 0.15)', fill: true, tension: 0.3 },
                { label: 'Expense', data: expenseData, borderColor: '#f85149', backgroundColor: 'rgba(248, 81, 73, 0.15)', fill: true, tension: 0.3 }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            },
            plugins: {
                legend: { labels: { color: textColor } }
            }
        }
    });

    // Cash Flow Chart
    new Chart(document.getElementById('cashFlowChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($projection_labels) ?>,
            datasets: [
                { label: 'Est. Income', data: [<?= $avg_income ?>, <?= $avg_income ?>, <?= $avg_income ?>], backgroundColor: '#2ea043', borderRadius: 4 },
                { label: 'Est. Expense', data: [<?= $avg_expense ?>, <?= $avg_expense ?>, <?= $avg_expense ?>], backgroundColor: '#f85149', borderRadius: 4 }
            ]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            scales: {
                x: { ticks: { color: textColor }, grid: { display: false } },
                y: { ticks: { color: textColor }, grid: { color: gridColor } }
            },
            plugins: {
                legend: { labels: { color: textColor } }
            }
        }
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>