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
    header("Location: goals.php");
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
    header("Location: goals.php");
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

// Delete Goal
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM goals WHERE id = :id AND user_id = :user_id");
    $del_stmt->execute([':id' => $del_id, ':user_id' => $user_id]);
    $_SESSION['msg'] = "Savings goal deleted!";
    $_SESSION['msg_type'] = "success";
    header("Location: goals.php");
    exit();
}

// Add New Savings Goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_goal'])) {
    $goal_name = trim($_POST['goal_name']);
    $target_amount = trim($_POST['target_amount']);
    $current_amount = (float)trim($_POST['current_amount']);
    $target_date = $_POST['target_date'];

    if (empty($goal_name) || empty($target_amount) || empty($target_date)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO goals (user_id, goal_name, target_amount, current_amount, target_date) VALUES (:user_id, :goal_name, :target_amount, :current_amount, :target_date)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':goal_name' => $goal_name,
            ':target_amount' => $target_amount,
            ':current_amount' => $current_amount ?: 0,
            ':target_date' => $target_date
        ]);

        // If user enters initial savings amount > 0, auto-record in main transactions as Savings Expense
        if ($current_amount > 0) {
            $t_stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, amount, category, type) VALUES (:user_id, :desc, :amount, 'Savings', 'expense')");
            $t_stmt->execute([
                ':user_id' => $user_id,
                ':desc' => 'Initial Savings Goal: ' . $goal_name,
                ':amount' => $current_amount
            ]);
        }

        $success = "Savings goal added and synced with main expenses!";
    }
}

// Deposit Money into Goal Logic with Auto Expense Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_money'])) {
    $goal_id = $_POST['goal_id'];
    $deposit_amount = (float)$_POST['deposit_amount'];

    if ($deposit_amount > 0) {
        // 1. Update Goal Savings
        $stmt = $pdo->prepare("UPDATE goals SET current_amount = current_amount + :amt WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':amt' => $deposit_amount, ':id' => $goal_id, ':user_id' => $user_id]);

        // Fetch Goal Title
        $g_stmt = $pdo->prepare("SELECT goal_name FROM goals WHERE id = :id");
        $g_stmt->execute([':id' => $goal_id]);
        $goal_data = $g_stmt->fetch();
        $g_name = $goal_data['goal_name'] ?? 'Savings Goal';

        // 2. Auto Insert into Main Transactions Table
        $t_stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, amount, category, type) VALUES (:user_id, :desc, :amount, 'Savings', 'expense')");
        $t_stmt->execute([
            ':user_id' => $user_id,
            ':desc' => 'Deposit to Goal: ' . $g_name,
            ':amount' => $deposit_amount
        ]);

        // Check if goal reached
        $check_stmt = $pdo->prepare("SELECT target_amount, current_amount FROM goals WHERE id = :id");
        $check_stmt->execute([':id' => $goal_id]);
        $g = $check_stmt->fetch();

        if ($g && $g['current_amount'] >= $g['target_amount']) {
            $pdo->prepare("UPDATE goals SET status = 'achieved' WHERE id = :id")->execute([':id' => $goal_id]);
        }

        $success = "Savings updated and recorded in transactions/charts!";
    }
}

// Fetch User Goals
$stmt = $pdo->prepare("SELECT * FROM goals WHERE user_id = :user_id ORDER BY created_at DESC");
$stmt->execute([':user_id' => $user_id]);
$goals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal-based Savings - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Theme Switcher External JS Engine -->
    <script src="../assets/js/theme.js"></script>

    <style>
        /* Inner boxes hover lift effect */
        .inner-form-box, .inner-goal-box {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .inner-form-box:hover, .inner-goal-box:hover {
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
            <a href="goals.php" class="nav-link active rounded-3"><i class="fa-solid fa-bullseye me-3"></i>Goals</a>
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
                    <h4 class="m-0 fw-bold text-white"><i class="fa-solid fa-bullseye me-2 text-primary"></i>Goal-based Savings Tracker</h4>
                </div>

                <div class="row g-4">
                    <!-- Add New Goal Form (1st Inner Box with Hover) -->
                    <div class="col-md-4">
                        <div class="card card-custom inner-form-box p-3 border-secondary h-100">
                            <h5 class="mb-3 fw-bold">Set New Savings Goal</h5>
                            <form method="POST">
                                <input type="hidden" name="add_goal" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Goal Title</label>
                                    <input type="text" name="goal_name" class="form-control" placeholder="e.g. New Laptop, Emergency Fund, Goa Trip" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Target Amount (₹)</label>
                                    <input type="number" step="0.01" name="target_amount" class="form-control" placeholder="50000" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Initial Savings (₹)</label>
                                    <input type="number" step="0.01" name="current_amount" class="form-control" placeholder="0.00">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Target Date</label>
                                    <input type="date" name="target_date" class="form-control" required>
                                </div>

                                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i> Create Goal</button>
                            </form>
                        </div>
                    </div>

                    <!-- Goals Progress List (2nd Inner Box container) -->
                    <div class="col-md-8">
                        <div class="row g-3">
                            <?php if (count($goals) > 0): ?>
                                <?php foreach ($goals as $g): 
                                    $percent = min(100, round(($g['current_amount'] / $g['target_amount']) * 100));
                                ?>
                                    <div class="col-12">
                                        <div class="card card-custom inner-goal-box p-4 border-secondary">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h5 class="m-0 fw-bold"><?= htmlspecialchars($g['goal_name']) ?></h5>
                                                    <small class="text-subtle">Target Date: <?= date('d M Y', strtotime($g['target_date'])) ?></small>
                                                </div>
                                                <div>
                                                    <?php if ($g['status'] === 'achieved' || $percent >= 100): ?>
                                                        <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Goal Achieved</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-spinner me-1"></i> In Progress</span>
                                                    <?php endif; ?>
                                                    <a href="goals.php?delete_id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-danger ms-2" onclick="return confirm('Delete this goal?');">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between text-subtle small mb-1">
                                                <span>Saved: ₹<?= number_format($g['current_amount'], 2) ?></span>
                                                <span>Target: ₹<?= number_format($g['target_amount'], 2) ?> (<?= $percent ?>%)</span>
                                            </div>

                                            <div class="progress bg-secondary mb-3" style="height: 12px;">
                                                <div class="progress-bar <?= $percent >= 100 ? 'bg-success' : 'bg-primary' ?>" role="progressbar" style="width: <?= $percent ?>%;"></div>
                                            </div>

                                            <?php if ($percent < 100): ?>
                                                <form method="POST" class="row g-2 align-items-center">
                                                    <input type="hidden" name="deposit_money" value="1">
                                                    <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
                                                    <div class="col-8 col-sm-9">
                                                        <input type="number" step="0.01" name="deposit_amount" class="form-control form-control-sm" placeholder="Add additional savings (₹)" required>
                                                    </div>
                                                    <div class="col-4 col-sm-3">
                                                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fa-solid fa-piggy-bank me-1"></i> Deposit</button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5 text-muted card card-custom border-secondary">
                                    <i class="fa-solid fa-bullseye fa-3x mb-3 text-primary"></i>
                                    <h5>No savings goals created yet.</h5>
                                    <p class="m-0">Start setting targets for your financial future!</p>
                                </div>
                            <?php endif; ?>
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