<?php
// HR Admin Portal Database Configuration
// This allows the admin portal to be completely separate from the main ESS portal

// Database Configuration - Updated with correct port
define('DB_HOST', 'localhost'); 
define('DB_PORT', '3306');      
define('DB_NAME', 'hr2_imarketph'); 
define('DB_USER', 'root'); 
define('DB_PASS', '');

// Admin Portal Settings
define('ADMIN_SESSION_NAME', 'hr_admin_session');
define('ADMIN_LOGIN_REDIRECT', 'login.php');
define('ADMIN_DASHBOARD_REDIRECT', 'dashboard.php');

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds
define('FORCE_PRODUCTION_EMAIL', true); // Force SMTP even on localhost when true

// Admin Portal Base URL (adjust as needed)
define('ADMIN_BASE_URL', '/hr2_ess/hr_admin/');

// Create PDO connection for admin portal
function getAdminPDO() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Admin Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please contact the administrator.");
        }
    }
    
    return $pdo;
}

// Initialize admin session
function initAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie parameters before starting session
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_name(ADMIN_SESSION_NAME);
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// Check if admin is logged in (using existing employee system)
function isAdminLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie parameters before starting session
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_name(ADMIN_SESSION_NAME);
        session_start();
    }
    
    // If OTP verification is pending, user is not fully logged in yet
    if (isset($_SESSION['otp_pending']) && $_SESSION['otp_pending']) {
        return false;
    }
    
    // Check if user is logged in and has admin role
    if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
        return false;
    }
    
    // Check if user has admin privileges
    $admin_roles = ['admin', 'hr_admin', 'super_admin', 'manager'];
    if (!in_array($_SESSION['role'], $admin_roles)) {
        return false;
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_destroy();
            return false;
        }
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    return true;
}

// Get current admin user (from employees table)
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    // Return employee data from session
    return [
        'id' => $_SESSION['employee_id'],
        'full_name' => $_SESSION['full_name'] ?? 'Admin User',
        'username' => $_SESSION['username'] ?? 'admin',
        'role' => $_SESSION['role'] ?? 'admin',
        'email' => $_SESSION['email'] ?? '',
        'department' => $_SESSION['department'] ?? '',
        'position' => $_SESSION['position'] ?? ''
    ];
}

// Get current admin ID (from employee session)
function getCurrentAdminId() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    return $_SESSION['employee_id'] ?? null;
}

// Destroy admin session
function destroyAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set session cookie parameters before starting session
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_name(ADMIN_SESSION_NAME);
        session_start();
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

// Redirect to login if not authenticated
function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . ADMIN_LOGIN_REDIRECT);
        exit;
    }
}

