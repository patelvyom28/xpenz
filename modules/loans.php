<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Delete Record
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $del_stmt = $pdo->prepare("DELETE FROM loans_insurance WHERE id = :id AND user_id = :user_id");
    $del_stmt->execute([':id' => $del_id, ':user_id' => $user_id]);
    $_SESSION['msg'] = "Record deleted successfully!";
    $_SESSION['msg_type'] = "success";
    header("Location: loans.php");
    exit();
}

// Pay Installment Logic -> Reduce Balance & Sync with Expense Transactions
if (isset($_GET['pay_id'])) {
    $pay_id = $_GET['pay_id'];
    
    // Fetch loan details
    $stmt = $pdo->prepare("SELECT title, monthly_installment, type FROM loans_insurance WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $pay_id, ':user_id' => $user_id]);
    $item = $stmt->fetch();

    if ($item) {
        $amount = $item['monthly_installment'];
        $desc = "Paid " . strtoupper($item['type']) . " Installment: " . $item['title'];

        // 1. Update Paid Amount in Loans Table
        $u_stmt = $pdo->prepare("UPDATE loans_insurance SET paid_amount = paid_amount + :amt WHERE id = :id AND user_id = :user_id");
        $u_stmt->execute([':amt' => $amount, ':id' => $pay_id, ':user_id' => $user_id]);

        // 2. Insert into transactions table for dashboard chart & expense sync
        $t_stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, amount, category, type) VALUES (:user_id, :desc, :amount, 'EMI & Loans', 'expense')");
        $t_stmt->execute([
            ':user_id' => $user_id,
            ':desc' => $desc,
            ':amount' => $amount
        ]);

        $_SESSION['msg'] = "Installment paid! Remaining balance updated and recorded in Dashboard.";
        $_SESSION['msg_type'] = "success";
        header("Location: loans.php");
        exit();
    }
}

// Add New Loan/Insurance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_portfolio'])) {
    $title = trim($_POST['title']);
    $type = $_POST['type'];
    $total_amount = trim($_POST['total_amount']);
    $monthly_installment = trim($_POST['monthly_installment']);
    $due_day = trim($_POST['due_day']);
    $lender_provider = trim($_POST['lender_provider']);

    if (empty($title) || empty($total_amount) || empty($monthly_installment) || empty($due_day)) {
        $error = "Please fill in all required fields.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO loans_insurance (user_id, title, type, total_amount, paid_amount, monthly_installment, due_day, lender_provider) VALUES (:user_id, :title, :type, :total_amount, 0.00, :monthly_installment, :due_day, :lender_provider)");
        $stmt->execute([
            ':user_id' => $user_id,
            ':title' => $title,
            ':type' => $type,
            ':total_amount' => $total_amount,
            ':monthly_installment' => $monthly_installment,
            ':due_day' => $due_day,
            ':lender_provider' => $lender_provider
        ]);
        $success = "Portfolio item added successfully!";
    }
}

// Fetch Records
$stmt = $pdo->prepare("SELECT * FROM loans_insurance WHERE user_id = :user_id ORDER BY due_day ASC");
$stmt->execute([':user_id' => $user_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans & Insurance Portfolio - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-building-columns me-2 text-primary"></i>Loans & Insurance Portfolio</h2>
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
        <div class="col-md-4">
            <div class="card card-custom p-3">
                <h5 class="text-white mb-3">Add Loan / Insurance / EMI</h5>
                <form method="POST">
                    <input type="hidden" name="add_portfolio" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="loan">Car / Home Loan</option>
                            <option value="insurance">Health / Term Insurance</option>
                            <option value="emi">Product EMI (Gadgets/Appliances)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Title / Item Name</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Brezza Loan, Health Policy" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Provider / Bank Name</label>
                        <input type="text" name="lender_provider" class="form-control" placeholder="e.g. HDFC Bank, LIC">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Total Amount / Cover (₹)</label>
                        <input type="number" step="0.01" name="total_amount" class="form-control" placeholder="500000" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Monthly Installment / Premium (₹)</label>
                        <input type="number" step="0.01" name="monthly_installment" class="form-control" placeholder="12000" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Monthly Due Day (1-31)</label>
                        <input type="number" min="1" max="31" name="due_day" class="form-control" placeholder="5" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i> Add Record</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card card-custom p-3">
                <h5 class="text-white mb-3">Active Liabilities & Policies</h5>
                <div class="table-responsive">
                    <table class="table table-dark-custom table-hover align-middle m-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Remaining / Total</th>
                                <th>Due Day</th>
                                <th>Installment</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($items) > 0): ?>
                                <?php foreach ($items as $item): 
                                    $remaining = max(0, $item['total_amount'] - $item['paid_amount']);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($item['title']) ?></div>
                                            <small class="text-subtle"><?= htmlspecialchars($item['lender_provider'] ?: 'N/A') ?></small>
                                        </td>
                                        <td><span class="badge bg-warning text-dark"><?= strtoupper($item['type']) ?></span></td>
                                        <td>
                                            <div class="text-white fw-bold">₹<?= number_format($remaining, 2) ?></div>
                                            <small class="text-subtle">of ₹<?= number_format($item['total_amount'], 2) ?></small>
                                        </td>
                                        <td>Every <?= $item['due_day'] ?>th</td>
                                        <td class="text-danger fw-bold">₹<?= number_format($item['monthly_installment'], 2) ?></td>
                                        <td class="text-center">
                                            <?php if ($remaining > 0): ?>
                                                <a href="loans.php?pay_id=<?= $item['id']; ?>" class="btn btn-sm btn-success me-1" title="Pay Monthly Installment" onclick="return confirm('Record this monthly installment as expense and reduce remaining balance?');">
                                                    <i class="fa-solid fa-credit-card me-1"></i> Pay
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-success me-1"><i class="fa-solid fa-check me-1"></i> Fully Paid</span>
                                            <?php endif; ?>
                                            <a href="loans.php?delete_id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this item?');" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No loans or insurance records added yet.</td>
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