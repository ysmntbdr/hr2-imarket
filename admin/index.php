<?php
// HR Admin Portal Entry Point
// Redirect to login page if not authenticated, otherwise to dashboard

require_once 'config.php';

if (isAdminLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
?>
