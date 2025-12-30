<?php
// Admin authentication system - Updated to use new separate admin config
require_once 'config.php';

// Backward compatibility functions for existing admin pages
function getPDO() {
    return getAdminPDO();
}

function isLoggedIn() {
    return isAdminLoggedIn();
}

function getCurrentEmployee() {
    return getCurrentAdmin();
}

function getCurrentEmployeeId() {
    return getCurrentAdminId();
}

function requireAuth() {
    requireAdminAuth();
}

function requireAdminAccess() {
    requireAdminAuth();
}

function hasAdminRole($required_role) {
    $admin = getCurrentAdmin();
    if (!$admin) return false;
    
    $role_hierarchy = [
        'hr_admin' => 1,
        'manager' => 2,
        'super_admin' => 3
    ];
    
    $user_level = $role_hierarchy[$admin['role']] ?? 1;
    $required_level = $role_hierarchy[$required_role] ?? 1;
    
    return $user_level >= $required_level;
}

// Check authentication on page load
if (!isAdminLoggedIn()) {
    // Allow access to login page
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'login.php') {
        header('Location: login.php');
        exit;
    }
}
?>
