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
    header("Location: subscriptions.php");
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
    header("Location: subscriptions.php");
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

// Delete Subscription Logic
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM subscriptions WHERE id = :id AND user_id = :user_id");
    $del_stmt->execute([':id' => $delete_id, ':user_id' => $user_id]);
    
    $_SESSION['msg'] = "Subscription removed successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: subscriptions.php");
    exit();
}

// Add New Subscription Logic with Auto Expense Sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sub'])) {
    $title = trim($_POST['title']);
    $amount = trim($_POST['amount']);
    $billing_cycle = $_POST['billing_cycle'];
    $due_date = $_POST['due_date'];
    $category = $_POST['category'];

    if (empty($title) || empty($amount) || empty($due_date)) {
        $error = "Please fill in all required fields.";
    } else {
        // 1. Insert into Subscriptions Table
        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, title, amount, billing_cycle, due_date, category) VALUES (:user_id, :title, :amount, :billing_cycle, :due_date, :category)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':title' => $title,
            ':amount' => $amount,
            ':billing_cycle' => $billing_cycle,
            ':due_date' => $due_date,
            ':category' => $category
        ]);

        // 2. Auto Insert into Main Transactions Table for Charts & Total Expense
        $t_stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, amount, category, type) VALUES (:user_id, :desc, :amount, :category, 'expense')");
        $t_stmt->execute([
            ':user_id' => $user_id,
            ':desc' => 'Subscription: ' . $title,
            ':amount' => $amount,
            ':category' => $category
        ]);

        $success = "Subscription added and synced with main expenses!";
    }
}

// Fetch User Subscriptions
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = :user_id ORDER BY due_date ASC");
$stmt->execute([':user_id' => $user_id]);
$subscriptions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions & Bills - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Theme Switcher External JS Engine -->
    <script src="../assets/js/theme.js"></script>
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
            <a href="subscriptions.php" class="nav-link active rounded-3"><i class="fa-solid fa-calendar-check me-3"></i>Subscriptions</a>
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
            <div class="d-flex justify-content-center align-items-center mb-4">
                <h2 class="text-center m-0 w-100"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Subscriptions & Bills</h2>
            </div>

            <!-- Session Flash Message -->
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-<?= $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <div class="row g-4">
                <!-- Add Subscription Form -->
                <div class="col-md-4">
                    <div class="card card-custom p-3">
                        <h5 class="mb-3 fw-bold">Add Subscription / Bill</h5>
                        <form method="POST">
                            <input type="hidden" name="add_sub" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Service Title</label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Netflix, Wi-Fi, Gym, Car EMI" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount (₹)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Billing Cycle</label>
                                <select name="billing_cycle" class="form-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Next Due Date</label>
                                <input type="date" name="due_date" class="form-control" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="Entertainment">Entertainment (Netflix, Prime, Spotify)</option>
                                    <option value="Wi-Fi & Broadband">Wi-Fi & Broadband</option>
                                    <option value="Mobile Recharge">Mobile Recharge</option>
                                    <option value="Gym & Fitness">Gym & Fitness</option>
                                    <option value="EMI & Loans">EMI & Loans</option>
                                    <option value="Rent & Utilities">Rent & Utilities</option>
                                    <option value="Insurance Renewal">Insurance Renewal</option>
                                    <option value="Other Bills">Other Bills</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i> Add Record</button>
                        </form>
                    </div>
                </div>

                <!-- Subscriptions List -->
                <div class="col-md-8">
                    <div class="card card-custom p-3">
                        <h5 class="mb-3 fw-bold">Active Subscriptions & Recurring Bills</h5>
                        <div class="table-responsive">
                            <table class="table table-dark-custom table-hover align-middle m-0">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Category</th>
                                        <th>Cycle</th>
                                        <th>Next Due Date</th>
                                        <th>Amount</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($subscriptions) > 0): ?>
                                        <?php foreach ($subscriptions as $s): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($s['title']) ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($s['category']) ?></span></td>
                                                <td><span class="badge bg-info text-dark"><?= ucfirst($s['billing_cycle']) ?></span></td>
                                                <td><?= date('d M Y', strtotime($s['due_date'])) ?></td>
                                                <td class="text-danger fw-bold">₹<?= number_format($s['amount'], 2) ?></td>
                                                <td class="text-center">
                                                    <a href="subscriptions.php?delete_id=<?= $s['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this subscription?');" title="Delete">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-calendar-xmark fa-2x mb-2 d-block"></i> No subscriptions or recurring bills added yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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

<!-- Mobile Bottom Navigation Bar -->
<div class="mobile-bottom-nav d-md-none">
    <a href="../home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php" class="active"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>