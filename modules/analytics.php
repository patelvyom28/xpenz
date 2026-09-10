<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Monthly Income vs Expense Trend (Current Year)
$trend_stmt = $pdo->prepare("
    SELECT 
        MONTH(created_at) as month_num,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
    FROM transactions 
    WHERE user_id = :user_id AND YEAR(created_at) = YEAR(CURRENT_DATE())
    GROUP BY MONTH(created_at)
    ORDER BY month_num ASC
");
$trend_stmt->execute([':user_id' => $user_id]);
$raw_trends = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize 12 Months
$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$income_data = array_fill(0, 12, 0);
$expense_data = array_fill(0, 12, 0);

foreach ($raw_trends as $row) {
    $idx = (int)$row['month_num'] - 1;
    $income_data[$idx] = (float)$row['total_income'];
    $expense_data[$idx] = (float)$row['total_expense'];
}

// Cash Flow Projection (Next 3 Months Based on Average)
$avg_income = count(array_filter($income_data)) > 0 ? array_sum($income_data) / count(array_filter($income_data)) : 0;
$avg_expense = count(array_filter($expense_data)) > 0 ? array_sum($expense_data) / count(array_filter($expense_data)) : 0;

$projection_labels = array_slice($months, (int)date('n') - 1, 3);
if (count($projection_labels) < 3) {
    $projection_labels = array_merge($projection_labels, array_slice($months, 0, 3 - count($projection_labels)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visual Analytics - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-chart-line me-2 text-primary"></i>Visual Analytics & Cash Flow Projections</h2>
        <a href="../home.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Dashboard</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card card-custom p-3 h-100">
                <h5 class="text-white mb-3"><i class="fa-solid fa-chart-area me-2 text-info"></i>Annual Comparative Trends (<?= date('Y') ?>)</h5>
                <div style="height: 300px;">
                    <canvas id="annualTrendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-3 h-100">
                <h5 class="text-white mb-3"><i class="fa-solid fa-wand-magic-sparkles me-2 text-warning"></i>Smart Run-Rate Projections</h5>
                <div class="p-2">
                    <small class="text-subtle d-block">Monthly Avg Income</small>
                    <h4 class="text-success fw-bold">₹<?= number_format($avg_income, 2) ?></h4>
                    <hr class="border-secondary">
                    <small class="text-subtle d-block">Monthly Avg Expense</small>
                    <h4 class="text-danger fw-bold">₹<?= number_format($avg_expense, 2) ?></h4>
                    <hr class="border-secondary">
                    <small class="text-subtle d-block">Projected Monthly Savings</small>
                    <h4 class="text-primary fw-bold">₹<?= number_format($avg_income - $avg_expense, 2) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-custom p-3">
        <h5 class="text-white mb-3"><i class="fa-solid fa-chart-bar me-2 text-success"></i>Next 3-Month Cash Flow Projection</h5>
        <div style="height: 250px;">
            <canvas id="cashFlowChart"></canvas>
        </div>
    </div>
</div>

<script>
    const months = <?= json_encode($months) ?>;
    const incomeData = <?= json_encode($income_data) ?>;
    const expenseData = <?= json_encode($expense_data) ?>;

    // Annual Trend Chart
    new Chart(document.getElementById('annualTrendChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                { label: 'Income', data: incomeData, borderColor: '#198754', backgroundColor: 'rgba(25, 135, 84, 0.2)', fill: true, tension: 0.3 },
                { label: 'Expense', data: expenseData, borderColor: '#dc3545', backgroundColor: 'rgba(220, 53, 69, 0.2)', fill: true, tension: 0.3 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // Cash Flow Chart
    new Chart(document.getElementById('cashFlowChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($projection_labels) ?>,
            datasets: [
                { label: 'Est. Income', data: [<?= $avg_income ?>, <?= $avg_income ?>, <?= $avg_income ?>], backgroundColor: '#198754' },
                { label: 'Est. Expense', data: [<?= $avg_expense ?>, <?= $avg_expense ?>, <?= $avg_expense ?>], backgroundColor: '#dc3545' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
</script>
</body>
</html>
