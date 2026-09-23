<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Settings Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Update Password
    if (isset($_POST['update_password'])) {
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
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $up_stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $up_stmt->execute([':pass' => $hashed_password, ':id' => $user_id]);
            $_SESSION['msg'] = "Password updated successfully!";
            $_SESSION['msg_type'] = "success";
        }
        header("Location: settings.php");
        exit();
    }

    // 2. Update Currency
    if (isset($_POST['update_currency'])) {
        $currency = $_POST['currencyOption'] ?? 'INR';
        $stmt = $pdo->prepare("UPDATE users SET currency = :curr WHERE id = :id");
        $stmt->execute([':curr' => $currency, ':id' => $user_id]);
        $_SESSION['msg'] = "Currency updated successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: settings.php");
        exit();
    }

    // 3. Update Date Format
    if (isset($_POST['update_date_format'])) {
        $df = $_POST['dateFormat'] ?? 'dd-MM-yyyy';
        $stmt = $pdo->prepare("UPDATE users SET date_format = :df WHERE id = :id");
        $stmt->execute([':df' => $df, ':id' => $user_id]);
        $_SESSION['msg'] = "Date format updated successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: settings.php");
        exit();
    }

    // 4. Update Time Format
    if (isset($_POST['update_time_format'])) {
        $tf = $_POST['timeFormat'] ?? '12-hour';
        $stmt = $pdo->prepare("UPDATE users SET time_format = :tf WHERE id = :id");
        $stmt->execute([':tf' => $tf, ':id' => $user_id]);
        $_SESSION['msg'] = "Time format updated successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: settings.php");
        exit();
    }
}

// Fetch User Info & Preferences
$user_stmt = $pdo->prepare("SELECT name, email, monthly_budget, currency, date_format, time_format FROM users WHERE id = :user_id");
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch();
$user_name = !empty($user['name']) ? $user['name'] : 'User';

$words = explode(' ', trim($user_name));
$initials = count($words) >= 2 
    ? strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1))
    : strtoupper(substr($user_name, 0, 2));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - XPenz</title>
    
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
            <a href="settings.php" class="nav-link active rounded-3"><i class="fa-solid fa-gear me-3"></i>Settings</a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content-wrapper">
        <header class="top-header d-flex justify-content-between align-items-center px-4">
            <div class="d-flex align-items-center gap-2 d-md-none">
                <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 34px;">
            </div>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="dropdown">
                    <span class="badge bg-primary rounded-circle avatar-badge dropdown-toggle" type="button" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <?= htmlspecialchars($initials) ?>
                    </span>
                    <ul class="dropdown-menu dropdown-menu-end card-custom p-2 shadow-lg border-secondary" aria-labelledby="profileDropdown" style="min-width: 260px; background-color: var(--card-bg) !important;">
                        <li class="px-3 py-2 border-bottom border-secondary mb-2 text-center">
                            <span class="badge bg-primary rounded-circle p-3 fs-4 avatar-badge mb-1" style="width: 50px; height: 50px; display: inline-flex; align-items: center; justify-content: center;"><?= htmlspecialchars($initials) ?></span>
                            <span class="fw-bold d-block text-white mt-1"><?= htmlspecialchars($user_name) ?></span>
                            <small class="text-subtle"><?= htmlspecialchars($user['email']) ?></small>
                        </li>
                        <li><a class="dropdown-item rounded-2 text-danger py-2 mt-1" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="dashboard-body-content">
            <?php if (isset($_SESSION['msg'])): ?>
                <div class="alert alert-<?= $_SESSION['msg_type'] ?? 'success'; ?> alert-dismissible fade show mb-4">
                    <?= $_SESSION['msg']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
            <?php endif; ?>

            <div class="card card-custom p-4 mb-4">
                <h3 class="fw-bold text-white mb-4"><i class="fa-solid fa-gear me-2 text-primary"></i>Settings</h3>

                <div class="list-group list-group-flush bg-transparent">
                    <!-- Currency -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#currencyModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Currency</h6>
                            <small class="text-subtle"><?= htmlspecialchars($user['currency'] ?? 'INR') ?></small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Password / Security -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#passwordModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Password</h6>
                            <small class="text-subtle">Change account password</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Date Format -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#dateFormatModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Date Format</h6>
                            <small class="text-subtle"><?= htmlspecialchars($user['date_format'] ?? 'dd-MM-yyyy') ?></small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Time Format -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#timeFormatModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Time Format</h6>
                            <small class="text-subtle"><?= htmlspecialchars($user['time_format'] ?? '12-Hour') ?></small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Categories -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Category</h6>
                            <small class="text-subtle">Manage income & expense categories</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Payment Mode -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#paymentModeModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Payment Mode</h6>
                            <small class="text-subtle">Cash Wallet & UPI / Online</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Database -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#databaseModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Database</h6>
                            <small class="text-subtle">Import, Export, or Backup Data</small>
                        </div>
                        <i class="fa-solid fa-chevron-right text-subtle"></i>
                    </div>

                    <!-- Suggestion / Contact Us -->
                    <div class="list-group-item bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="modal" data-bs-target="#suggestionModal">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">Suggestion / Contact Us</h6>
                            <small class="text-subtle">support@xpenz.com</small>
                        </div>
                        <i class="fa-solid fa-envelope text-primary"></i>
                    </div>

                    <!-- App Version -->
                    <div class="list-group-item bg-transparent border-0 py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-white fw-bold">App Version</h6>
                            <small class="text-subtle">Version 1.0.0 (XPenz Pro)</small>
                        </div>
                        <span class="badge bg-success">Latest</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ================= MODALS WITH FORMS ================= -->

