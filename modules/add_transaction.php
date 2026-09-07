<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id          = $_SESSION['user_id'];
    $type             = $_POST['type'];
    $amount           = $_POST['amount'];
    $category         = trim($_POST['category']);
    $transaction_date = $_POST['transaction_date'];
    $description      = trim($_POST['description']);

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, category, amount, transaction_date, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $category, $amount, $transaction_date, $description]);

    header("Location: ../home.php");
    exit;
}
?>