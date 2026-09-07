<?php include 'includes/header.php'; ?>

<!-- Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card bg-income p-4 text-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold opacity-75 small">Total Family Income</span>
                <i class="fa-solid fa-arrow-down-left text-white-50 fs-4"></i>
            </div>
            <h2 class="fw-bold mb-0">₹ 45,000.00</h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card bg-expense p-4 text-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold opacity-75 small">Total Monthly Expenses</span>
                <i class="fa-solid fa-arrow-up-right text-white-50 fs-4"></i>
            </div>
            <h2 class="fw-bold mb-0">₹ 18,500.00</h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card bg-balance p-4 text-white shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-uppercase fw-semibold opacity-75 small">Net Available Balance</span>
                <i class="fa-solid fa-wallet text-white-50 fs-4"></i>
            </div>
            <h2 class="fw-bold mb-0">₹ 26,500.00</h2>
        </div>
    </div>
</div>

<!-- Quick Action Buttons -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <button class="btn action-btn w-100 text-center">
            <i class="fa-solid fa-circle-plus text-success fa-2x mb-2 d-block"></i>
            <span class="fw-semibold">Add Income</span>
        </button>
    </div>
    <div class="col-md-3 col-6">
        <button class="btn action-btn w-100 text-center">
            <i class="fa-solid fa-circle-minus text-danger fa-2x mb-2 d-block"></i>
            <span class="fw-semibold">Add Expense</span>
        </button>
    </div>
    <div class="col-md-3 col-6">
        <button class="btn action-btn w-100 text-center">
            <i class="fa-solid fa-clock-rotate-left text-warning fa-2x mb-2 d-block"></i>
            <span class="fw-semibold">EMI Tracker</span>
        </button>
    </div>
    <div class="col-md-3 col-6">
        <button class="btn action-btn w-100 text-center">
            <i class="fa-solid fa-chart-pie text-info fa-2x mb-2 d-block"></i>
            <span class="fw-semibold">Reports</span>
        </button>
    </div>
</div>

<?php include 'includes/footer.php'; ?>