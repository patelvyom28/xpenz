<?php
date_default_timezone_set('Asia/Kolkata');

/**
 * Multi-Channel SMS Notification Helper Engine
 * Supports: Fast2SMS (India Real SMS), Twilio API, and Local File Simulation.
 */
function sendSMSNotification($toPhone, $message) {
    // -------------------------------------------------------------
    // CONFIGURATION SECTION (API CREDENTIALS)
    // -------------------------------------------------------------
    
    // 1. Fast2SMS API Key (Active Indian Transactional Route)
    $fast2smsApiKey = 'y6ZUre3HD8CGjMz5cwSQoNYkdKfvqgPatxJnsR9ET4WIVm0iOFsJ7GuP0iSacVyFhKkCUbwzgMBl8jZI'; 

    // 2. Twilio API Credentials (Fallback/Testing)
    $twilioAccountSid = 'YOUR_TWILIO_ACCOUNT_SID';
    $twilioAuthToken  = 'YOUR_TWILIO_AUTH_TOKEN';
    $twilioNumber     = '+1234567890';

    // Always log locally for verification & academic viva demo
    $logFile = __DIR__ . '/../sms_logs.txt';
    $logEntry = "[" . date('Y-m-d H:i:s') . "] SMS Triggered for {$toPhone}: {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // Clean phone number (Extract 10 digits)
    $cleanPhone = preg_replace('/[^0-9]/', '', $toPhone);
    if (strlen($cleanPhone) > 10) {
        $cleanPhone = substr($cleanPhone, -10);
    }

    // -------------------------------------------------------------
    // OPTION A: Fast2SMS Real Delivery (Active API)
    // -------------------------------------------------------------
    if (!empty($fast2smsApiKey) && !empty($cleanPhone)) {
        $fields = array(
            "variables_values" => $message,
            "route" => "otp",
            "numbers" => $cleanPhone,
        );

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => array(
                "authorization: " . $fast2smsApiKey,
                "accept: */*",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? true : false;
    }

    // -------------------------------------------------------------
    // OPTION B: Twilio Real Delivery
    // -------------------------------------------------------------
    if ($twilioAccountSid !== 'YOUR_TWILIO_ACCOUNT_SID') {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioAccountSid}/Messages.json";
        $data = [
            'From' => $twilioNumber,
            'To'   => $toPhone,
            'Body' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");

        $response = curl_exec($ch);
        curl_close($ch);
        return $response ? true : false;
    }

    // -------------------------------------------------------------
    // OPTION C: Fallback Local Simulation
    // -------------------------------------------------------------
    return true;
}
?>