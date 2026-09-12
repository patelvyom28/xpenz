<?php
session_start();
require_once '../config/db.php';
require_once 'send_otp_helper.php';

$error = '';
$success = '';

// Step 1: Send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email_or_phone = trim($_POST['email_or_phone']);

    if (!empty($email_or_phone)) {
        $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE email = :email OR phone = :phone");
        $stmt->execute([':email' => $email_or_phone, ':phone' => $email_or_phone]);
        $user = $stmt->fetch();

        if ($user) {
            $otp = rand(100000, 999999);
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_otp'] = $otp;
            $_SESSION['reset_otp_expiry'] = time() + 300; // 5 mins validity

            // Trigger Email & SMS Delivery
            sendEmailOTP($user['email'], $user['name'], $otp);
            sendMobileSMSOTP($user['phone'], $otp);

            $success = "OTP code has been sent to your registered Email and Mobile!";
        } else {
            $error = "No account found with this Email or Mobile Number.";
        }
    } else {
        $error = "Please enter your Email or Mobile number.";
    }
}

// Step 2: Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $entered_otp = trim($_POST['otp']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!isset($_SESSION['reset_otp']) || time() > $_SESSION['reset_otp_expiry']) {
        $error = "OTP expired. Please try requesting a new OTP.";
    } elseif ($entered_otp != $_SESSION['reset_otp']) {
        $error = "Invalid OTP code. Please check and try again.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $user_id = $_SESSION['reset_user_id'];

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        if ($stmt->execute([':password' => $hashed_password, ':id' => $user_id])) {
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_otp_expiry']);

            $_SESSION['msg'] = "Password updated successfully! Please login with your new password.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Failed to update password. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center vh-100">

<div class="container" style="max-width: 420px;">
    <div class="card card-custom p-4 shadow-lg">
        <div class="text-center mb-3">
            <img src="../assets/images/logo.png" alt="XPenz Logo" style="height: 50px;" class="mb-2">
            <h4 class="fw-bold m-0">Reset Password</h4>
            <p class="text-subtle small">Recover your XPenz account access</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?= $success ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['reset_otp'])): ?>
            <!-- Form 1: Request OTP -->
            <form method="POST">
                <input type="hidden" name="action" value="send_otp">
                <div class="mb-3">
                    <label class="form-label text-subtle small">Registered Email or Mobile</label>
                    <input type="text" name="email_or_phone" class="form-control" required placeholder="Email or 10-digit Mobile">
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold mb-3"><i class="fa-solid fa-paper-plane me-1"></i> Send Reset OTP</button>
            </form>
        <?php else: ?>
            <!-- Form 2: Enter OTP & New Password -->
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <div class="mb-3">
                    <label class="form-label text-subtle small">Enter 6-Digit Reset OTP</label>
                    <input type="text" name="otp" class="form-control text-center fw-bold" placeholder="123456" maxlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-subtle small">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-subtle small">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-success w-100 fw-bold mb-3"><i class="fa-solid fa-key me-1"></i> Update Password</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-2">
            <a href="login.php" class="text-primary text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i> Back to Login</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>