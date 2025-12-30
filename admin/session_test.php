<?php
// Session Debug Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ADMIN_SESSION_NAME', 'hr_admin_session');

echo "<h2>Session Debug Test</h2>";

// Test 1: Start session
echo "<h3>Test 1: Starting Session</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_name(ADMIN_SESSION_NAME);
    session_start();
    echo "✅ Session started<br>";
} else {
    echo "ℹ️ Session already active<br>";
}

echo "Session Name: " . session_name() . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";
echo "Session Cookie Params: <pre>" . print_r(session_get_cookie_params(), true) . "</pre>";

// Test 2: Set test data
echo "<h3>Test 2: Session Data</h3>";
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
    $_SESSION['test_time'] = time();
    echo "✅ First visit - Session data initialized<br>";
} else {
    $_SESSION['test_counter']++;
    echo "✅ Returning visit #" . $_SESSION['test_counter'] . "<br>";
    echo "First visit was: " . date('Y-m-d H:i:s', $_SESSION['test_time']) . "<br>";
}

// Test 3: Show all session data
echo "<h3>Test 3: All Session Variables</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Test 4: Check if session will persist
echo "<h3>Test 4: Session Persistence Check</h3>";
echo "Reload this page to verify session persists across requests.<br>";

// Test 5: Cookie check
echo "<h3>Test 5: Cookies</h3>";
echo "Session Cookie Name: " . session_name() . "<br>";
echo "All Cookies: <pre>" . print_r($_COOKIE, true) . "</pre>";
?>
