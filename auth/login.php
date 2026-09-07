<?php
session_start();
require_once '../config/db.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: ../home.php");
        exit;
    } else {
        $message = '<div class="alert alert-danger">Invalid Email or Password!</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Xpenz</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .sub-text { color: #9be2ed !important; }
        .footer-text { color: #8b949e !important; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

    <div class="card p-4 shadow-lg border-0 rounded-4" style="width: 100%; max-width: 420px; background-color: var(--card-bg); border: 1px solid var(--card-border) !important;">
        <h3 class="fw-bold text-center mb-1 text-white">Welcome Back</h3>
        <p class="sub-text text-center small mb-4">Login to your Xpenz account</p>

        <?= $message; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label text-light">Email Address</label>
                <input type="email" name="email" class="form-control bg-dark text-white border-secondary" required placeholder="name@example.com">
            </div>
            <div class="mb-4">
                <label class="form-label text-light">Password</label>
                <input type="password" name="password" class="form-control bg-dark text-white border-secondary" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3">Login</button>
        </form>

        <p class="text-center footer-text small mt-4 mb-0">
            Don't have an account? <a href="register.php" class="text-primary text-decoration-none fw-bold ms-1">Register</a>
        </p>
    </div>

</body>
</html>