<?php
// Set session cookie lifetime to 30 days
ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params(2592000);

session_start();
require_once '../config/db.php';
require_once 'send_otp_helper.php'; // Include Helper for Real Email & SMS OTP

// If already logged in, redirect directly to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../home.php");
    exit();
}

$error = '';
$success = '';

// Check Session Flash Messages
if (isset($_SESSION['msg'])) {
    $success = $_SESSION['msg'];
    unset($_SESSION['msg']);
    unset($_SESSION['msg_type']);
}

// 1. Password Login Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'password') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: ../home.php");
            exit();
        } else {
            $error = "Invalid email address or password.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// 2. OTP Request Handler (Send OTP via PHPMailer & Fast2SMS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'send_otp') {
    $email_or_phone = trim($_POST['email_or_phone']);

    if (!empty($email_or_phone)) {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, password FROM users WHERE email = :email OR phone = :phone");
        $stmt->execute([':email' => $email_or_phone, ':phone' => $email_or_phone]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = rand(100000, 999999);
            $_SESSION['otp_auth_user_id'] = $user['id'];
            $_SESSION['otp_code'] = $otp;
            $_SESSION['otp_expiry'] = time() + 300; // Valid for 5 minutes

            // Trigger Real Email and Mobile SMS
            $emailSent = sendEmailOTP($user['email'], $user['name'], $otp);
            $smsSent   = sendMobileSMSOTP($user['phone'], $otp);

            $success = "OTP code has been sent directly to your registered Email and Mobile Number!";
        } else {
            $error = "No registered account found with this Email/Mobile.";
        }
    } else {
        $error = "Please enter your registered Email or Mobile number.";
    }
}

// 3. OTP Verification Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type']) && $_POST['login_type'] === 'verify_otp') {
    $entered_otp = trim($_POST['otp']);

    if (!empty($entered_otp) && isset($_SESSION['otp_code'])) {
        if (time() > $_SESSION['otp_expiry']) {
            $error = "OTP expired. Please request a new one.";
        } elseif ($entered_otp == $_SESSION['otp_code']) {
            $user_id = $_SESSION['otp_auth_user_id'];
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch();

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            unset($_SESSION['otp_auth_user_id']);
            unset($_SESSION['otp_code']);
            unset($_SESSION['otp_expiry']);

            header("Location: ../home.php");
            exit();
        } else {
            $error = "Invalid OTP code entered.";
        }
    } else {
        $error = "Please enter the OTP sent to your device.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPenz — Login</title>
    
    <!-- PWA Manifest & App Icons -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#0d1117">
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <!-- CSS Links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="../assets/js/theme.js"></script>
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

<div class="container" style="max-width: 420px;">
    <div class="card card-custom p-4 shadow-lg">
        <div class="text-center mb-3">
            <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 50px;" class="mb-2">
            <h4 class="fw-bold m-0">Welcome Back</h4>
            <p class="text-subtle small">Login to your XPenz account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small" role="alert">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <!-- Login Tabs (Password vs OTP) -->
        <ul class="nav nav-pills nav-fill mb-3" id="loginTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active btn-sm" id="password-tab" data-bs-toggle="pill" data-bs-target="#password-login" type="button"><i class="fa-solid fa-lock me-1"></i> Password</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm" id="otp-tab" data-bs-toggle="pill" data-bs-target="#otp-login" type="button"><i class="fa-solid fa-key me-1"></i> OTP Login</button>
            </li>
        </ul>

        <div class="tab-content" id="loginTabsContent">
            <!-- Password Login Tab -->
            <div class="tab-pane fade show active" id="password-login" role="tabpanel">
                <form method="POST" action="login.php">
                    <input type="hidden" name="login_type" value="password">
                    <div class="mb-3">
                        <label class="form-label text-subtle small">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label text-subtle small">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="text-end mb-3">
                        <a href="forgot_password.php" class="text-primary text-decoration-none small"><i class="fa-solid fa-circle-question me-1"></i>Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold mb-3">Login</button>
                </form>
            </div>

            <!-- OTP Login Tab -->
            <div class="tab-pane fade" id="otp-login" role="tabpanel">
                <?php if (!isset($_SESSION['otp_code'])): ?>
                    <form method="POST" action="login.php">
                        <input type="hidden" name="login_type" value="send_otp">
                        <div class="mb-3">
                            <label class="form-label text-subtle small">Email or Mobile Number</label>
                            <input type="text" name="email_or_phone" class="form-control" placeholder="Enter Email or 10-digit Phone" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold mb-3"><i class="fa-solid fa-paper-plane me-1"></i> Send OTP Code</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="login.php">
                        <input type="hidden" name="login_type" value="verify_otp">
                        <div class="mb-3">
                            <label class="form-label text-subtle small">Enter 6-Digit OTP</label>
                            <input type="text" name="otp" class="form-control text-center letter-spacing-2 fw-bold" placeholder="123456" maxlength="6" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold mb-2">Verify & Login</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center mt-2">
            <small class="text-subtle">Don't have an account? <a href="register.php" class="text-primary text-decoration-none fw-semibold">Register</a></small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>