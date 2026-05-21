<?php
// File: reset_password.php
require 'core/db.php';

// The password you want to use
$new_password = '1234';
$email = 'admin@sys.com';

// Generate a fresh hash using YOUR server's algorithm
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Force update the user
    $stmt = $pdo->prepare("UPDATE users SET password = :hash, is_active = 1 WHERE email = :email");
    $stmt->execute([':hash' => $new_hash, ':email' => $email]);
    
    echo "<h1>Success!</h1>";
    echo "<p>Password for <strong>$email</strong> has been reset to: <strong>$new_password</strong></p>";
    echo "<p>Generated Hash: $new_hash</p>";
    echo "<p><a href='admin/login.php'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>