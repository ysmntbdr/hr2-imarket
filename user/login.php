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
        }

        body {
            background: #353c61;
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
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.35);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
            display: flex;
            animation: slideInUp 0.8s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .login-left {
            background: #353c61;
            padding: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .logo-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1;
            min-height: 500px;
        }

        .logo-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            filter: brightness(1.05) drop-shadow(0 10px 30px rgba(0, 0, 0, 0.2));
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            padding: 0;
            box-sizing: border-box;
        }

        .login-right {
            padding: 3rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-left: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: -10px 0 40px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .login-right::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            z-index: 0;
            pointer-events: none;
        }

        .login-right::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0 20px 20px 0;
            z-index: 0;
            pointer-events: none;
        }

        .login-right > * {
            position: relative;
            z-index: 1;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(255, 255, 255, 0.8);
        }

        .form-subtitle {
            color: #495057;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            font-weight: 500;
            text-shadow: 0 1px 5px rgba(255, 255, 255, 0.6);
        }

        .form-control {
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
            color: #212529;
        }

        .form-control:focus {
            border-color: rgba(75, 197, 236, 0.8);
            box-shadow: 0 0 0 0.25rem rgba(75, 197, 236, 0.2);
            background: rgba(255, 255, 255, 0.85);
            outline: none;
        }

        .form-control::placeholder {
            color: #adb5bd;
        }

        .input-group {
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #495057;
            padding: 14px 16px;
            font-size: 1rem;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
            border-right: 2px solid rgba(255, 255, 255, 0.3);
            background: transparent;
        }

        .input-group .form-control:focus {
            border-right-color: rgba(75, 197, 236, 0.8);
            background: rgba(255, 255, 255, 0.85);
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
            box-shadow: 0 4px 15px rgba(75, 197, 236, 0.3);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
            color: white;
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(15px) saturate(180%);
            -webkit-backdrop-filter: blur(15px) saturate(180%);
        }

        .alert-danger {
            background: rgba(255, 245, 245, 0.7);
            color: #c92a2a;
            border-left: 4px solid #fa5252;
        }

        .alert-success {
            background: rgba(240, 249, 255, 0.7);
            color: #0c5460;
            border-left: 4px solid #20c997;
        }

        .alert-info {
            background: rgba(231, 245, 255, 0.7);
            color: #0c5460;
            border-left: 4px solid #4bc5ec;
        }

        .toggle-password-btn {
            background: rgba(255, 255, 255, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-left: none;
            border-radius: 0 12px 12px 0;
            color: #495057;
            padding: 14px 16px;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            cursor: pointer;
        }

        .toggle-password-btn:hover {
            background: rgba(255, 255, 255, 0.7);
            color: #495057;
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
            <div class="logo-container">
                <!-- Logo image - try multiple paths -->
                <img src="../admin/logo.png" alt="IMARKET Logo" class="logo-image" id="logoImg"
                     onerror="this.onerror=null; this.src='../admin/assets/logo.png'; this.onerror=handleLogoError;">
                <div id="logoPlaceholder" style="display: none; color: rgba(255,255,255,0.6); text-align: center; padding: 2rem;">
                    <i class="fas fa-image" style="font-size: 4rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                    <p style="font-size: 0.9rem; line-height: 1.6;">
                        Logo image not found.<br>
                        <strong style="color: rgba(255,255,255,0.9);">Please ensure logo.png exists in:</strong><br>
                        <code style="background: rgba(0,0,0,0.3); padding: 0.5rem; border-radius: 5px; display: inline-block; margin-top: 0.5rem; font-size: 0.85rem;">
                            admin\logo.png
                        </code>
                    </p>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <h2 class="form-title">Welcome Back!</h2>
            <p class="form-subtitle">Sign in to access your employee portal</p>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input type="email" class="form-control" name="email" placeholder="Email address" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                    <button type="button" class="toggle-password-btn" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle logo error
        function handleLogoError(img) {
            console.error('Logo image failed to load from:', img.src);
            img.style.display = 'none';
            const placeholder = document.getElementById('logoPlaceholder');
            if (placeholder) {
                placeholder.style.display = 'block';
            }
        }
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Check if logo image loaded
            const logoImage = document.getElementById('logoImg');
            if (logoImage) {
                console.log('Logo image element found, src:', logoImage.src);
                
                logoImage.addEventListener('load', function() {
                    console.log('Logo image loaded successfully!');
                    this.style.display = 'block';
                    this.style.visibility = 'visible';
                    this.style.opacity = '1';
                    const placeholder = document.getElementById('logoPlaceholder');
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                });
                
                logoImage.addEventListener('error', function(e) {
                    console.error('Logo image failed to load:', this.src);
                    handleLogoError(this);
                });
                
                // Force display
                logoImage.style.display = 'block';
                logoImage.style.visibility = 'visible';
                logoImage.style.opacity = '1';
                
                // Check if image failed to load after a delay
                setTimeout(function() {
                    if (!logoImage.complete || logoImage.naturalHeight === 0) {
                        console.warn('Logo image may not have loaded. complete:', logoImage.complete, 'height:', logoImage.naturalHeight);
                        if (!logoImage.complete) {
                            handleLogoError(logoImage);
                        }
                    }
                }, 2000);
            } else {
                console.error('Logo image element not found!');
            }
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordField = document.getElementById('password');
            
            if (togglePassword && passwordField) {
                togglePassword.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordField.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
            
            // Focus on email field
            const emailField = document.querySelector('input[name="email"]');
            if (emailField && !emailField.value) {
                emailField.focus();
            }
            
            // Add ripple effect to login button
            const loginBtn = document.querySelector('.btn-primary');
            if (loginBtn) {
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
            }
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
