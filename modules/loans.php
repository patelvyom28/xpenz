<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

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
    header("Location: loans.php");
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
    header("Location: loans.php");
    exit();
}

// Fetch User Profile Data
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
    $del_stmt = $pdo->prepare("DELETE FROM loans_insurance WHERE id = :id AND user_id = :user_id");
    $del_stmt->execute([':id' => $del_id, ':user_id' => $user_id]);
    $_SESSION['msg'] = "Record deleted successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: loans.php");
    exit();
}

// Pay Installment Logic -> Reduce Balance & Sync with Expense Transactions
if (isset($_GET['pay_id'])) {
    $pay_id = $_GET['pay_id'];
    
    // Fetch loan details
    $stmt = $pdo->prepare("SELECT title, monthly_installment, type FROM loans_insurance WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $pay_id, ':user_id' => $user_id]);
    $item = $stmt->fetch();

    if ($item) {
        $amount = $item['monthly_installment'];
        $desc = "Paid " . strtoupper($item['type']) . " Installment: " . $item['title'];

        // 1. Update Paid Amount in Loans Table
        $u_stmt = $pdo->prepare("UPDATE loans_insurance SET paid_amount = paid_amount + :amt WHERE id = :id AND user_id = :user_id");
        $u_stmt->execute([':amt' => $amount, ':id' => $pay_id, ':user_id' => $user_id]);

        // 2. Insert into transactions table for dashboard chart & expense sync
        $t_stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, amount, category, type) VALUES (:user_id, :desc, :amount, 'EMI & Loans', 'expense')");
        $t_stmt->execute([
            ':user_id' => $user_id,
            ':desc' => $desc,
            ':amount' => $amount
        ]);

        $_SESSION['msg'] = "Installment paid! Remaining balance updated and recorded in Dashboard.";
        $_SESSION['msg_type'] = "success";
        header("Location: loans.php");
        exit();
    }
}

// Add New Loan/Insurance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_portfolio'])) {
    $title = trim($_POST['title']);
    $type = $_POST['type'];
    $total_amount = trim($_POST['total_amount']);
    $monthly_installment = trim($_POST['monthly_installment']);
    $due_day = trim($_POST['due_day']);
    $lender_provider = trim($_POST['lender_provider']);

    if (empty($title) || empty($total_amount) || empty($monthly_installment) || empty($due_day)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO loans_insurance (user_id, title, type, total_amount, paid_amount, monthly_installment, due_day, lender_provider) VALUES (:user_id, :title, :type, :total_amount, 0.00, :monthly_installment, :due_day, :lender_provider)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':title' => $title,
            ':type' => $type,
            ':total_amount' => $total_amount,
            ':monthly_installment' => $monthly_installment,
            ':due_day' => $due_day,
            ':lender_provider' => $lender_provider
        ]);
        $success = "Portfolio item added successfully!";
    }
}

// Filter Variable
$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : '';

