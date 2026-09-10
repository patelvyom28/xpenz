document.addEventListener("DOMContentLoaded", function () {
    // Render Expense Breakdown Doughnut Chart
    const ctxExpense = document.getElementById('expenseChart');
    if (ctxExpense && window.chartLabels && window.chartValues) {
        new Chart(ctxExpense, {
            type: 'doughnut',
            data: {
                labels: window.chartLabels,
                datasets: [{
                    data: window.chartValues,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#f8f9fc'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#e6edf3', font: { size: 11 } }
                    }
                }
            }
        });
    }

    // Render Income vs Expense Bar Chart
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
                        backgroundColor: '#1cc88a',
                        borderRadius: 6
                    },
                    {
                        label: 'Expense',
                        data: [window.totalExpense || 0],
                        backgroundColor: '#e74a3b',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { ticks: { color: '#858796' }, grid: { display: false } },
                    y: { ticks: { color: '#858796' }, grid: { color: '#373e47' } }
                },
                plugins: {
                    legend: { labels: { color: '#e6edf3' } }
                }
            }
        });
    }
});

// Dynamic Alert Trigger System (Visual Toast + Desktop Push)
function requestNotification() {
    const alertMsg = window.reminderText || "Remember to log your Cash & Online wallet transactions!";
    
    // 1. In-App Visual Alert Modal/Toast
    const toastHTML = `
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
            <div id="smartToast" class="toast show bg-dark text-white border-info shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-info text-dark fw-bold">
                    <i class="fa-solid fa-bell me-2"></i> XPenz Smart Reminder
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${alertMsg}
                </div>
            </div>
        </div>
    `;
    
    // Insert Toast to DOM
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    container.innerHTML = toastHTML;

    // Update Button State
    const btn = document.getElementById('enableNotifyBtn');
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Alert Triggered!';
        btn.classList.replace('btn-outline-info', 'btn-success');
    }

    // 2. Try Browser Push Notification
    if ("Notification" in window) {
        if (Notification.permission === "granted") {
            new Notification("XPenz Smart Alert", {
                body: alertMsg,
                icon: "assets/images/logo.png"
            });
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    new Notification("XPenz Smart Alert", {
                        body: alertMsg,
                        icon: "assets/images/logo.png"
                    });
                }
            });
        }
    }
}

// Automatically trigger smart toast on dashboard load
window.addEventListener('DOMContentLoaded', () => {
    setTimeout(requestNotification, 1000); // Popup opens automatically after 1 second
});

// Register PWA Service Worker for Mobile/Desktop Installation
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./service-worker.js')
            .then(reg => console.log('PWA Service Worker Registered successfully!', reg))
            .catch(err => console.error('PWA Service Worker Registration Failed:', err));
    });
}