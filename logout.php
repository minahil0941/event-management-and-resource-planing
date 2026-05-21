<?php
/**
 * Logout Page
 * Purges sessions and clears the permanent session cookie.
 */
require_once 'core/session.php';

// 1. Clear session variables
$_SESSION = array();

// 2. Destroy the session cookie for the specific project path
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroy the session on server
session_destroy();

// 4. Redirect to login
header("Location: login.php?msg=" . urlencode("Logout Successful"));
exit;
?>