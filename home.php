<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Totals
$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'income'");
$stmt->execute([':user_id' => $user_id]);
$total_income = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = :user_id AND type = 'expense'");
$stmt->execute([':user_id' => $user_id]);
$total_expense = $stmt->fetch()['total'] ?? 0;

$net_balance = $total_income - $total_expense;
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
            <span class="badge bg-primary rounded-circle p-2 fs-6">VP</span>
            <a href="auth/logout.php" class="btn btn-outline-danger btn-sm"><i class="fa-solid fa-right-from-bracket me-1"></i> Logout</a>
        </div>
    </div>

    <!-- Welcome Text -->
<div class="mb-4">
    <h2>Welcome back, Vyom Patel! 👋</h2>
    <p class="text-subtle m-0">Here is your real-time financial summary.</p>
</div>

    <!-- Colored Summary Cards -->
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

    <!-- Actions -->
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

    <!-- History Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent History</h4>
        <a href="modules/transactions.php" class="btn btn-primary">
            <i class="fa-solid fa-list-check me-1"></i> View All Transactions
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>