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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal-based Savings - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Theme Switcher External JS Engine -->
    <script src="../assets/js/theme.js"></script>
</head>
<body>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-bullseye me-2 text-primary"></i>Goal-based Savings Tracker</h2>
        <a href="../home.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard</a>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type']; ?> alert-dismissible fade show">
            <?= $_SESSION['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Add New Goal Form -->
        <div class="col-md-4">
            <div class="card card-custom p-3">
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

        <!-- Goals Progress List -->
        <div class="col-md-8">
            <div class="row g-3">
                <?php if (count($goals) > 0): ?>
                    <?php foreach ($goals as $g): 
                        $percent = min(100, round(($g['current_amount'] / $g['target_amount']) * 100));
                    ?>
                        <div class="col-12">
                            <div class="card card-custom p-4">
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
                    <div class="col-12 text-center py-5 text-muted card card-custom">
                        <i class="fa-solid fa-bullseye fa-3x mb-3 text-primary"></i>
                        <h5>No savings goals created yet.</h5>
                        <p class="m-0">Start setting targets for your financial future!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>