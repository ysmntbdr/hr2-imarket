<?php
session_start();
require_once 'config.php';
require_once 'email_helper.php';

// Redirect if already logged in
if (isset($_SESSION['employee_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $pdo = getPDO();
            
            // Get user from database using email
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Start 2FA OTP flow instead of immediate login
                $otp = random_int(100000, 999999);
                $otpExpires = time() + (5 * 60); // 5 minutes

                // Store pending user and OTP in session
                $_SESSION['pending_user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role'],
                    'department' => $user['department'],
                    'position' => $user['position'],
                ];
                $_SESSION['otp_code'] = $otp;
                $_SESSION['otp_expires'] = $otpExpires;

                // Send OTP via email
                if (!empty($user['email'])) {
                    $emailSent = sendOtpEmail($user['email'], $user['full_name'], $otp);
                    if (!$emailSent) {
                        $error = 'Unable to send OTP email. Please contact the system administrator.';
                        unset($_SESSION['pending_user'], $_SESSION['otp_code'], $_SESSION['otp_expires']);
                    }
                } else {
                    $error = 'No email address is associated with this account. Please contact the system administrator.';
                    unset($_SESSION['pending_user'], $_SESSION['otp_code'], $_SESSION['otp_expires']);
                }

                if (empty($error)) {
                    header('Location: verify_otp.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            $error = 'Login system temporarily unavailable. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check for logout message
if (isset($_GET['logout'])) {
    $message = 'You have been logged out successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Employee Self-Service - Login</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #4bc5ec;
            --primary-dark: #3ba3cc;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            background: var(--gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }

        .login-left {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
        }

        .login-right {
            padding: 3rem;
        }

        .login-form {
            max-width: 400px;
            margin: 0 auto;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(75, 197, 236, 0.25);
        }

        .btn-login {
            background: var(--gradient);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        .company-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2rem;
        }

        .animate-fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .demo-credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .login-left {
                padding: 2rem;
            }
            .login-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="login-container animate-fade-in">
                    <div class="row g-0">
                        <!-- Left Side - Branding -->
                        <div class="col-lg-6 login-left">
                            <div class="company-logo">
                                <i class="fas fa-building"></i>
                            </div>
                            <h2 class="mb-3">Welcome Back!</h2>
                            <p class="mb-4">Access your HR Employee Self-Service portal to manage your work life efficiently.</p>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <p class="small">Employee<br>Management</p>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                                    <p class="small">Performance<br>Tracking</p>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-graduation-cap fa-2x mb-2"></i>
                                    <p class="small">Learning &<br>Development</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Side - Login Form -->
                        <div class="col-lg-6 login-right">
                            <div class="login-form">
                                <h3 class="text-center mb-4">Sign In</h3>
                                
                                <!-- Success/Error Messages -->
                                <?php if ($message): ?>
                                    <div class="alert alert-success border-0 rounded-3" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?= htmlspecialchars($message) ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($error): ?>
                                    <div class="alert alert-danger border-0 rounded-3" role="alert">
                                        <i class="fas fa-exclamation-circle me-2"></i>
                                        <?= htmlspecialchars($error) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   placeholder="Enter your email address" required 
                                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Enter your password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-login">
                                            <i class="fas fa-sign-in-alt me-2"></i>
                                            Sign In
                                        </button>
                                    </div>
                                </form>
                                
                                <!-- Demo Credentials -->
                                <div class="demo-credentials">
                                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>Demo Credentials</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Admin:</strong><br>
                                            Email: <code>mariankeith13@gmail.com</code><br>
                                            Password: <code>gatela1313</code>
                                        </div>
                                        <div class="col-6">
                                            <strong>Employee:</strong><br>
                                            Email: <code>Elmerbarrientos019@gmail.com</code><br>
                                            Password: <code>password123</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Auto-focus email field
        document.getElementById('email').focus();
    </script>
</body>
</html>
