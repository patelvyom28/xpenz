<?php
// Twilio / Fast2SMS API Wrapper Engine
function sendSMSNotification($toPhone, $message) {
    // API Credentials (Twilio Sandbox / Live)
    $accountSid = 'YOUR_TWILIO_ACCOUNT_SID';
    $authToken  = 'YOUR_TWILIO_AUTH_TOKEN';
    $twilioNumber = '+1234567890'; // Your Twilio Sender Number

    // Simulated Log Mode for Local Academic Project
    if ($accountSid === 'YOUR_TWILIO_ACCOUNT_SID') {
        $logFile = __DIR__ . '/../sms_logs.txt';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] SMS Sent to {$toPhone}: {$message}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return true;
    }

    // Real API Call via cURL
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
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
?>