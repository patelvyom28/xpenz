<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

require_once 'config/db.php';
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Calculate Total Income
$stmt_income = $pdo->prepare("SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = ? AND type = 'income'");
$stmt_income->execute([$user_id]);
$income_data = $stmt_income->fetch();
$total_income = $income_data['total_income'] ?? 0;

// Calculate Total Expenses
$stmt_expense = $pdo->prepare("SELECT SUM(amount) AS total_expense FROM transactions WHERE user_id = ? AND type = 'expense'");
$stmt_expense->execute([$user_id]);
$expense_data = $stmt_expense->fetch();
$total_expense = $expense_data['total_expense'] ?? 0;

// Net Balance
$net_balance = $total_income - $total_expense;

include 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-white mb-1">Welcome back, <?= htmlspecialchars($user_name); ?>! 👋</h3>
        <p class="text-secondary small mb-0">Here is your real-time financial summary.</p>
    </div>
    <a href="auth/logout.php" class="btn btn-outline-danger btn-sm rounded-3">
        <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
    </a>
</div>

<!-- Dynamic Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card bg-income p-4 text-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold opacity-75 small">Total Income</span>
                <i class="fa-solid fa-arrow-down-left text-white-50 fs-4"></i>
            </div>
            <h2 class="fw-bold mb-0">₹ <?= number_format($total_income, 2); ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card bg-expense p-4 text-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold opacity-75 small">Total Expenses</span>
                <i class="fa-solid fa-arrow-up-right text-white-50 fs-4"></i>
            </div>
            <h2 class="fw-bold mb-0">₹ <?= number_format($total_expense, 2); ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card bg-balance p-4 text-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold opacity-75 small">Net Available Balance</span>
                <i class="fa-solid fa-wallet text-white-50 fs-4"></i>
            </div>
            <h2 class="fw-bold mb-0">₹ <?= number_format($net_balance, 2); ?></h2>
        </div>
    </div>
</div>

<!-- Quick Action Buttons -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <button class="btn action-btn w-100 text-center" data-bs-toggle="modal" data-bs-target="#incomeModal">
            <i class="fa-solid fa-circle-plus text-success fa-2x mb-2 d-block"></i>
            <span class="fw-semibold">Add Income</span>
        </button>
    </div>
    <div class="col-md-6">
        <button class="btn action-btn w-100 text-center" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="fa-solid fa-circle-minus text-danger fa-2x mb-2 d-block"></i>
            <span class="fw-semibold">Add Expense</span>
        </button>
    </div>
</div>

<!-- Add Income Modal -->
<div class="modal fade" id="incomeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-plus-circle me-2"></i>Add Income</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="modules/add_transaction.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="type" value="income">
                    <div class="mb-3">
                        <label class="form-label text-light">Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" class="form-control text-white" required placeholder="e.g. 5000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-light">Category</label>
                        <input type="text" name="category" class="form-control text-white" required placeholder="e.g. Salary, Pocket Money, Bonus">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-light">Date</label>
                        <input type="date" name="transaction_date" class="form-control text-white" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-light">Description (Optional)</label>
                        <textarea name="description" class="form-control text-white" rows="2" placeholder="e.g. Monthly stipend"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="submit" class="btn btn-success w-100 fw-bold">Save Income</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-danger"><i class="fa-solid fa-minus-circle me-2"></i>Add Expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="modules/add_transaction.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="type" value="expense">
                    <div class="mb-3">
                        <label class="form-label text-light">Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" class="form-control text-white" required placeholder="e.g. 250">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-light">Category</label>
                        <input type="text" name="category" class="form-control text-white" required placeholder="e.g. Food, Fuel, Shopping, Bills">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-light">Date</label>
                        <input type="date" name="transaction_date" class="form-control text-white" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-light">Description (Optional)</label>
                        <textarea name="description" class="form-control text-white" rows="2" placeholder="e.g. Dinner with friends"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="submit" class="btn btn-danger w-100 fw-bold">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>