<?php
// cron/send_reminders.php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/../config/db.php';

// Include PHPMailer classes manually or via autoload if present
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper function to send Email via PHPMailer
function sendUserEmail($to_email, $to_name, $subject, $body_html) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                     
        $mail->Username   = 'vyompatel996@gmail.com';             
        $mail->Password   = 'gbwkpssijouxbggg';                
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            
        $mail->Port       = 587;                                    

        // Recipients
        $mail->setFrom('vyompatel996@gmail.com', 'XPenz Smart Wallet');
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Helper function to send SMS via Fast2SMS
function sendUserSMS($mobile_number, $message) {
    if (empty($mobile_number) || strlen($mobile_number) < 10) return false;
    
    $apiKey = "y6ZUre3HD8CGjMz5cwSQoNYkdKfvqgPatxJnsR9ET4WIVm0iOFsJ7GuP0iSacVyFhKkCUbwzgMBl8jZI"; 
    $fields = array(
        "sender_id" => "TXTIND",
        "message" => $message,
        "language" => "english",
        "route" => "q",
        "numbers" => $mobile_number,
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => json_encode($fields),
      CURLOPT_HTTPHEADER => array(
        "authorization: " . $apiKey,
        "accept: */*",
        "cache-control: no-cache",
        "content-type: application/json"
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    // Also log to sms_logs.txt for debugging
    $log_entry = "[" . date('Y-m-d H:i:s') . "] To: $mobile_number | Msg: $message | Res: " . ($response ?: $err) . "\n";
    file_put_contents(__DIR__ . '/../sms_logs.txt', $log_entry, FILE_APPEND);

    return $err ? false : true;
}

// ================= FETCH ALL USERS =================
$users_stmt = $pdo->query("SELECT id, name, email, phone FROM users");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $user) {
    $uid = $user['id'];
    $u_name = $user['name'] ?: 'User';
    $u_email = $user['email'];
    $u_phone = $user['phone'] ?? ''; 

    // 1. Check Subscriptions Due Soon
    $sub_stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = :uid AND next_payment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)");
    $sub_stmt->execute([':uid' => $uid]);
    $subscriptions = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subscriptions as $sub) {
        $sub_name = $sub['name'];
        $sub_amt = $sub['amount'];
        $due_date = $sub['next_payment_date'];

        $subject = "⚠️ Subscription Due Reminder: {$sub_name}";
        $html = "<p>Hello <b>{$u_name}</b>,</p><p>Your subscription <b>{$sub_name}</b> of <b>₹{$sub_amt}</b> is due on <b>{$due_date}</b>. Please keep your balance ready.</p><p>- Team XPenz</p>";
        
        if (!empty($u_email)) sendUserEmail($u_email, $u_name, $subject, $html);
        if (!empty($u_phone)) sendUserSMS($u_phone, "XPenz: Your subscription {$sub_name} (Rs.{$sub_amt}) is due on {$due_date}.");
    }

    // 2. Check Loans / EMI Due Soon
    $loan_stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = :uid AND next_emi_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)");
    $loan_stmt->execute([':uid' => $uid]);
    $loans = $loan_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($loans as $loan) {
        $loan_title = $loan['title'];
        $emi_amt = $loan['emi_amount'];
        $emi_date = $loan['next_emi_date'];

        $subject = "🚨 EMI / Loan Payment Reminder: {$loan_title}";
        $html = "<p>Hello <b>{$u_name}</b>,</p><p>Your EMI for <b>{$loan_title}</b> amounting to <b>₹{$emi_amt}</b> is coming up on <b>{$emi_date}</b>.</p><p>- Team XPenz</p>";

        if (!empty($u_email)) sendUserEmail($u_email, $u_name, $subject, $html);
        if (!empty($u_phone)) sendUserSMS($u_phone, "XPenz Alert: EMI of Rs.{$emi_amt} for {$loan_title} is due on {$emi_date}.");
    }

    // 3. Daily Expense Logging Reminder
    $daily_subject = "📝 Daily XPenz Reminder: Record Today's Expenses";
    $daily_html = "<p>Hi <b>{$u_name}</b>,</p><p>Hope you're having a great day! Don't forget to log your daily cash or online expenses and income in your <b>XPenz</b> dashboard.</p><p><a href='http://localhost/xpenz/home.php'>Open Dashboard</a></p><p>- Team XPenz</p>";
    
    if (!empty($u_email)) {
        sendUserEmail($u_email, $u_name, $daily_subject, $daily_html);
    }
    if (!empty($u_phone)) {
        sendUserSMS($u_phone, "XPenz Daily Reminder: Please log your expenses and income for today on your dashboard.");
    }
}

echo "Reminders executed successfully at " . date('Y-m-d H:i:s');
?>