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
    $name             = trim($_POST['name']);
    $email            = trim($_POST['email']);
    $phone            = trim($_POST['phone']);
    $password         = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Server-Side Validations
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "All fields including Confirm Password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address format.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Mobile number must be exactly 10 digits.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email or phone already exists in DB
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $stmt->execute([':email' => $email, ':phone' => $phone]);

        if ($stmt->fetch()) {
            $error = "Email or Mobile Number is already registered!";
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
    
    <!-- Dynamic Theme Switcher Script -->
    <script src="../assets/js/theme.js"></script>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

<div class="container" style="max-width: 420px;">
    <div class="card card-custom p-4 shadow-lg">
        <div class="text-center mb-3">
            <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 50px;" class="mb-2">
            <h4 class="fw-bold m-0">Create Account</h4>
            <p class="text-subtle small">Join XPenz Smart Budget Tracker</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" onsubmit="return validateRegisterForm()">
            <div class="mb-3">
                <label class="form-label text-subtle small">Full Name</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Your Name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="name@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Mobile Number</label>
                <input type="tel" name="phone" id="phone" class="form-control" placeholder="10-digit number" maxlength="10" pattern="[0-9]{10}" title="Ten digit mobile number" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="mb-3">
                <label class="form-label text-subtle small">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                <div id="passError" class="text-danger small mt-1 d-none"><i class="fa-solid fa-circle-exclamation me-1"></i> Passwords do not match!</div>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold mb-3">Register Account</button>
        </form>

        <div class="text-center">
            <small class="text-subtle">Already have an account? <a href="login.php" class="text-primary text-decoration-none fw-semibold">Login</a></small>
        </div>
    </div>
</div>

<script>
function validateRegisterForm() {
    const pass = document.getElementById('password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    const phone = document.getElementById('phone').value;
    const errDiv = document.getElementById('passError');

    // Phone validation
    if (!/^[0-9]{10}$/.test(phone)) {
        alert("Please enter a valid 10-digit mobile number.");
        return false;
    }

    // Password match validation
    if (pass !== confirmPass) {
        errDiv.classList.remove('d-none');
        return false;
    }
    
    errDiv.classList.add('d-none');
    return true;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>