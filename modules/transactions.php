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

// Handle Inline Transaction Update from Popup Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction_modal'])) {
    $trans_id = intval($_POST['trans_id']);
    $type = trim($_POST['type']);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $category = trim($_POST['category']);
    $payment_method = trim($_POST['payment_method']);
    $created_at = trim($_POST['created_at']);

    if (empty($description) || $amount <= 0 || empty($category)) {
        $_SESSION['msg'] = "Please fill in all required fields correctly.";
        $_SESSION['msg_type'] = "danger";
    } else {
        $up_t = $pdo->prepare("UPDATE transactions SET type = :type, description = :desc, amount = :amt, category = :cat, payment_method = :pm, created_at = :cdt WHERE id = :id AND user_id = :uid");
        $up_t->execute([
            ':type' => $type,
            ':desc' => $description,
            ':amt' => $amount,
            ':cat' => $category,
            ':pm' => $payment_method,
            ':cdt' => $created_at,
            ':id' => $trans_id,
            ':uid' => $user_id
        ]);
        $_SESSION['msg'] = "Transaction updated successfully!";
        $_SESSION['msg_type'] = "success";
    }
    header("Location: transactions.php");
    exit();
}

// Fetch User Profile Initials & Details
$user_stmt = $pdo->prepare("SELECT name, email, monthly_budget FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';
$user_email = !empty($user['email']) ? $user['email'] : 'user@xpenz.com';

$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Delete Record
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND user_id = :user_id");
    $del_stmt->execute([':id' => $del_id, ':user_id' => $user_id]);
    $_SESSION['msg'] = "Transaction deleted successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: transactions.php");
    exit();
}

// Filter and Search Parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Build Query Dynamically
$query = "SELECT * FROM transactions WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if (!empty($search)) {
    $query .= " AND description LIKE :search";
    $params[':search'] = "%$search%";
}
if (!empty($type_filter)) {
    $query .= " AND type = :type";
    $params[':type'] = $type_filter;
}
if (!empty($category_filter)) {
    $query .= " AND category = :category";
    $params[':category'] = $category_filter;
}
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

