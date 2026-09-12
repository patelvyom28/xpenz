document.addEventListener("DOMContentLoaded", function () {
    // --- 1. Smart Expense Toast Logic (Max 3 Times / Day) ---
    const maxShowsPerDay = 3;
    const today = new Date().toISOString().split('T')[0];
    let alertData = JSON.parse(localStorage.getItem('xpenz_alert_tracker')) || { date: '', count: 0 };

    if (alertData.date !== today) {
        alertData = { date: today, count: 0 };
    }

    const popup = document.getElementById('smartExpenseToast');
    if (popup && alertData.count < maxShowsPerDay) {
        setTimeout(() => {
            popup.classList.add('show');
            alertData.count += 1;
            localStorage.setItem('xpenz_alert_tracker', JSON.stringify(alertData));
        }, 1000);

        setTimeout(() => {
            dismissToast();
        }, 8000);
    }

    // --- 2. Chart.js Permanent Dark Theme Color Palette ---
    const textColor = '#e6edf3';
    const gridColor = '#30363d';

    // Expense Pie Chart
    const expenseCtx = document.getElementById('expenseChart');
    if (expenseCtx && window.chartLabels && window.chartLabels.length > 0) {
        new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: window.chartLabels,
                datasets: [{
                    data: window.chartValues,
                    backgroundColor: ['#f85149', '#06b6d4', '#eab308', '#a855f7', '#3b82f6', '#22c55e'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: textColor } }
                }
            }
        });
    }

    // Income vs Expense Bar Chart
    const compareCtx = document.getElementById('compareChart');
    if (compareCtx) {
        new Chart(compareCtx, {
            type: 'bar',
            data: {
                labels: ['Income', 'Expense'],
                datasets: [{
                    label: 'Amount (₹)',
                    data: [window.totalIncome || 0, window.totalExpense || 0],
                    backgroundColor: ['#22c55e', '#ef4444'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { color: textColor }, grid: { display: false } },
                    y: { ticks: { color: textColor }, grid: { color: gridColor } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});

function dismissToast() {
    const popup = document.getElementById('smartExpenseToast');
    if (popup) {
        popup.classList.remove('show');
    }
}