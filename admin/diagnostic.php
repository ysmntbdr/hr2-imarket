<?php
// Diagnostic file to check what's causing the error
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>HR2 Admin Diagnostic</h1>";

// Test 1: Basic PHP
echo "<h2>1. PHP Status</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";

// Test 2: File existence
echo "<h2>2. File Check</h2>";
$files = ['config.php', 'admin_auth.php', 'login.php', 'dashboard.php'];
foreach ($files as $file) {
    echo $file . ": " . (file_exists($file) ? "✅ EXISTS" : "❌ MISSING") . "<br>";
}

// Test 3: Database connection
echo "<h2>3. Database Connection</h2>";
try {
    require_once 'config.php';
    echo "Config loaded successfully<br>";
    
    $pdo = getAdminPDO();
    echo "Database connection: ✅ SUCCESS<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $result = $stmt->fetch();
    echo "Employee count: " . $result['count'] . "<br>";
    
} catch (Exception $e) {
    echo "Database error: ❌ " . $e->getMessage() . "<br>";
}

// Test 4: Session
echo "<h2>4. Session Test</h2>";
try {
    ini_set('session.name', 'HR2_ADMIN_SESSION');
    ini_set('session.cookie_path', '/hr2_admin/');
    session_start();
    echo "Session started: ✅ SUCCESS<br>";
    echo "Session ID: " . session_id() . "<br>";
} catch (Exception $e) {
    echo "Session error: ❌ " . $e->getMessage() . "<br>";
}

// Test 5: Authentication functions
echo "<h2>5. Authentication Functions</h2>";
try {
    echo "isAdminLoggedIn function: " . (function_exists('isAdminLoggedIn') ? "✅ EXISTS" : "❌ MISSING") . "<br>";
    if (function_exists('isAdminLoggedIn')) {
        echo "Currently logged in: " . (isAdminLoggedIn() ? "YES" : "NO") . "<br>";
    }
} catch (Exception $e) {
    echo "Auth error: ❌ " . $e->getMessage() . "<br>";
}

echo "<h2>6. Next Steps</h2>";
echo "If all tests pass, try accessing: <a href='login.php'>login.php</a> or <a href='dashboard.php'>dashboard.php</a><br>";
?>
