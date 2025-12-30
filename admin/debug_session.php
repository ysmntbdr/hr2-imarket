<?php
// Session Debug Page - Shows current session state
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Admin Session Debug</h2>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} pre{background:#fff;padding:15px;border:1px solid #ddd;border-radius:5px;}</style>";

echo "<h3>1. Session Information</h3>";
echo "<pre>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "NONE") . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Save Path: " . session_save_path() . "\n";
echo "</pre>";

echo "<h3>2. Session Cookie Parameters</h3>";
echo "<pre>";
print_r(session_get_cookie_params());
echo "</pre>";

echo "<h3>3. All Session Data</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>4. All Cookies</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h3>5. Login Status Check</h3>";
echo "<pre>";
$isLoggedIn = isAdminLoggedIn();
echo "isAdminLoggedIn(): " . ($isLoggedIn ? "TRUE - Logged In ✅" : "FALSE - Not Logged In ❌") . "\n";

if ($isLoggedIn) {
    $currentUser = getCurrentAdmin();
    echo "\nCurrent User:\n";
    print_r($currentUser);
}
echo "</pre>";

echo "<h3>6. Actions</h3>";
echo '<a href="login.php" style="display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin-right:10px;">Go to Login</a>';
echo '<a href="dashboard.php" style="display:inline-block;padding:10px 20px;background:#28a745;color:white;text-decoration:none;border-radius:5px;margin-right:10px;">Go to Dashboard</a>';
echo '<a href="logout.php" style="display:inline-block;padding:10px 20px;background:#dc3545;color:white;text-decoration:none;border-radius:5px;">Logout</a>';
echo '<br><br>';
echo '<a href="debug_session.php" style="display:inline-block;padding:10px 20px;background:#6c757d;color:white;text-decoration:none;border-radius:5px;">Refresh This Page</a>';
?>
