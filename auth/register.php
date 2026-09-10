<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../home.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if (!empty($name) && !empty($email) && !empty($phone) && !empty($password)) {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);

        if ($stmt->fetch()) {
            $error = "Email is already registered!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, monthly_budget) VALUES (:name, :email, :phone, :password, 0)");
            
            if ($insertStmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':password' => $hashedPassword])) {
                $_SESSION['msg'] = "Registration successful! Please login.";
                $_SESSION['msg_type'] = "success";
                header("Location: login.php");
                exit();
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    } else {
        $error = "All fields including Mobile Number are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPenz — Register</title>
    
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#0d1117">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

<div class="container" style="max-width: 420px;">
    <div class="card card-custom p-4 shadow-lg">
        <div class="text-center mb-4">
            <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 50px;" class="mb-2">
            <h4 class="text-white fw-bold m-0">Create Account</h4>
            <p class="text-subtle small">Join XPenz Smart Budget Tracker</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label class="form-label text-subtle small">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Vyom Patel" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Mobile Number (For Real SMS Alerts)</label>
                <input type="tel" name="phone" class="form-control" placeholder="e.g. 9712515799" pattern="[0-9]{10}" title="Ten digit mobile number" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold mb-3">Register Account</button>
        </form>

        <div class="text-center">
            <small class="text-subtle">Already have an account? <a href="login.php" class="text-primary text-decoration-none">Login</a></small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>