<!-- 1. Currency Modal -->
<div class="modal fade" id="currencyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-wave me-2 text-success"></i>Select Currency</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body" style="max-height: 350px; overflow-y: auto;">
                    <input type="hidden" name="update_currency" value="1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="currencyOption" id="cur1" value="INR" <?= ($user['currency'] ?? 'INR') == 'INR' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cur1">Indian Rupee (₹) <small class="text-subtle d-block">INR</small></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="currencyOption" id="cur2" value="USD" <?= ($user['currency'] ?? '') == 'USD' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cur2">US Dollar ($) <small class="text-subtle d-block">USD</small></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="currencyOption" id="cur3" value="EUR" <?= ($user['currency'] ?? '') == 'EUR' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cur3">Euro (€) <small class="text-subtle d-block">EUR</small></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="currencyOption" id="cur4" value="GBP" <?= ($user['currency'] ?? '') == 'GBP' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cur4">British Pound (£) <small class="text-subtle d-block">GBP</small></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="currencyOption" id="cur5" value="JPY" <?= ($user['currency'] ?? '') == 'JPY' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cur5">Japanese Yen (¥) <small class="text-subtle d-block">JPY</small></label>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2 text-warning"></i>Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_password" value="1">
                    <div class="mb-3">
                        <label class="form-label small text-subtle">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-subtle">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-subtle">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Date Format Modal -->