$query .= " ORDER BY created_at DESC, id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Fetch unique categories for filter dropdown
$cat_stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :user_id ORDER BY category ASC");
$cat_stmt->execute([':user_id' => $user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions History - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- External Theme Switcher Engine -->
    <script src="../assets/js/theme.js"></script>

    <style>
        .inner-trans-box {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .inner-trans-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
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
        <!-- Top Fixed Header -->
        <header class="top-header d-flex justify-content-between align-items-center px-4">
            <div class="d-flex align-items-center gap-2 d-md-none">
                <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
            </div>

            <!-- Live Date & Time -->
            <div class="d-none d-sm-flex align-items-center gap-3 text-subtle small">
                <span><i class="fa-solid fa-calendar-days me-1 text-primary"></i> <?= date('d M Y') ?></span>
                <span><i class="fa-solid fa-clock me-1 text-success"></i> <span id="liveClock"><?= date('h:i A') ?></span></span>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="badge bg-secondary text-light d-none d-md-inline-block px-3 py-2 rounded-pill fw-normal" style="font-size: 0.8rem;">
                    <i class="fa-solid fa-shield-halved text-warning me-1"></i> Secure Wallet
                </span>

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
            <div class="card card-custom p-4 mb-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h4 class="m-0 fw-bold text-white"><i class="fa-solid fa-list-check me-2 text-primary"></i>Transactions History</h4>
                </div>

                <!-- Filter & Search Box (Inner Box) -->
                <div class="card card-custom inner-trans-box p-3 mb-4 border-secondary">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <label class="form-label small text-subtle">Search Description</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <label class="form-label small text-subtle">Type</label>
                            <select name="type" class="form-select form-select-sm">
                                <option value="">All Types</option>
                                <option value="income" <?= $type_filter === 'income' ? 'selected' : '' ?>>Income</option>
                                <option value="expense" <?= $type_filter === 'expense' ? 'selected' : '' ?>>Expense</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <label class="form-label small text-subtle">Category</label>
                            <select name="category" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-6">
                            <label class="form-label small text-subtle">Start Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-12">
                            <label class="form-label small text-subtle">End Date</label>
                            <div class="d-flex gap-1">
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>">
                                <button type="submit" class="btn btn-primary btn-sm px-2" title="Filter"><i class="fa-solid fa-filter"></i></button>
                                <a href="transactions.php" class="btn btn-outline-secondary btn-sm px-2" title="Reset"><i class="fa-solid fa-rotate-right"></i></a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Transactions Table Box (Inner Box) -->
                <div class="card card-custom inner-trans-box p-3 border-secondary">
                    <div class="table-responsive">
                        <table class="table table-dark-custom table-hover align-middle m-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Method</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th class="text-center">Action</th>
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
                                            <td class="text-center">
                                                <!-- Edit Button Triggers Popup Modal -->
                                                <button type="button" class="btn btn-sm btn-warning me-1 edit-btn" 
                                                        data-id="<?= $t['id'] ?>"
                                                        data-type="<?= $t['type'] ?>"
                                                        data-description="<?= htmlspecialchars($t['description']) ?>"
                                                        data-amount="<?= $t['amount'] ?>"
                                                        data-category="<?= htmlspecialchars($t['category']) ?>"
                                                        data-payment="<?= $t['payment_method'] ?? 'online' ?>"
                                                        data-date="<?= date('Y-m-d', strtotime($t['created_at'])) ?>"
                                                        title="Edit Transaction">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                                <!-- Delete Button -->
                                                <a href="transactions.php?delete_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this transaction?');" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No transactions found matching your filter criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ================= MODAL POPUPS ================= -->

<!-- 1. Edit Transaction Popup Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold text-warning" id="editTransactionModalLabel"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_transaction_modal" value="1">
                    <input type="hidden" name="trans_id" id="modal_trans_id">

                    <div class="mb-3">
                        <label class="form-label small text-subtle">Type *</label>
                        <select name="type" id="modal_type" class="form-select" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-subtle">Title / Description *</label>
                        <input type="text" name="description" id="modal_description" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-subtle">Amount (₹) *</label>
                        <input type="number" step="0.01" name="amount" id="modal_amount" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-subtle">Category *</label>
                        <input type="text" name="category" id="modal_category" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-subtle">Payment Method *</label>
                        <select name="payment_method" id="modal_payment_method" class="form-select" required>
                            <option value="online">UPI / Online Payment</option>
                            <option value="cash">Cash Wallet</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-subtle">Transaction Date *</label>
                        <input type="date" name="created_at" id="modal_created_at" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark fw-bold btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Update Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit Profile & Budget Modal -->
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

<!-- 3. Change Password Modal -->
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
    <a href="../home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php" class="active"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="loans.php"><i class="fa-solid fa-building-columns"></i><span>Loans</span></a>
    <a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
    <a href="pnl_statement.php"><i class="fa-solid fa-file-invoice-dollar"></i><span>P&L</span></a>
</div>

<script>
    // Live Clock Updater
    function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        let minutes = now.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        const strTime = hours + ':' + minutes + ' ' + ampm;
        const clockElem = document.getElementById('liveClock');
        if (clockElem) {
            clockElem.innerText = strTime;
        }
    }
    setInterval(updateClock, 1000);

    // Populate Edit Transaction Modal on button click
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function () {
            document.getElementById('modal_trans_id').value = this.getAttribute('data-id');
            document.getElementById('modal_type').value = this.getAttribute('data-type');
            document.getElementById('modal_description').value = this.getAttribute('data-description');
            document.getElementById('modal_amount').value = this.getAttribute('data-amount');
            document.getElementById('modal_category').value = this.getAttribute('data-category');
            document.getElementById('modal_payment_method').value = this.getAttribute('data-payment');
            document.getElementById('modal_created_at').value = this.getAttribute('data-date');

            // Show Bootstrap Modal
            var editModal = new bootstrap.Modal(document.getElementById('editTransactionModal'));
            editModal.show();
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>