<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPenz — Smart Expense Tracker</title>
    
    <!-- PWA Manifest & App Icons -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d1117">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">

    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .splash-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: #0d1117;
        }

        .splash-logo {
            width: 100px;
            height: auto;
            animation: pulse 1.5s infinite ease-in-out;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
    </style>
</head>
<body class="bg-dark text-white">

<div class="splash-container text-center">
    <img src="assets/images/logo.png" alt="XPenz Logo" class="splash-logo mb-3">
    <h3 class="fw-bold text-white mb-1">XPenz</h3>
    <p class="text-subtle small mb-4">Next-Gen Expense & Budget Tracker</p>
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
</div>

<script>
    // Direct Redirect Logic (No External File Dependency Error)
    setTimeout(() => {
        window.location.href = 'home.php';
    }, 1200);

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('./service-worker.js')
                .then(reg => console.log('PWA Service Worker Registered!', reg))
                .catch(err => console.error('PWA SW Registration Failed:', err));
        });
    }
</script>
</body>
</html>