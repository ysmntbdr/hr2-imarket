<?php
// Admin logout - destroy admin session and redirect
require_once 'config.php';

// Log logout activity
if (isAdminLoggedIn()) {
    logAdminActivity('logout', 'User logged out');
}

// Destroy admin session
destroyAdminSession();

// Redirect to admin login page
header('Location: login.php?logout=1');
exit;
?>
