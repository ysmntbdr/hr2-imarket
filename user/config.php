<?php
// =========================
// config.php
// =========================

// Database credentials
$db_host = "localhost";      
$db_port = "3306";           
$db_name = "hr2_imarketph";     
$db_user = "hr2_imarketph";            
$db_pass = "hr2imarketph";              

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================
// PDO Connection
// =========================
function getPDO() {
    global $db_host, $db_port, $db_name, $db_user, $db_pass;

    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $db_user, $db_pass, $options);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Optional shorthand
function pdo() {
    return getPDO();
}

// =========================
// Helper Functions
// =========================

/**
 * Get current logged-in employee ID
 * @return int
 */
function getCurrentEmployeeId() {
    // Check if user is logged in via session
    if (isset($_SESSION['employee_id'])) {
        return $_SESSION['employee_id'];
    }
    
    // Fallback for demo purposes (when not using auth_check.php)
    return 1;
}

/**
 * Get current logged-in employee data
 * @return array|null
 */
function getCurrentEmployee() {
    // If session data is available, use it for better performance
    if (isset($_SESSION['employee_id'])) {
        return [
            'id' => $_SESSION['employee_id'],
            'full_name' => $_SESSION['full_name'] ?? 'Unknown User',
            'username' => $_SESSION['username'] ?? 'unknown',
            'role' => $_SESSION['role'] ?? 'employee',
            'department' => $_SESSION['department'] ?? 'N/A',
            'position' => $_SESSION['position'] ?? 'N/A',
            'employee_id' => $_SESSION['username'] ?? 'N/A'
        ];
    }
    
    // Fallback: fetch from database
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([getCurrentEmployeeId()]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}
?>

