<?php
// Authentication check - include this at the top of protected pages
session_start();

// Include config for database functions
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header('Location: login.php');
    exit;
}

// Check session timeout (optional - 8 hours)
$timeout_duration = 8 * 60 * 60; // 8 hours in seconds
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Function to check if user has specific role
function hasRole($required_role) {
    $user_role = $_SESSION['role'] ?? 'employee';
    
    $role_hierarchy = [
        'employee' => 1,
        'manager' => 2,
        'hr' => 3,
        'admin' => 4
    ];
    
    $user_level = $role_hierarchy[$user_role] ?? 1;
    $required_level = $role_hierarchy[$required_role] ?? 1;
    
    return $user_level >= $required_level;
}

// Note: getCurrentEmployeeId() and getCurrentEmployee() are now defined in config.php
?>
