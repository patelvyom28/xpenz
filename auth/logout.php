<?php
session_start();

// Session all data clear
$_SESSION = array();

// Session access remove from server
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Session (Destroy)
session_destroy();

// Return to Login
header("Location: login.php");
exit();
?>