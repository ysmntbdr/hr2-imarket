<?php
require_once 'config.php';
require_once 'email_config.php';

// Start session early for login page
if (session_status() === PHP_SESSION_NONE) {
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

// Redirect if already logged in
if (isAdminLoggedIn()) {
    header('Location: ' . ADMIN_DASHBOARD_REDIRECT);
    exit;
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_POST && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getAdminPDO();
            
            // Get employee with admin role using email
            $stmt = $pdo->prepare("
                SELECT id, username, email, password, full_name, role, status, department, position
                FROM employees 
                WHERE email = ? AND status = 'active' 
                AND role IN ('admin', 'hr_admin', 'super_admin', 'manager')
            ");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Verify password (check both hashed and plain text for compatibility)
                $password_valid = false;
                if (password_verify($password, $admin['password'])) {
                    $password_valid = true;
                } elseif ($password === $admin['password']) {
                    // Plain text password (for existing data)
                    $password_valid = true;
                }
                
                if ($password_valid) {
                    // Session already started at top of file, just set data
                    $_SESSION['employee_id'] = $admin['id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['full_name'] = $admin['full_name'];
                    $_SESSION['email'] = $admin['email'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['department'] = $admin['department'];
                    $_SESSION['position'] = $admin['position'];
                    $_SESSION['last_activity'] = time();

                    try {
                        $otpData = createAdminOtpRecord($admin['id'], $admin['email']);
                        if (!sendOTPEmailSMTP($admin['email'], $otpData['code'], $admin['full_name'])) {
                            throw new Exception('Failed to send OTP email');
                        }

                        $_SESSION['otp_pending'] = true;
                        $_SESSION['otp_employee_id'] = $admin['id'];
                        $_SESSION['otp_expires_at'] = $otpData['expires_at'];
                        $_SESSION['otp_last_sent'] = time();

                        try {
                            logAdminActivity('otp_sent', 'OTP sent for admin login');
                        } catch (Exception $e) {
                            // Ignore logging errors
                        }

                        session_write_close();
                        header('Location: otp_verify.php');
                        exit;
                    } catch (Exception $e) {
                        error_log('OTP generation error: ' . $e->getMessage());
                        $error_message = 'Unable to send verification code. Please try again in a moment.';
                        destroyAdminSession();
                    }
                } else {
                    $error_message = 'Invalid credentials.';
                }
            } else {
                $error_message = 'Invalid credentials.';
            }
        } catch (Exception $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error_message = 'Login system temporarily unavailable. Please try again later.';
        }
    }
}

// Handle logout message
if (isset($_GET['logout'])) {
    $success_message = 'You have been successfully logged out.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Admin Portal - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4bc5ec;
            --primary-dark: #3ba3cc;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #94dcf4 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
            display: flex;
            animation: slideInUp 0.8s ease-out;
        }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .login-left {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 3rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(75, 197, 236, 0.85), rgba(59, 163, 204, 0.85));
            z-index: 1;
        }

        .login-left::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('../assets/LOGO.png');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 0;
        }

        /* Fallback if logo not found in ../assets/ */
        @supports not (background-image: url('../assets/LOGO.png')) {
            .login-left::after {
                background-image: url('assets/LOGO.png');
            }
        }

        .login-right {
            padding: 3rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            font-size: 2rem;
            color: var(--primary-color);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .login-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(75, 197, 236, 0.25);
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #6c757d;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .features {
            list-style: none;
            padding: 0;
            margin: 2rem 0 0 0;
        }

        .features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            opacity: 0.9;
        }

        .features li i {
            margin-right: 1rem;
            width: 20px;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
            }
            
            .login-left {
                padding: 2rem;
                text-align: center;
            }
            
            .login-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Branding -->
        <div class="login-left">
            <!-- Logo displayed as background -->
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <h2 class="mb-4">Welcome Back</h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['dev_otp_code'])): ?>
                <div class="alert alert-info">
                    <strong>Development OTP:</strong>
                    <?= htmlspecialchars($_SESSION['dev_otp_code']) ?>
                    sent to <?= htmlspecialchars($_SESSION['dev_otp_email'] ?? 'unknown email') ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="email" class="form-control" name="email" placeholder="Email address" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" name="password" placeholder="Password" autocomplete="current-password" required>
                </div>

                <button type="submit" name="login" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <div class="text-center text-muted">
                <small>
                    <i class="fas fa-shield-alt me-1"></i>
                    Secure login system<br>
                    <i class="fas fa-info-circle me-1"></i>
                    Use your employee credentials with admin role
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on username field
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
            
            // Add ripple effect to login button
            const loginBtn = document.querySelector('.btn-primary');
            loginBtn.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255,255,255,0.3)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
