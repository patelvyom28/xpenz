<?php
session_start();
require_once '../config/db.php';

// Auth Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'expense';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['title']);
    $amount = trim($_POST['amount']);
    $category = trim($_POST['category']);
    $trans_type = trim($_POST['type']);

    if (empty($description) || empty($amount) || empty($category)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $query = "INSERT INTO transactions (user_id, description, amount, category, type, created_at) VALUES (:user_id, :description, :amount, :category, :type, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':user_id' => $user_id,
                ':description' => $description,
                ':amount' => $amount,
                ':category' => $category,
                ':type' => $trans_type
            ]);

            header("Location: ../home.php");
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
    <title>Add Transaction - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-5" style="max-width: 600px;">
    <div class="card card-custom p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="m-0 text-capitalize">
                <i class="fa-solid <?= $type === 'income' ? 'fa-circle-plus text-success' : 'fa-circle-minus text-danger' ?> me-2"></i>
                Add <?= htmlspecialchars($type) ?>
            </h3>
            <a href="../home.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="add_transaction.php?type=<?= htmlspecialchars($type) ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">

            <div class="mb-3">
                <label class="form-label">Title / Description</label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Salary, Grocery, Rent" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>

            <div class="mb-4">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    <option value="" disabled selected>Select Category</option>
                    <?php if ($type === 'income'): ?>
                        <option value="Salary">Salary</option>
                        <option value="Freelance">Freelance</option>
                        <option value="Investment">Investment</option>
                        <option value="Other">Other</option>
                    <?php else: ?>
                        <option value="Food & Dining">Food & Dining</option>
                        <option value="Shopping">Shopping</option>
                        <option value="Bills & Utilities">Bills & Utilities</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Transport">Transport</option>
                        <option value="Other">Other</option>
                    <?php endif; ?>
                </select>
            </div>

            <button type="submit" class="btn <?= $type === 'income' ? 'btn-success' : 'btn-danger' ?> w-100 py-2">
                <i class="fa-solid fa-check me-1"></i> Save <?= ucfirst(htmlspecialchars($type)) ?>
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>