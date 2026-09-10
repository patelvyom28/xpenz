<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Monthly Budget Limit Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_budget'])) {
    $new_budget = floatval($_POST['monthly_budget']);
    $u_stmt = $pdo->prepare("UPDATE users SET monthly_budget = :budget WHERE id = :user_id");
    $u_stmt->execute([':budget' => $new_budget, ':user_id' => $user_id]);
    $_SESSION['msg'] = "Monthly budget limit updated successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: home.php");
    exit();
}

// Fetch User Profile
$user_stmt = $pdo->prepare("SELECT name, monthly_budget FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
$monthly_budget = floatval($user['monthly_budget'] ?? 0);

$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Aggregate Totals
$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'income'");
$stmt->execute([':user_id' => $user_id]);
$total_income = floatval($stmt->fetch()['total'] ?? 0);

$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense'");
$stmt->execute([':user_id' => $user_id]);
$total_expense = floatval($stmt->fetch()['total'] ?? 0);

$net_balance = $total_income - $total_expense;

// Cash Wallet vs Bank/Online Aggregations
$c_inc = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'income' AND payment_method = 'cash'");
$c_inc->execute([':user_id' => $user_id]);
$cash_income = floatval($c_inc->fetch()['total'] ?? 0);

$c_exp = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense' AND payment_method = 'cash'");
$c_exp->execute([':user_id' => $user_id]);
$cash_expense = floatval($c_exp->fetch()['total'] ?? 0);

$cash_balance = $cash_income - $cash_expense;
$online_balance = $net_balance - $cash_balance;

// Current Month Budget Usage
$m_stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$m_stmt->execute([':user_id' => $user_id]);
$current_month_expense = floatval($m_stmt->fetch()['total'] ?? 0);

$budget_percent = $monthly_budget > 0 ? min(100, round(($current_month_expense / $monthly_budget) * 100)) : 0;

// Fetch Today's Activity
$recent_stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :user_id AND DATE(created_at) = CURDATE() ORDER BY created_at DESC, id DESC");
$recent_stmt->execute([':user_id' => $user_id]);
$recent_transactions = $recent_stmt->fetchAll();

// Category Data for Visual Charts
$cat_stmt = $pdo->prepare("SELECT category, SUM(amount) as total FROM transactions WHERE user_id = :user_id AND type = 'expense' GROUP BY category");
$cat_stmt->execute([':user_id' => $user_id]);
$categories_data = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_values = [];
foreach ($categories_data as $row) {
    $chart_labels[] = $row['category'];
    $chart_values[] = (float)$row['total'];
}

// Time-Aware Smart Reminders
$current_hour = (int)date('H');
if ($current_hour >= 8 && $current_hour < 12) {
    $reminder_text = "Morning Reminder: Did you spend any cash or online money on tea, breakfast, or commuting?";
    $reminder_icon = "fa-sun";
} elseif ($current_hour >= 12 && $current_hour < 17) {
    $reminder_text = "Afternoon Reminder: Don't forget to record lunch, petrol, or online UPI expenses!";
    $reminder_icon = "fa-utensils";
} else {
    $reminder_text = "Evening Reconciliation Check: Verify your Cash Wallet and UPI balances before ending your day.";
    $reminder_icon = "fa-moon";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - XPenz</title>
    
    <!-- PWA Manifest & Favicon / Mobile Icons -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">

    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container py-4">
    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 top-header">
        <div class="d-flex align-items-center gap-2">
            <img src="assets/images/logo.png" alt="XPenz Logo" style="height: 38px;">
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#budgetModal">
                <i class="fa-solid fa-sliders"></i> <span class="d-none d-md-inline">Budget Limit</span>
            </button>
            <span class="badge bg-dark border border-secondary p-2 d-none d-md-inline-block"><i class="fa-solid fa-users me-1"></i> Family Workspace</span>
            <span class="badge bg-primary rounded-circle avatar-badge"><?= htmlspecialchars($initials) ?></span>
            <a href="auth/logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type']; ?> alert-dismissible fade show mb-4">
            <?= $_SESSION['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- Navigation Bar (Scrollable on Mobile) -->
    <div class="d-flex gap-2 mb-4 flex-wrap nav-scroller">
        <a href="home.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-house me-1"></i> Dashboard</a>
        <a href="modules/transactions.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-list-check me-1"></i> Transactions</a>
        <a href="modules/subscriptions.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-calendar-check me-1"></i> Subscriptions</a>
        <a href="modules/goals.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-bullseye me-1"></i> Goals</a>
        <a href="modules/loans.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-building-columns me-1"></i> Loans</a>
        <a href="modules/analytics.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-chart-line me-1"></i> Analytics</a>
        <a href="modules/pnl_statement.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-file-invoice-dollar me-1"></i> P&L</a>
    </div>

    <!-- Time-Aware Smart Expense Banner -->
    <div class="alert alert-info d-flex align-items-center justify-content-between rounded-4 mb-4" role="alert">
        <div class="d-flex align-items-center gap-3">
            <i class="fa-solid <?= $reminder_icon ?> fs-3 text-info"></i>
            <div>
                <strong>Smart Expense Alert:</strong> <?= $reminder_text ?>
            </div>
        </div>
        <button id="enableNotifyBtn" onclick="requestNotification()" class="btn btn-sm btn-outline-info text-nowrap mt-2 mt-md-0"><i class="fa-solid fa-bell me-1"></i> Trigger Push Alert</button>
    </div>

    <!-- Monthly Budget Warning Banners -->
    <?php if ($monthly_budget > 0): ?>
        <?php if ($current_month_expense > $monthly_budget): ?>
            <div class="alert alert-danger d-flex align-items-center gap-3 rounded-4 mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-3"></i>
                <div>
                    <strong>Monthly Budget Exceeded!</strong> Spent <strong>₹<?= number_format($current_month_expense, 2) ?></strong> / <strong>₹<?= number_format($monthly_budget, 2) ?></strong>.
                </div>
            </div>
        <?php elseif ($current_month_expense >= ($monthly_budget * 0.8)): ?>
            <div class="alert alert-warning d-flex align-items-center gap-3 rounded-4 mb-4 text-dark" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-3"></i>
                <div>
                    <strong>Budget Warning!</strong> Used <strong><?= $budget_percent ?>%</strong> of budget limit (₹<?= number_format($current_month_expense, 2) ?> / ₹<?= number_format($monthly_budget, 2) ?>).
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <h2 class="fs-3">Welcome back, <?= htmlspecialchars($user_name) ?>! 👋</h2>
            <p class="text-subtle m-0 small">Real-time wallet balance & transaction dashboard.</p>
        </div>
        <?php if ($monthly_budget > 0): ?>
            <div class="text-end">
                <small class="text-subtle d-block">Monthly Budget Used</small>
                <strong class="text-white fs-6">₹<?= number_format($current_month_expense, 2) ?> / ₹<?= number_format($monthly_budget, 2) ?></strong>
                <div class="progress mt-1" style="height: 6px;">
                    <div class="progress-bar <?= $current_month_expense > $monthly_budget ? 'bg-danger' : ($current_month_expense >= ($monthly_budget * 0.8) ? 'bg-warning' : 'bg-success') ?>" role="progressbar" style="width: <?= $budget_percent ?>%"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Overview Summary Cards (2x2 Grid on Mobile) -->
    <div class="summary-grid mb-4">
        <div class="card bg-success text-white border-0 p-3">
            <small class="text-uppercase fw-bold opacity-75" style="font-size: 0.7rem;">Total Income</small>
            <h4 class="mt-1 mb-0 fw-bold">₹<?= number_format($total_income, 2); ?></h4>
        </div>
        <div class="card bg-danger text-white border-0 p-3">
            <small class="text-uppercase fw-bold opacity-75" style="font-size: 0.7rem;">Total Expense</small>
            <h4 class="mt-1 mb-0 fw-bold">₹<?= number_format($total_expense, 2); ?></h4>
        </div>
        <div class="card card-custom border-start border-3 border-warning p-3">
            <small class="text-subtle text-uppercase fw-bold" style="font-size: 0.7rem;">💵 Cash Wallet</small>
            <h4 class="text-warning mt-1 mb-0 fw-bold">₹<?= number_format($cash_balance, 2); ?></h4>
        </div>
        <div class="card card-custom border-start border-3 border-info p-3">
            <small class="text-subtle text-uppercase fw-bold" style="font-size: 0.7rem;">📱 Bank / Online</small>
            <h4 class="text-info mt-1 mb-0 fw-bold">₹<?= number_format($online_balance, 2); ?></h4>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="row g-2 mb-4">
        <div class="col-6">
            <a href="modules/add_transaction.php?type=income" class="text-decoration-none">
                <div class="card card-custom p-3 text-center">
                    <div class="mb-1"><i class="fa-solid fa-circle-plus text-success fs-3"></i></div>
                    <h6 class="text-white m-0 fw-bold">Add Income</h6>
                </div>
            </a>
        </div>
        <div class="col-6">
            <a href="modules/add_transaction.php?type=expense" class="text-decoration-none">
                <div class="card card-custom p-3 text-center">
                    <div class="mb-1"><i class="fa-solid fa-circle-minus text-danger fs-3"></i></div>
                    <h6 class="text-white m-0 fw-bold">Add Expense</h6>
                </div>
            </a>
        </div>
    </div>

    <!-- Dynamic Chart Section -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card card-custom p-3 h-100">
                <h6 class="text-white mb-3"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Expense Breakdown</h6>
                <div class="chart-container d-flex justify-content-center">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-custom p-3 h-100">
                <h6 class="text-white mb-3"><i class="fa-solid fa-chart-column me-2 text-success"></i>Income vs Expense</h6>
                <div class="chart-container">
                    <canvas id="compareChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Table -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="m-0 text-white fs-6"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Today's Activity</h5>
        <a href="modules/transactions.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-list-check me-1"></i> View All
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
                        <th>Method</th>
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
                                    <span class="badge <?= ($t['payment_method'] ?? 'online') === 'cash' ? 'bg-warning text-dark' : 'bg-info text-dark' ?>">
                                        <?= strtoupper($t['payment_method'] ?? 'online') ?>
                                    </span>
                                </td>
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
                            <td colspan="6" class="text-center py-3 text-muted">No transactions recorded today.</td>
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
                <h5 class="modal-title fs-6"><i class="fa-solid fa-sliders text-primary me-2"></i>Set Monthly Budget Limit</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_budget" value="1">
                    <label class="form-label text-subtle small">Monthly Spending Limit (₹)</label>
                    <input type="number" step="0.01" name="monthly_budget" class="form-control mb-2" placeholder="e.g. 15000" value="<?= $monthly_budget ?>" required>
                    <small class="text-subtle opacity-75">You will receive warning alerts if your monthly expenses reach 80% or exceed this amount.</small>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Budget</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Fixed Bottom Navigation Bar -->
<div class="mobile-bottom-nav">
    <a href="home.php" class="active"><i class="fa-solid fa-house"></i>Home</a>
    <a href="modules/add_transaction.php?type=expense"><i class="fa-solid fa-circle-minus text-danger"></i>Expense</a>
    <a href="modules/add_transaction.php?type=income"><i class="fa-solid fa-circle-plus text-success"></i>Income</a>
    <a href="modules/transactions.php"><i class="fa-solid fa-receipt"></i>History</a>
    <a href="modules/analytics.php"><i class="fa-solid fa-chart-pie"></i>Analytics</a>
</div>

<script>
    window.chartLabels = <?= json_encode($chart_labels) ?>;
    window.chartValues = <?= json_encode($chart_values) ?>;
    window.totalIncome = <?= (float)$total_income ?>;
    window.totalExpense = <?= (float)$total_expense ?>;
    window.reminderText = "<?= addslashes($reminder_text) ?>";
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>