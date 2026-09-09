<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: transactions.php");
    exit();
}

// Fetch existing data
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = :id AND user_id = :user_id");
$stmt->execute([':id' => $id, ':user_id' => $user_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    header("Location: transactions.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['title']);
    $amount = trim($_POST['amount']);
    $category = trim($_POST['category']);
    $type = trim($_POST['type']);

    if (empty($description) || empty($amount) || empty($category)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $update_stmt = $pdo->prepare("UPDATE transactions SET description = :desc, amount = :amount, category = :category, type = :type WHERE id = :id AND user_id = :user_id");
            $update_stmt->execute([
                ':desc' => $description,
                ':amount' => $amount,
                ':category' => $category,
                ':type' => $type,
                ':id' => $id,
                ':user_id' => $user_id
            ]);

            $_SESSION['msg'] = "Transaction updated successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: transactions.php");
            exit();
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-5" style="max-width: 600px;">
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="m-0"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Transaction</h3>
            <a href="transactions.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select" required>
                    <option value="income" <?= $transaction['type'] === 'income' ? 'selected' : '' ?>>Income</option>
                    <option value="expense" <?= $transaction['type'] === 'expense' ? 'selected' : '' ?>>Expense</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Title / Description</label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($transaction['description']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($transaction['amount']) ?>" required>
            </div>

            <div class="mb-4">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($transaction['category']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-rotate me-1"></i> Update Transaction
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>