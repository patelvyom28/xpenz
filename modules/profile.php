<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch User Data
$stmt = $pdo->prepare("SELECT name, email, monthly_budget FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

$user_name = !empty($user['name']) ? $user['name'] : 'User';
$user_email = !empty($user['email']) ? $user['email'] : 'user@xpenz.com';

// Initials for Header
$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));

// Update Profile Info (Name, Email, Budget)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $monthly_budget = floatval($_POST['monthly_budget']);

    if (empty($name) || empty($email)) {
        $error = "Name and Email cannot be empty.";
    } else {
        $u_stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, monthly_budget = :budget WHERE id = :id");
        $u_stmt->execute([':name' => $name, ':email' => $email, ':budget' => $monthly_budget, ':id' => $user_id]);
        
        $_SESSION['msg'] = "Profile updated successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: profile.php");
        exit();
    }
}

// Change Password Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $p_stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
    $p_stmt->execute([':id' => $user_id]);
    $db_user = $p_stmt->fetch();

    if (!password_verify($current_password, $db_user['password'])) {
        $error = "Incorrect current password!";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $up_stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
        $up_stmt->execute([':pass' => $hashed_password, ':id' => $user_id]);

        $_SESSION['msg'] = "Password changed successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: profile.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/theme.js"></script>
</head>
<body class="bg-dark text-white">

<div class="dashboard-layout">
    <!-- Desktop Sidebar -->
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
            <a href="pnl_statement.php" class="nav-link rounded-3"><i class="fa-solid fa-file-invoice-dollar me-3"></i>P&L Statement</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content-wrapper">
        <header class="top-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2 d-md-none">
                <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
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
                        <li><a class="dropdown-item rounded-2 text-white py-2 mb-1 active" href="profile.php"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit Profile & Budget</a></li>
                        <li><a class="dropdown-item rounded-2 text-white py-2 mb-2" href="profile.php#change-password"><i class="fa-solid fa-key me-2 text-warning"></i> Change Password</a></li>
                        <li><hr class="dropdown-divider border-secondary my-1"></li>
                        <li><a class="dropdown-item rounded-2 text-danger py-2 mt-1" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="dashboard-body-content">
            <div class="mb-4">
                <h2><i class="fa-solid fa-user-gear me-2 text-primary"></i>Google-Style Account Settings</h2>
                <p class="text-subtle">Manage your personal information, monthly budget limits, and security credentials.</p>
            </div>

            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="row g-4">
                <!-- Edit Profile Info Card -->
                <div class="col-md-6">
                    <div class="card card-custom p-4 h-100">
                        <h5 class="mb-3 fw-bold"><i class="fa-solid fa-id-card me-2 text-success"></i>Personal Information</h5>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="mb-3 text-center">
                                <span class="badge bg-primary rounded-circle p-4 fs-2 avatar-badge mb-2" style="width: 80px; height: 80px; display: inline-flex; align-items: center; justify-content: center;"><?= htmlspecialchars($initials) ?></span>
                                <h6 class="text-white mt-2"><?= htmlspecialchars($user_name) ?></h6>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Monthly Expense Budget Limit (₹)</label>
                                <input type="number" step="0.01" name="monthly_budget" class="form-control" value="<?= htmlspecialchars($user['monthly_budget']) ?>">
                            </div>

                            <button type="submit" class="btn btn-success w-100"><i class="fa-solid fa-floppy-disk me-1"></i> Save Changes</button>
                        </form>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="col-md-6" id="change-password">
                    <div class="card card-custom p-4 h-100">
                        <h5 class="mb-3 fw-bold"><i class="fa-solid fa-shield-halved me-2 text-warning"></i>Security & Password</h5>
                        <form method="POST">
                            <input type="hidden" name="change_password" value="1">

                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="At least 6 characters" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                            </div>

                            <button type="submit" class="btn btn-warning text-dark fw-bold w-100"><i class="fa-solid fa-key me-1"></i> Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>