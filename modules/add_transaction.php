<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$type = isset($_GET['type']) && $_GET['type'] === 'income' ? 'income' : 'expense';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $payment_method = trim($_POST['payment_method'] ?? 'online');
    $amount = floatval($_POST['amount']);
    $notes = trim($_POST['notes'] ?? '');
    $custom_date = !empty($_POST['custom_date']) ? $_POST['custom_date'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s');

    if (!empty($description) && !empty($category) && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, category, payment_method, type, amount, notes, created_at) VALUES (:user_id, :desc, :cat, :method, :type, :amt, :notes, :created_at)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':desc' => $description,
            ':cat' => $category,
            ':method' => $payment_method,
            ':type' => $type,
            ':amt' => $amount,
            ':notes' => $notes,
            ':created_at' => $custom_date
        ]);

        $_SESSION['msg'] = ucfirst($type) . " recorded successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: ../home.php");
        exit();
    } else {
        $error = "Please fill all required fields correctly.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add <?= ucfirst($type) ?> - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="text-white m-0">
                        <i class="fa-solid <?= $type === 'income' ? 'fa-circle-plus text-success' : 'fa-circle-minus text-danger' ?> me-2"></i>
                        Add <?= ucfirst($type) ?>
                    </h3>
                    <a href="../home.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label text-subtle">Title / Description *</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Tea & Snacks, Dinner with Friends" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-subtle">Category *</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php if ($type === 'income'): ?>
                                    <option value="Salary">Salary</option>
                                    <option value="Freelance">Freelance</option>
                                    <option value="Pocket Money">Pocket Money</option>
                                    <option value="Investments">Investments</option>
                                    <option value="Other">Other Income</option>
                                <?php else: ?>
                                    <option value="Food & Snacks">Food & Snacks</option>
                                    <option value="Travel & Fuel">Travel & Fuel</option>
                                    <option value="Bills & Utilities">Bills & Utilities</option>
                                    <option value="Shopping">Shopping</option>
                                    <option value="EMI & Loans">EMI & Loans</option>
                                    <option value="Other">Other Expense</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-subtle">Payment Method *</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">💵 Cash Wallet</option>
                                <option value="online" selected>📱 UPI / Online Payment</option>
                                <option value="bank_transfer">🏦 Bank Transfer</option>
                                <option value="credit_card">💳 Credit Card</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-subtle">Amount (INR) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-subtle">Transaction Date *</label>
                            <input type="date" name="custom_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            <small class="text-subtle opacity-75">Forgot yesterday? Change date above!</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-subtle">Notes / Remarks (Optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Paid via PhonePe, Cash given to auto driver">
                    </div>

                    <button type="submit" class="btn <?= $type === 'income' ? 'btn-success' : 'btn-danger' ?> w-100 py-2">
                        <i class="fa-solid fa-check me-1"></i> Save <?= ucfirst($type) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>