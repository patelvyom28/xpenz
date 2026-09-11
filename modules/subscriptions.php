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
<html lang="en">
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
<body>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Subscriptions & Bills</h2>
        <a href="../home.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard</a>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>