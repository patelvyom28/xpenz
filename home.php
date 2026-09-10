<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Monthly Budget Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_budget'])) {
    $new_budget = floatval($_POST['monthly_budget']);
    $u_stmt = $pdo->prepare("UPDATE users SET monthly_budget = :budget WHERE id = :user_id");
    $u_stmt->execute([':budget' => $new_budget, ':user_id' => $user_id]);
    $_SESSION['msg'] = "Monthly budget updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: home.php");
    exit();
}

// Fetch User Info
$user_stmt = $pdo->prepare("SELECT name, monthly_budget FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
$monthly_budget = floatval($user['monthly_budget'] ?? 0);

$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Financial Summary Calculations
$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'income'");
$stmt->execute([':user_id' => $user_id]);
$total_income = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense'");
$stmt->execute([':user_id' => $user_id]);
$total_expense = $stmt->fetch()['total'] ?? 0;

$net_balance = $total_income - $total_expense;

// Current Month Expense Calculation for Budget Tracking
$m_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$m_stmt->execute([':user_id' => $user_id]);
$current_month_expense = floatval($m_stmt->fetch()['total'] ?? 0);

// Budget Percentage & Alert Status
$budget_percent = $monthly_budget > 0 ? min(100, round(($current_month_expense / $monthly_budget) * 100)) : 0;

$recent_stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :user_id AND DATE(created_at) = CURDATE() ORDER BY created_at DESC, id DESC");
$recent_stmt->execute([':user_id' => $user_id]);
$recent_transactions = $recent_stmt->fetchAll();

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-2">
            <img src="assets/images/logo.png" alt="XPenz Logo" style="height: 40px;">
        </div>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#budgetModal">
                <i class="fa-solid fa-sliders me-1"></i> Budget Limit
            </button>
            <span class="badge bg-dark border border-secondary p-2"><i class="fa-solid fa-users me-1"></i> Family Workspace</span>
            <span class="badge bg-primary rounded-circle p-2 fs-6" style="width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center;"><?= htmlspecialchars($initials) ?></span>
            <a href="auth/logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
        </div>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type']; ?> alert-dismissible fade show mb-4">
            <?= $_SESSION['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- Navigation Shortcuts -->
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <a href="home.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-house me-1"></i> Dashboard</a>
        <a href="modules/transactions.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-list-check me-1"></i> Transactions</a>
        <a href="modules/subscriptions.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-calendar-check me-1"></i> Subscriptions & Bills</a>
        <a href="modules/goals.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-bullseye me-1"></i> Savings Goals</a>
        <a href="modules/loans.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-building-columns me-1"></i> Loans & Insurance</a>
        <a href="modules/analytics.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-chart-line me-1"></i> Analytics</a>
    </div>

    <!-- Smart Budget Alert Banner -->
    <?php if ($monthly_budget > 0): ?>
        <?php if ($current_month_expense > $monthly_budget): ?>
            <div class="alert alert-danger d-flex align-items-center gap-3 rounded-4 mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-3"></i>
                <div>
                    <strong>Monthly Budget Exceeded!</strong> You have spent <strong>₹<?= number_format($current_month_expense, 2) ?></strong> against your limit of <strong>₹<?= number_format($monthly_budget, 2) ?></strong>.
                </div>
            </div>
        <?php elseif ($current_month_expense >= ($monthly_budget * 0.8)): ?>
            <div class="alert alert-warning d-flex align-items-center gap-3 rounded-4 mb-4 text-dark" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-3"></i>
                <div>
                    <strong>Budget Warning!</strong> You have used <strong><?= $budget_percent ?>%</strong> of your monthly budget (₹<?= number_format($current_month_expense, 2) ?> / ₹<?= number_format($monthly_budget, 2) ?>).
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h2>Welcome back, <?= htmlspecialchars($user_name) ?>! 👋</h2>
            <p class="text-subtle m-0">Here is your real-time financial summary.</p>
        </div>
        <?php if ($monthly_budget > 0): ?>
            <div class="text-end">
                <small class="text-subtle d-block">Monthly Budget Used</small>
                <strong class="text-white fs-5">₹<?= number_format($current_month_expense, 2) ?> / ₹<?= number_format($monthly_budget, 2) ?></strong>
                <div class="progress mt-1" style="width: 200px; height: 6px;">
                    <div class="progress-bar <?= $current_month_expense > $monthly_budget ? 'bg-danger' : ($current_month_expense >= ($monthly_budget * 0.8) ? 'bg-warning' : 'bg-success') ?>" role="progressbar" style="width: <?= $budget_percent ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

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

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0 text-white"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Today's Activity</h4>
        <a href="modules/transactions.php" class="btn btn-primary">
            <i class="fa-solid fa-list-check me-1"></i> View All Transactions
        </a>
    </div>

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

<!-- Modal for Setting Budget Limit -->
<div class="modal fade" id="budgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title"><i class="fa-solid fa-sliders text-primary me-2"></i>Set Monthly Budget Limit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_budget" value="1">
                    <label class="form-label text-subtle">Monthly Spending Limit (₹)</label>
                    <input type="number" step="0.01" name="monthly_budget" class="form-control mb-2" placeholder="e.g. 15000" value="<?= $monthly_budget ?>" required>
                    <small class="text-subtle">You will receive warning alerts if your monthly expenses reach 80% or exceed this amount.</small>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Budget</button>
                </div>
            </form>
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