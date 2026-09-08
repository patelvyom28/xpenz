<?php
session_start();
// Check user session to set correct target page
$targetPage = isset($_SESSION['user_id']) ? 'home.php' : 'auth/login.php';
?>
<!DOCTYPE html>
<html lang="gu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPenz - Next-Gen Smart Expense & Budget Tracker</title>
    <!-- Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            background-color: #0d1117;
            color: white;
            font-family: Arial, sans-serif;
            overflow: hidden;
        }
        #splash-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            opacity: 1;
            transition: opacity 1s ease-out;
        }
        .splash-logo {
            max-width: 300px;
            height: auto;
            margin-bottom: 20px;
        }
        .loader {
            border: 5px solid #1f2937;
            border-top: 5px solid #00f2fe;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div id="splash-container" data-target="<?= $targetPage; ?>">
        <img src="assets/images/logo.png" alt="XPenz Logo" class="splash-logo">
        <div class="loader"></div>
    </div>

    <!-- External Scripts -->
    <script src="assets/js/splash.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>