// Log admin activity
function logAdminActivity($action, $details = '') {
    try {
        $pdo = getAdminPDO();
        $employee_id = getCurrentAdminId();
        
        if ($employee_id) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_log (employee_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $employee_id,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

function generateAdminOtpCode($length = 6) {
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
    $code = random_int($min, $max);
    return str_pad((string) $code, $length, '0', STR_PAD_LEFT);
}

function createAdminOtpRecord($employee_id, $email, $ttl_seconds = 300) {
    $pdo = getAdminPDO();

    // Clean up previous/expired OTPs for this employee
    $cleanup_stmt = $pdo->prepare("DELETE FROM otp_codes WHERE employee_id = ? AND (is_used = 1 OR expires_at < NOW())");
    $cleanup_stmt->execute([$employee_id]);

    $otp_code = generateAdminOtpCode();
    $code_hash = password_hash($otp_code, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + $ttl_seconds);

    $fields = ['employee_id', 'code_hash', 'expires_at', 'delivery_address'];
    $placeholders = ['?', '?', '?', '?'];
    $values = [$employee_id, $code_hash, $expires_at, $email];

    if (otpColumnExists($pdo, 'otp_code')) {
        $fields[] = 'otp_code';
        $placeholders[] = '?';
        $values[] = $otp_code;
    }

    if (otpColumnExists($pdo, 'type')) {
        $fields[] = 'type';
        $placeholders[] = '?';
        $values[] = 'login';
    }

    if (otpColumnExists($pdo, 'used')) {
        $fields[] = 'used';
        $placeholders[] = '?';
        $values[] = 0;
    }

    $sql = sprintf(
        'INSERT INTO otp_codes (%s) VALUES (%s)',
        implode(', ', $fields),
        implode(', ', $placeholders)
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    return [
        'otp_id' => (int) $pdo->lastInsertId(),
        'code' => $otp_code,
        'expires_at' => $expires_at
    ];
}

function getLatestAdminOtp($employee_id) {
    $pdo = getAdminPDO();
    $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE employee_id = ? AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    return $stmt->fetch();
}

function incrementAdminOtpAttempts($otp_id) {
    $pdo = getAdminPDO();
    $stmt = $pdo->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
    $stmt->execute([$otp_id]);
}

function markAdminOtpUsed($otp_id) {
    $pdo = getAdminPDO();
    $stmt = $pdo->prepare("UPDATE otp_codes SET is_used = 1, used_at = NOW() WHERE id = ?");
    $stmt->execute([$otp_id]);
}

// Create admin activity log table (using existing employees table for users)
function otpColumnExists($pdo, $column) {
    static $cache = [];
    if (isset($cache[$column])) {
        return $cache[$column];
    }

    $column_safe = str_replace('`', '``', $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM otp_codes LIKE '{$column_safe}'");
    $cache[$column] = ($stmt !== false && $stmt->rowCount() > 0);
    return $cache[$column];
}

function ensureOtpColumnExists($pdo, $column, $definition) {
    if (!otpColumnExists($pdo, $column)) {
        $pdo->exec("ALTER TABLE otp_codes ADD COLUMN $definition");
        // refresh cache entry
        otpColumnExists($pdo, $column);
    }
}

function createAdminTables() {
    try {
        $pdo = getAdminPDO();
        
        // Admin activity log table (references employees table)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
            )
        ");

        // OTP codes table for 2FA
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS otp_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts TINYINT UNSIGNED DEFAULT 0,
                max_attempts TINYINT UNSIGNED DEFAULT 5,
                is_used TINYINT(1) DEFAULT 0,
                delivery_channel VARCHAR(20) DEFAULT 'email',
                delivery_address VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                used_at DATETIME NULL,
                INDEX idx_employee_expires (employee_id, expires_at),
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
            )
        ");

        // Ensure legacy tables pick up new columns
        ensureOtpColumnExists($pdo, 'code_hash', "code_hash VARCHAR(255) NOT NULL DEFAULT ''");
        ensureOtpColumnExists($pdo, 'expires_at', "expires_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        ensureOtpColumnExists($pdo, 'attempts', "attempts TINYINT UNSIGNED DEFAULT 0");
        ensureOtpColumnExists($pdo, 'max_attempts', "max_attempts TINYINT UNSIGNED DEFAULT 5");
        ensureOtpColumnExists($pdo, 'is_used', "is_used TINYINT(1) DEFAULT 0");
        ensureOtpColumnExists($pdo, 'delivery_channel', "delivery_channel VARCHAR(20) DEFAULT 'email'");
        ensureOtpColumnExists($pdo, 'delivery_address', "delivery_address VARCHAR(255) NULL");
        ensureOtpColumnExists($pdo, 'created_at', "created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        ensureOtpColumnExists($pdo, 'used_at', "used_at DATETIME NULL");

        // Check if there's at least one admin user in employees table
        $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE role IN ('admin', 'hr_admin', 'super_admin', 'manager')");
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count == 0) {
            // Create a default admin employee if none exists
            $stmt = $pdo->prepare("
                INSERT INTO employees (username, password, full_name, email, role, status, hire_date) 
                VALUES (?, ?, ?, ?, ?, 'active', CURDATE())
            ");
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt->execute(['admin', $defaultPassword, 'System Administrator', 'admin@company.com', 'admin']);
        }
        
    } catch (Exception $e) {
        error_log("Failed to create admin tables: " . $e->getMessage());
    }
}

// Initialize admin portal
createAdminTables();

// Update database schema for training management
function updateTrainingSchema() {
    try {
        $pdo = getAdminPDO();
        
        // Check if employee_trainings table has the required columns
        $stmt = $pdo->query("SHOW COLUMNS FROM employee_trainings LIKE 'status'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE employee_trainings ADD COLUMN status VARCHAR(50) DEFAULT 'registered'");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM employee_trainings LIKE 'attendance_status'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE employee_trainings ADD COLUMN attendance_status VARCHAR(50) DEFAULT NULL");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM employee_trainings LIKE 'completion_status'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE employee_trainings ADD COLUMN completion_status VARCHAR(50) DEFAULT NULL");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM employee_trainings LIKE 'enrollment_date'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE employee_trainings ADD COLUMN enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Check if courses table has is_active column
        $stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'is_active'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE courses ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        }
        
        // Create employee_courses table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS employee_courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                course_id INT NOT NULL,
                enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                progress INT DEFAULT 0,
                status VARCHAR(50) DEFAULT 'enrolled',
                completion_date DATE NULL,
                UNIQUE KEY unique_enrollment (employee_id, course_id),
                FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
            )
        ");
        
    } catch (Exception $e) {
        // Silently fail if tables don't exist yet
        error_log("Training schema update failed: " . $e->getMessage());
    }
}

// Run schema updates
updateTrainingSchema();

// Clean up expired OTPs (run occasionally)
if (rand(1, 100) <= 5) { // 5% chance to run cleanup
    try {
        $pdo = getAdminPDO();
        $pdo->exec("DELETE FROM otp_codes WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    } catch (Exception $e) {
        // Silently fail if OTP table doesn't exist yet
    }
}
?>
