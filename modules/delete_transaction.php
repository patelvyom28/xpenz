<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['id'])) {
    $transaction_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Security check: Delete only if the transaction belongs to logged in user
    $query = "DELETE FROM transactions WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($query);
    
    if ($stmt->execute([':id' => $transaction_id, ':user_id' => $user_id])) {
        $_SESSION['msg'] = "Transaction deleted successfully!";
        $_SESSION['msg_type'] = "danger";
    }
}

header("Location: transactions.php");
exit();