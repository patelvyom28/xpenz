<?php
// Include PHPMailer classes directly without Composer
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send OTP to User's Email Address using Gmail SMTP
 */
function sendEmailOTP($recipientEmail, $recipientName, $otpCode) {
    $mail = new PHPMailer(true);

    try {
        // Gmail SMTP Server Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // SENDER CREDENTIALS
        $mail->Username   = 'vyompatel996@gmail.com';
        $mail->Password   = 'gbwkpssijouxbggg';
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Disable SSL Certificate Verification for Localhost
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Sender & Dynamic User Recipient
        $mail->setFrom('vyompatel996@gmail.com', 'XPenz Smart Tracker');
        $mail->addAddress($recipientEmail, $recipientName);

        // HTML Email Template
        $mail->isHTML(true);
        $mail->Subject = 'XPenz - Your Security OTP Verification Code';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #0d1117; color: #ffffff; border-radius: 10px;'>
                <h2 style='color: #38bdf8;'>XPenz Smart Budget Tracker</h2>
                <p>Hello <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                <p>Your one-time security verification code (OTP) for XPenz is:</p>
                <div style='background-color: #161b22; font-size: 28px; font-weight: bold; letter-spacing: 5px; padding: 15px; text-align: center; color: #10b981; border: 1px solid #30363d; border-radius: 8px; margin: 15px 0;'>
                    " . $otpCode . "
                </div>
                <p style='font-size: 0.85rem; color: #8b949e;'>This OTP is valid for 5 minutes. Please do not share this code with anyone.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send OTP to User's 10-Digit Mobile Number via Fast2SMS API
 */
function sendMobileSMSOTP($mobileNumber, $otpCode) {
    // Fast2SMS API Key Configured
    $apiKey = "y6ZUre3HD8CGjMz5cwSQoNYkdKfvqgPatxJnsR9ET4WIVm0iOFsJ7GuP0iSacVyFhKkCUbwzgMBl8jZI";

    // Clean mobile number (Extracts last 10 digits)
    $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNumber);
    if (strlen($cleanMobile) > 10) {
        $cleanMobile = substr($cleanMobile, -10);
    }

    $fields = array(
        "variables_values" => $otpCode,
        "route" => "otp",
        "numbers" => $cleanMobile,
    );

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
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

    if ($err) {
        error_log("Fast2SMS Error: " . $err);
        return false;
    } else {
        return true;
    }
}