<div class="modal fade" id="dateFormatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar me-2 text-primary"></i>Select Date Format</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_date_format" value="1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="dateFormat" id="df1" value="dd-MM-yyyy" <?= ($user['date_format'] ?? 'dd-MM-yyyy') == 'dd-MM-yyyy' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="df1">dd-MM-yyyy <small class="text-subtle d-block">14-09-2026</small></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="dateFormat" id="df2" value="dd-MMM-yyyy" <?= ($user['date_format'] ?? '') == 'dd-MMM-yyyy' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="df2">dd-MMM-yyyy <small class="text-subtle d-block">14-Sep-2026</small></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="dateFormat" id="df3" value="yyyy-MM-dd" <?= ($user['date_format'] ?? '') == 'yyyy-MM-dd' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="df3">yyyy-MM-dd <small class="text-subtle d-block">2026-09-14</small></label>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Time Format Modal -->
<div class="modal fade" id="timeFormatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-clock me-2 text-info"></i>Select Time Format</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_time_format" value="1">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="timeFormat" id="tf1" value="12-Hour" <?= ($user['time_format'] ?? '12-Hour') == '12-Hour' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tf1">12-Hour (hh:mm a)</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="timeFormat" id="tf2" value="24-Hour" <?= ($user['time_format'] ?? '') == '24-Hour' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tf2">24-Hour (HH:mm)</label>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. Category List Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-list me-2 text-success"></i>Category List</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent border-secondary text-white d-flex justify-content-between align-items-center">Food & Snacks <span class="text-subtle"><i class="fa-solid fa-pen me-3"></i><i class="fa-solid fa-trash text-danger"></i></span></li>
                    <li class="list-group-item bg-transparent border-secondary text-white d-flex justify-content-between align-items-center">Travel & Fuel <span class="text-subtle"><i class="fa-solid fa-pen me-3"></i><i class="fa-solid fa-trash text-danger"></i></span></li>
                    <li class="list-group-item bg-transparent border-secondary text-white d-flex justify-content-between align-items-center">Salary <span class="text-subtle"><i class="fa-solid fa-pen me-3"></i><i class="fa-solid fa-trash text-danger"></i></span></li>
                    <li class="list-group-item bg-transparent border-secondary text-white d-flex justify-content-between align-items-center">Shopping <span class="text-subtle"><i class="fa-solid fa-pen me-3"></i><i class="fa-solid fa-trash text-danger"></i></span></li>
                </ul>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-success btn-sm w-100"><i class="fa-solid fa-plus me-1"></i> Add New Category</button>
            </div>
        </div>
    </div>
</div>

<!-- 6. Payment Mode Modal -->
<div class="modal fade" id="paymentModeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-wallet me-2 text-warning"></i>Payment Mode List</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent border-secondary text-white d-flex justify-content-between align-items-center">Cash Wallet <span class="text-subtle"><i class="fa-solid fa-pen me-3"></i><i class="fa-solid fa-trash text-danger"></i></span></li>
                    <li class="list-group-item bg-transparent border-secondary text-white d-flex justify-content-between align-items-center">UPI / Online <span class="text-subtle"><i class="fa-solid fa-pen me-3"></i><i class="fa-solid fa-trash text-danger"></i></span></li>
                </ul>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold w-100"><i class="fa-solid fa-plus me-1"></i> Add Payment Mode</button>
            </div>
        </div>
    </div>
</div>

<!-- 7. Database Modal -->
<div class="modal fade" id="databaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-database me-2 text-info"></i>Database Management</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-light btn-sm text-start py-2"><i class="fa-solid fa-file-arrow-down me-2 text-primary"></i> Import Database From Storage</button>
                    <button class="btn btn-outline-light btn-sm text-start py-2"><i class="fa-solid fa-file-arrow-up me-2 text-success"></i> Export Database (Backup)</button>
                    <button class="btn btn-outline-light btn-sm text-start py-2"><i class="fa-solid fa-envelope-circle-check me-2 text-warning"></i> Email Database Backup</button>
                    <button class="btn btn-outline-danger btn-sm text-start py-2"><i class="fa-solid fa-triangle-exclamation me-2"></i> Delete All Data</button>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 8. Suggestion / Contact Us Modal -->
<div class="modal fade" id="suggestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-secondary bg-dark text-white shadow-lg">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-paper-plane me-2 text-primary"></i>Suggestion & Support</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-subtle">Your Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user_name) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-subtle">Email ID</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-subtle">Description / Feedback</label>
                        <textarea class="form-control" rows="3" placeholder="Write your suggestions here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check me-1"></i> Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav d-md-none">
    <a href="../home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="transactions.php"><i class="fa-solid fa-receipt"></i><span>History</span></a>
    <a href="subscriptions.php"><i class="fa-solid fa-calendar-check"></i><span>Subs</span></a>
    <a href="goals.php"><i class="fa-solid fa-bullseye"></i><span>Goals</span></a>
    <a href="loans.php"><i class="fa-solid fa-building-columns"></i><span>Loans</span></a>
    <a href="analytics.php"><i class="fa-solid fa-chart-pie"></i><span>Analytics</span></a>
    <a href="settings.php" class="active"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>