<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

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
    header("Location: home.php");
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
    header("Location: home.php");
    exit();
}

// Fetch User Profile
$user_stmt = $pdo->prepare("SELECT name, email, monthly_budget FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
$user_email = !empty($user['email']) ? $user['email'] : 'user@xpenz.com';
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
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - XPenz</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d1117">
    <link rel="icon" type="image/png" href="assets/images/logo.png">

    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <script src="assets/js/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-dark text-white">

<div class="dashboard-layout">
    <!-- Fixed Desktop Sidebar Navigation -->
    <aside class="sidebar-desktop d-none d-md-flex flex-column py-3 px-3">
        <div class="mb-4 px-2 d-flex align-items-center gap-2">
            <img src="assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
        </div>
        
        <nav class="nav flex-column gap-1">
            <a href="home.php" class="nav-link active rounded-3"><i class="fa-solid fa-house me-3"></i>Dashboard</a>
            <a href="modules/transactions.php" class="nav-link rounded-3"><i class="fa-solid fa-list-check me-3"></i>Transactions</a>
            <a href="modules/subscriptions.php" class="nav-link rounded-3"><i class="fa-solid fa-calendar-check me-3"></i>Subscriptions</a>
            <a href="modules/goals.php" class="nav-link rounded-3"><i class="fa-solid fa-bullseye me-3"></i>Goals</a>
            <a href="modules/loans.php" class="nav-link rounded-3"><i class="fa-solid fa-building-columns me-3"></i>Loans</a>
            <a href="modules/analytics.php" class="nav-link rounded-3"><i class="fa-solid fa-chart-line me-3"></i>Analytics</a>
            <a href="modules/pnl_statement.php" class="nav-link rounded-3"><i class="fa-solid fa-file-invoice-dollar me-3"></i>P&L Statement</a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content-wrapper">
        <!-- Top Sticky Header -->
        <header class="top-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2 d-md-none">
                <img src="assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
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
                        <li><a class="dropdown-item rounded-2 text-danger py-2 mt-1" href="auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Scrollable Body Content -->
        <main class="dashboard-body-content">
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-<?= $_SESSION['msg_type'] ?? 'success'; ?> alert-dismissible fade show mb-4">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <?php if ($monthly_budget > 0): ?>
                <?php if ($current_month_expense > $monthly_budget): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-3 rounded-3 mb-4" role="alert">
                        <i class="fa-solid fa-triangle-exclamation fs-4"></i>
                        <div>
                            <strong>Monthly Budget Exceeded!</strong> Spent <strong>₹<?= number_format($current_month_expense, 2) ?></strong> / <strong>₹<?= number_format($monthly_budget, 2) ?></strong>.
                        </div>
                    </div>
                <?php elseif ($current_month_expense >= ($monthly_budget * 0.8)): ?>
                    <div class="alert alert-warning d-flex align-items-center gap-3 rounded-3 mb-4 text-dark" role="alert">
                        <i class="fa-solid fa-circle-exclamation fs-4"></i>
                        <div>
                            <strong>Budget Warning!</strong> Used <strong><?= $budget_percent ?>%</strong> of budget limit (₹<?= number_format($current_month_expense, 2) ?> / ₹<?= number_format($monthly_budget, 2) ?>).
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="welcome-header mb-4 d-flex justify-content-between align-items-end">
                <div>
                    <h2 class="welcome-title fw-bold text-white mb-1">
                        Welcome back, <?= htmlspecialchars($user_name) ?>! 👋
                    </h2>
                    <p class="welcome-subtext text-subtle m-0">
                        Real-time wallet balance & transaction dashboard.
                    </p>
                </div>
                <?php if ($monthly_budget > 0): ?>
                    <div class="text-end">
                        <small class="text-subtle d-block">Monthly Budget Used</small>
                        <strong class="text-white fs-6">₹<?= number_format($current_month_expense, 2) ?> / ₹<?= number_format($monthly_budget, 2) ?></strong>
                        <div class="progress mt-1" style="height: 6px; width: 140px;">
                            <div class="progress-bar <?= $current_month_expense > $monthly_budget ? 'bg-danger' : ($current_month_expense >= ($monthly_budget * 0.8) ? 'bg-warning' : 'bg-success') ?>" role="progressbar" style="width: <?= $budget_percent ?>%"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

           <!-- Overview Summary Cards -->
            <div class="summary-grid mb-4">
                <div class="card card-custom card-income-border p-3">
                    <small class="text-subtle text-uppercase fw-bold" style="font-size: 0.7rem;">Total Income</small>
                    <h4 class="text-success mt-2 mb-0 fw-bold">₹<?= number_format($total_income, 2); ?></h4>
                </div>
                <div class="card card-custom card-expense-border p-3">
                    <small class="text-subtle text-uppercase fw-bold" style="font-size: 0.7rem;">Total Expense</small>
                    <h4 class="text-danger mt-2 mb-0 fw-bold">₹<?= number_format($total_expense, 2); ?></h4>
                </div>
                <div class="card card-custom border-start border-3 border-warning p-3">
                    <small class="text-subtle text-uppercase fw-bold" style="font-size: 0.7rem;">💵 Cash Wallet</small>
                    <h4 class="text-warning mt-2 mb-0 fw-bold">₹<?= number_format($cash_balance, 2); ?></h4>
                </div>
                <div class="card card-custom border-start border-3 border-info p-3">
                    <small class="text-subtle text-uppercase fw-bold" style="font-size: 0.7rem;">📱 Bank / Online</small>
                    <h4 class="text-info mt-2 mb-0 fw-bold">₹<?= number_format($online_balance, 2); ?></h4>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <a href="modules/add_transaction.php?type=income" class="text-decoration-none">
                        <div class="card card-custom btn-action-income p-3 text-center">
                            <div class="d-flex align-items-center justify-content-center gap-2 py-1">
                                <i class="fa-solid fa-circle-plus text-success fs-4"></i>
                                <span class="fw-bold text-white fs-6">Add Income</span>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-6">
                    <a href="modules/add_transaction.php?type=expense" class="text-decoration-none">
                        <div class="card card-custom btn-action-expense p-3 text-center">
                            <div class="d-flex align-items-center justify-content-center gap-2 py-1">
                                <i class="fa-solid fa-circle-minus text-danger fs-4"></i>
                                <span class="fw-bold text-white fs-6">Add Expense</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card card-custom p-3 h-100">
                        <h6 class="mb-3 text-white fw-bold"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Expense Breakdown</h6>
                        <div class="chart-container d-flex justify-content-center align-items-center">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-custom p-3 h-100">
                        <h6 class="mb-3 text-white fw-bold"><i class="fa-solid fa-chart-column me-2 text-success"></i>Income vs Expense</h6>
                        <div class="chart-container">
                            <canvas id="compareChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Table Section -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="m-0 text-white fw-bold fs-6"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Today's Activity</h6>
                <a href="modules/transactions.php" class="btn btn-primary btn-sm px-3">
                    <i class="fa-solid fa-list-check me-1"></i> View All
                </a>
            </div>

            <div class="card card-custom p-3 mb-4">
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

<!-- Bottom Right Dynamic Toast Notification Popup -->
<div id="smartExpenseToast" class="toast-popup shadow-lg">
    <div class="toast-popup-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid <?= $reminder_icon ?> me-1"></i> XPenz Smart Reminder</span>
        <button type="button" class="btn-close-custom" onclick="dismissToast()">&times;</button>
    </div>
    <div class="toast-popup-body">
        <?= htmlspecialchars($reminder_text) ?>
    </div>
</div>

<!-- Mobile Fixed Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
    <a href="home.php" class="active"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="modules/transactions.php"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="modules/subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="modules/goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="modules/analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
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