// Fetch Records with Optional Filter
$query = "SELECT * FROM loans_insurance WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if (!empty($filter_type)) {
    $query .= " AND type = :type";
    $params[':type'] = $filter_type;
}
$query .= " ORDER BY due_day ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans & Insurance Portfolio - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- External Theme Switcher Engine -->
    <script src="../assets/js/theme.js"></script>

    <style>
        /* Inner boxes hover lift effect */
        .inner-form-box, .inner-table-box {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .inner-form-box:hover, .inner-table-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
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
            <a href="transactions.php" class="nav-link rounded-3"><i class="fa-solid fa-list-check me-3"></i>Transactions</a>
            <a href="subscriptions.php" class="nav-link rounded-3"><i class="fa-solid fa-calendar-check me-3"></i>Subscriptions</a>
            <a href="goals.php" class="nav-link rounded-3"><i class="fa-solid fa-bullseye me-3"></i>Goals</a>
            <a href="loans.php" class="nav-link active rounded-3"><i class="fa-solid fa-building-columns me-3"></i>Loans</a>
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
                <div class="alert alert-<?= $_SESSION['msg_type']; ?> alert-dismissible fade show">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- Outer Main Card Box -->
            <div class="card card-custom p-4 mb-4">
                <div class="d-flex justify-content-center align-items-center mb-4">
                    <h4 class="m-0 fw-bold text-white"><i class="fa-solid fa-building-columns me-2 text-primary"></i>Loans & Insurance Portfolio</h4>
                </div>

                <div class="row g-4">
                    <!-- Add Form Box (1st Inner Box with Hover) -->
                    <div class="col-md-4">
                        <div class="card card-custom inner-form-box p-3 border-secondary h-100">
                            <h5 class="mb-3 fw-bold">Add Loan / Insurance / EMI</h5>
                            <form method="POST">
                                <input type="hidden" name="add_portfolio" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-select" required>
                                        <option value="loan">Car / Home Loan</option>
                                        <option value="insurance">Health / Term Insurance</option>
                                        <option value="emi">Product EMI (Gadgets/Appliances)</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Title / Item Name</label>
                                    <input type="text" name="title" class="form-control" placeholder="e.g. Brezza Loan, Health Policy" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Provider / Bank Name</label>
                                    <input type="text" name="lender_provider" class="form-control" placeholder="e.g. HDFC Bank, LIC">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Total Amount / Cover (₹)</label>
                                    <input type="number" step="0.01" name="total_amount" class="form-control" placeholder="500000" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Monthly Installment / Premium (₹)</label>
                                    <input type="number" step="0.01" name="monthly_installment" class="form-control" placeholder="12000" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Monthly Due Day (1-31)</label>
                                    <input type="number" min="1" max="31" name="due_day" class="form-control" placeholder="5" required>
                                </div>

                                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i> Add Record</button>
                            </form>
                        </div>
                    </div>

                    <!-- Liabilities Table Box (2nd Inner Box with Hover) -->
                    <div class="col-md-8">
                        <div class="card card-custom inner-table-box p-3 border-secondary h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <h5 class="m-0 fw-bold">Active Liabilities & Policies</h5>
                                <!-- Type Filter Form -->
                                <form method="GET" action="loans.php" class="d-flex align-items-center gap-2">
                                    <select name="filter_type" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="">All Types</option>
                                        <option value="loan" <?= $filter_type === 'loan' ? 'selected' : '' ?>>Loan</option>
                                        <option value="insurance" <?= $filter_type === 'insurance' ? 'selected' : '' ?>>Insurance</option>
                                        <option value="emi" <?= $filter_type === 'emi' ? 'selected' : '' ?>>EMI</option>
                                    </select>
                                    <?php if (!empty($filter_type)): ?>
                                        <a href="loans.php" class="btn btn-outline-secondary btn-sm" title="Reset Filter"><i class="fa-solid fa-rotate-right"></i></a>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-dark-custom table-hover align-middle m-0">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Remaining / Total</th>
                                            <th>Due Day</th>
                                            <th>Installment</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($items) > 0): ?>
                                            <?php foreach ($items as $item): 
                                                $remaining = max(0, $item['total_amount'] - $item['paid_amount']);
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?= htmlspecialchars($item['title']) ?></div>
                                                        <small class="text-subtle"><?= htmlspecialchars($item['lender_provider'] ?: 'N/A') ?></small>
                                                    </td>
                                                    <td><span class="badge bg-warning text-dark"><?= strtoupper($item['type']) ?></span></td>
                                                    <td>
                                                        <div class="fw-bold">₹<?= number_format($remaining, 2) ?></div>
                                                        <small class="text-subtle">of ₹<?= number_format($item['total_amount'], 2) ?></small>
                                                    </td>
                                                    <td>Every <?= $item['due_day'] ?>th</td>
                                                    <td class="text-danger fw-bold">₹<?= number_format($item['monthly_installment'], 2) ?></td>
                                                    <td class="text-center">
                                                        <?php if ($remaining > 0): ?>
                                                            <a href="loans.php?pay_id=<?= $item['id']; ?>" class="btn btn-sm btn-success me-1" title="Pay Monthly Installment" onclick="return confirm('Record this monthly installment as expense and reduce remaining balance?');">
                                                                <i class="fa-solid fa-credit-card me-1"></i> Pay
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge bg-success me-1"><i class="fa-solid fa-check me-1"></i> Fully Paid</span>
                                                        <?php endif; ?>
                                                        <a href="loans.php?delete_id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?');" title="Delete">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">No loans or insurance records matching your filter.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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