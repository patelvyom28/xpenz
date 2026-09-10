<?php
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/sms_helper.php';

echo "=== Running Daily Multi-Channel Reminder Cron Job [" . date('Y-m-d H:i:s') . "] ===<br>";

// 1. Check Subscriptions Due Today
try {
    $sub_stmt = $pdo->prepare("
        SELECT s.*, u.phone, u.name 
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        WHERE DATE(s.due_date) = CURDATE()
    ");
    $sub_stmt->execute();
    $subscriptions = $sub_stmt->fetchAll();

    foreach ($subscriptions as $sub) {
        $phone = !empty($sub['phone']) ? $sub['phone'] : 'Guest Mobile';
        $msg = "Hello {$sub['name']}, your subscription '{$sub['title']}' of ₹{$sub['amount']} is due today ({$sub['due_date']}). - XPenz Tracker";
        sendSMSNotification($phone, $msg);
        echo "SMS Sent for Subscription: " . htmlspecialchars($sub['title']) . "<br>";
    }
} catch (PDOException $e) {
    echo "Subscription Check Skipped: " . $e->getMessage() . "<br>";
}

// 2. Check Loan / EMI Installments Due Today (Safe Check)
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'loans'")->rowCount();
    if ($table_check > 0) {
        $loan_stmt = $pdo->prepare("
            SELECT l.*, u.phone, u.name 
            FROM loans l 
            JOIN users u ON l.user_id = u.id 
            WHERE DATE(l.next_emi_date) = CURDATE()
        ");
        $loan_stmt->execute();
        $loans = $loan_stmt->fetchAll();

        foreach ($loans as $loan) {
            $phone = !empty($loan['phone']) ? $loan['phone'] : 'Guest Mobile';
            $msg = "Reminder: Your Loan EMI '{$loan['loan_name']}' of ₹{$loan['emi_amount']} is due today. - XPenz Tracker";
            sendSMSNotification($phone, $msg);
            echo "SMS Sent for EMI: " . htmlspecialchars($loan['loan_name']) . "<br>";
        }
    } else {
        echo "Loans table not present — Skipped EMI checks.<br>";
    }
} catch (PDOException $e) {
    echo "Loan Check Skipped: " . $e->getMessage() . "<br>";
}

echo "=== Cron Job Finished Successfully ===";
?>