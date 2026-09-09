<?php
session_start();
require_once 'config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Logged-in User Details
$user_stmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';

// Initials Generator
$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Fetch Totals
$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'income'");
$stmt->execute([':user_id' => $user_id]);
$total_income = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense'");
$stmt->execute([':user_id' => $user_id]);
$total_expense = $stmt->fetch()['total'] ?? 0;

$net_balance = $total_income - $total_expense;

// Fetch Today's Transactions
$recent_stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :user_id AND DATE(created_at) = CURDATE() ORDER BY created_at DESC, id DESC");
$recent_stmt->execute([':user_id' => $user_id]);
$recent_transactions = $recent_stmt->fetchAll();

// Expense Breakdown by Category
$cat_stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id = :user_id AND type = 'expense' GROUP BY category");
$cat_stmt->execute([':user_id' => $user_id]);
$categories_data = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];

foreach ($categories_data as $row) {
    $chart_labels[] = $row['category'];
    $chart_values[] = (float)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container py-4">
    <!-- Navbar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <img src="assets/images/logo.png" alt="XPenz Logo" style="height: 40px;">
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-dark border border-secondary p-2"><i class="fa-solid fa-users me-1"></i> Family Workspace</span>
            <span class="badge bg-primary rounded-circle p-2 fs-6" style="width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center;"><?= htmlspecialchars($initials) ?></span>
            <a href="auth/logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
        </div>
    </div>

    <!-- Quick Module Shortcuts -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="home.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-house me-1"></i> Dashboard</a>
        <a href="modules/transactions.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-list-check me-1"></i> Transactions</a>
        <a href="modules/subscriptions.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-calendar-check me-1"></i> Subscriptions & Bills</a>
        <a href="modules/goals.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-bullseye me-1"></i> Savings Goals</a>
    </div>

    <!-- Welcome Text -->
    <div class="mb-4">
        <h2>Welcome back, <?= htmlspecialchars($user_name) ?>! 👋</h2>
        <p class="text-subtle m-0">Here is your real-time financial summary.</p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white p-3 h-100 border-0 rounded-4">
                <small class="text-uppercase fw-bold opacity-75">Total Income</small>
                <h3 class="mt-2 mb-0 fw-bold">₹ <?= number_format($total_income, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white p-3 h-100 border-0 rounded-4">
                <small class="text-uppercase fw-bold opacity-75">Total Expenses</small>
                <h3 class="mt-2 mb-0 fw-bold">₹ <?= number_format($total_expense, 2); ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white p-3 h-100 border-0 rounded-4 position-relative">
                <small class="text-uppercase fw-bold opacity-75">Net Available Balance</small>
                <h3 class="mt-2 mb-0 fw-bold">₹ <?= number_format($net_balance, 2); ?></h3>
                <i class="fa-solid fa-wallet position-absolute end-0 bottom-0 m-3 fs-2 opacity-50"></i>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <a href="modules/add_transaction.php?type=income" class="text-decoration-none">
                <div class="card card-custom p-4 text-center">
                    <div class="mb-2"><i class="fa-solid fa-circle-plus text-success fa-2x"></i></div>
                    <h5 class="text-white m-0 fw-bold">Add Income</h5>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="modules/add_transaction.php?type=expense" class="text-decoration-none">
                <div class="card card-custom p-4 text-center">
                    <div class="mb-2"><i class="fa-solid fa-circle-minus text-danger fa-2x"></i></div>
                    <h5 class="text-white m-0 fw-bold">Add Expense</h5>
                </div>
            </a>
        </div>
    </div>

    <!-- Visual Analytics Section -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-3 h-100">
                <h5 class="text-white mb-3"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Expense Breakdown</h5>
                <div style="height: 220px; position: relative;" class="d-flex justify-content-center">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-custom p-3 h-100">
                <h5 class="text-white mb-3"><i class="fa-solid fa-chart-column me-2 text-success"></i>Income vs Expense</h5>
                <div style="height: 220px; position: relative;">
                    <canvas id="compareChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Activity Header with Button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0 text-white"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Today's Activity</h4>
        <a href="modules/transactions.php" class="btn btn-primary">
            <i class="fa-solid fa-list-check me-1"></i> View All Transactions
        </a>
    </div>

    <!-- Today's Activity Table -->
    <div class="card card-custom p-3">
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_transactions) > 0): ?>
                        <?php foreach ($recent_transactions as $t): ?>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-3 text-muted">No transactions recorded today.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    window.chartLabels = <?= json_encode($chart_labels) ?>;
    window.chartValues = <?= json_encode($chart_values) ?>;
    window.totalIncome = <?= (float)$total_income ?>;
    window.totalExpense = <?= (float)$total_expense ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>