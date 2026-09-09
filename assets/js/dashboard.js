document.addEventListener("DOMContentLoaded", function () {
    // 1. Expense Breakdown Doughnut Chart
    const ctxExpense = document.getElementById('expenseChart');
    if (ctxExpense) {
        new Chart(ctxExpense, {
            type: 'doughnut',
            data: {
                labels: window.chartLabels || [],
                datasets: [{
                    data: window.chartValues || [],
                    backgroundColor: [
                        '#ff4d4d', '#ffbc00', '#20c997', 
                        '#0dcaf0', '#6c757d', '#0d6efd', 
                        '#6f42c1', '#fd7e14'
                    ],
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right', // Legend moved to right side to avoid crowding
                        labels: {
                            color: '#cbd5e1',
                            font: { size: 12 },
                            padding: 12,
                            boxWidth: 12
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }

    // 2. Income vs Expense Bar Chart
    const ctxCompare = document.getElementById('compareChart');
    if (ctxCompare) {
        new Chart(ctxCompare, {
            type: 'bar',
            data: {
                labels: ['Overview'],
                datasets: [
                    {
                        label: 'Income',
                        data: [window.totalIncome || 0],
                        backgroundColor: '#198754',
                        borderRadius: 6,
                        barThickness: 45 // Reduced bar thickness for cleaner UI
                    },
                    {
                        label: 'Expense',
                        data: [window.totalExpense || 0],
                        backgroundColor: '#dc3545',
                        borderRadius: 6,
                        barThickness: 45
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: '#cbd5e1' }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#cbd5e1' }
                    },
                    y: {
                        grid: { color: '#334155' },
                        ticks: { color: '#cbd5e1' },
                        beginAtZero: true
                    }
                }
            }
        });
    }
});