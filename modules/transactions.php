<?php
session_start();
require_once '../config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Filter variables
$type = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Base Query Setup
$query = "SELECT * FROM transactions WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if ($type !== 'all') {
    $query .= " AND type = :type";
    $params[':type'] = $type;
}

if (!empty($search)) {
    $query .= " AND (description LIKE :search OR category LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY created_at DESC, id DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - XPenz</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-list-check me-2 text-primary"></i>All Transactions</h2>
        <a href="../home.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard</a>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type']; ?> alert-dismissible fade show" role="alert">
            <?= $_SESSION['msg']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card card-custom p-3 mb-4">
        <form method="GET" action="transactions.php" class="row g-3">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Search by title or category..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All Types (Income & Expense)</option>
                    <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Income Only</option>
                    <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expense Only</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
            </div>
            <div class="col-md-2">
                <a href="transactions.php" class="btn btn-outline-danger w-100"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="card card-custom p-3">
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th class="text-center">Actions</th>
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
                                    <?php if ($t['type'] === 'income'): ?>
                                        <span class="badge bg-success border border-success">Income</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger border border-danger">Expense</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="<?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                        <?= $t['type'] === 'income' ? '+' : '-' ?> ₹<?= number_format($t['amount'], 2) ?>
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <a href="edit_transaction.php?id=<?= $t['id']; ?>" class="btn btn-sm btn-outline-warning me-1" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="delete_transaction.php?id=<?= $t['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this transaction?');" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block"></i> No transactions found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>