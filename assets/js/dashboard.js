document.addEventListener("DOMContentLoaded", function () {
    // 1. Expense Breakdown (Doughnut Chart)
    const ctx1 = document.getElementById('expenseChart');
    if (ctx1) {
        new Chart(ctx1.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: window.chartLabels || [],
                datasets: [{
                    data: window.chartValues || [],
                    backgroundColor: ['#dc3545', '#ffc107', '#0dcaf0', '#6c757d', '#0d6efd', '#20c997'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#ffffff' } }
                }
            }
        });
    }

    // 2. Income vs Expense Overview (Bar Chart)
    const ctx2 = document.getElementById('compareChart');
    if (ctx2) {
        new Chart(ctx2.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Overview'],
                datasets: [
                    {
                        label: 'Income',
                        data: [window.totalIncome || 0],
                        backgroundColor: '#198754'
                    },
                    {
                        label: 'Expense',
                        data: [window.totalExpense || 0],
                        backgroundColor: '#dc3545'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { color: '#ffffff' } },
                    y: { ticks: { color: '#ffffff' } }
                },
                plugins: {
                    legend: { labels: { color: '#ffffff' } }
                }
            }
        });
    }
});