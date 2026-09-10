<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Filter Variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Build Dynamic SQL Query
$query = "SELECT * FROM transactions WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if (!empty($search)) {
    $query .= " AND description LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $query .= " AND category = :category";
    $params[':category'] = $category;
}

if (!empty($type)) {
    $query .= " AND type = :type";
    $params[':type'] = $type;
}

if (!empty($from_date)) {
    $query .= " AND DATE(created_at) >= :from_date";
    $params[':from_date'] = $from_date;
}

if (!empty($to_date)) {
    $query .= " AND DATE(created_at) <= :to_date";
    $params[':to_date'] = $to_date;
}

$query .= " ORDER BY created_at DESC";

// CSV Export Handler with Filters
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=xpenz_filtered_report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Description', 'Category', 'Type', 'Amount (INR)']);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            date('d M Y, h:i A', strtotime($row['created_at'])),
            $row['description'],
            $row['category'],
            strtoupper($row['type']),
            $row['amount']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch Filtered Transactions
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Fetch Categories for Dropdown
$cat_stmt = $pdo->prepare("SELECT DISTINCT category FROM transactions WHERE user_id = :user_id");
$cat_stmt->execute([':user_id' => $user_id]);
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; color: #000000 !important; }
            .card-custom { background: none !important; border: none !important; color: #000 !important; }
            .table { color: #000 !important; }
            .table-dark-custom th, .table-dark-custom td { color: #000 !important; border-bottom: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2><i class="fa-solid fa-receipt me-2 text-primary"></i>Transaction History</h2>
        <div class="d-flex gap-2">
            <a href="transactions.php?export=csv&<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel me-1"></i> Export CSV</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print me-1"></i> Print / Save PDF</button>
            <a href="../home.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Dashboard</a>
        </div>
    </div>

    <!-- Filter Bar Card -->
    <div class="card card-custom p-3 mb-4 no-print">
        <form method="GET" action="transactions.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-subtle small">Search Keyword</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="e.g. Salary, Recharge" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-subtle small">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-subtle small">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="income" <?= $type === 'income' ? 'selected' : '' ?>>Income</option>
                    <option value="expense" <?= $type === 'expense' ? 'selected' : '' ?>>Expense</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-subtle small">From Date</label>
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label text-subtle small">To Date</label>
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100" title="Apply Filter"><i class="fa-solid fa-filter"></i></button>
                <a href="transactions.php" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="fa-solid fa-rotate-right"></i></a>
            </div>
        </form>
    </div>

    <!-- Printable Header -->
    <div class="d-none d-print-block mb-4 text-center">
        <h2>XPenz — Financial Report</h2>
        <p>Generated on: <?= date('d M Y, h:i A') ?></p>
        <hr>
    </div>

    <div class="card card-custom p-3">
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th class="text-center no-print">Action</th>
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
                                    <span class="badge <?= $t['type'] === 'income' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst($t['type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="<?= $t['type'] === 'income' ? 'text-success' : 'text-danger' ?>">
                                        <?= $t['type'] === 'income' ? '+' : '-' ?> ₹<?= number_format($t['amount'], 2) ?>
                                    </strong>
                                </td>
                                <td class="text-center no-print">
                                    <a href="edit_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-warning me-1"><i class="fa-solid fa-pen"></i></a>
                                    <a href="delete_transaction.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete transaction?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No transactions matching your filter criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>