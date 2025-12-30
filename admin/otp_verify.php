<?php
require_once 'config.php';
require_once 'email_config.php';

// Ensure session started
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

// If user already fully authenticated, redirect to dashboard
if (isAdminLoggedIn()) {
    header('Location: ' . ADMIN_DASHBOARD_REDIRECT);
    exit;
}

if (!isset($_SESSION['otp_pending'], $_SESSION['otp_employee_id']) || !$_SESSION['otp_pending']) {
    header('Location: login.php');
    exit;
}

$error_message = '';
$success_message = '';
$otp_employee_id = (int) $_SESSION['otp_employee_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $otp_code = trim($_POST['otp_code'] ?? '');

        if ($otp_code === '') {
            $error_message = 'Please enter the verification code.';
        } else {
            try {
                $pdo = getAdminPDO();
                $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE employee_id = ? AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$otp_employee_id]);
                $otp = $stmt->fetch();

                if (!$otp) {
                    $error_message = 'No active verification code found. Please request a new one.';
                } elseif (strtotime($otp['expires_at']) < time()) {
                    $error_message = 'The verification code has expired. Please request a new one.';
                } elseif ($otp['attempts'] >= $otp['max_attempts']) {
                    $error_message = 'Too many invalid attempts. Please request a new verification code.';
                } elseif (!password_verify($otp_code, $otp['code_hash'])) {
                    incrementAdminOtpAttempts($otp['id']);
                    $attempts_left = max(0, ($otp['max_attempts'] ?? 5) - ($otp['attempts'] + 1));
                    $error_message = 'Invalid verification code.' . ($attempts_left > 0 ? " You have {$attempts_left} attempt(s) left." : '');
                } else {
                    markAdminOtpUsed($otp['id']);
                    unset($_SESSION['otp_pending'], $_SESSION['otp_employee_id'], $_SESSION['otp_expires_at']);
                    $_SESSION['last_activity'] = time();

                    try {
                        logAdminActivity('otp_verified', 'OTP verified for admin login');
                    } catch (Exception $e) {
                        // ignore logging failure
                    }

                    header('Location: ' . ADMIN_DASHBOARD_REDIRECT);
                    exit;
                }
            } catch (Exception $e) {
                error_log('OTP verification error: ' . $e->getMessage());
                $error_message = 'Unable to verify the code right now. Please try again later.';
            }
        }
    } elseif (isset($_POST['resend_otp'])) {
        try {
            if (isset($_SESSION['otp_last_sent']) && time() - $_SESSION['otp_last_sent'] < 60) {
                $error_message = 'Please wait before requesting another code.';
            } else {
                $pdo = getAdminPDO();
                $stmt = $pdo->prepare('SELECT email, full_name FROM employees WHERE id = ? LIMIT 1');
                $stmt->execute([$otp_employee_id]);
                $admin = $stmt->fetch();

                if (!$admin || empty($admin['email'])) {
                    throw new Exception('Admin record not found for OTP resend.');
                }

                $otpData = createAdminOtpRecord($otp_employee_id, $admin['email']);
                if (!sendOTPEmailSMTP($admin['email'], $otpData['code'], $admin['full_name'])) {
                    throw new Exception('Failed to dispatch OTP email.');
                }

                $_SESSION['otp_expires_at'] = $otpData['expires_at'];
                $_SESSION['otp_last_sent'] = time();

                try {
                    logAdminActivity('otp_resent', 'OTP resent for admin login');
                } catch (Exception $e) {
                    // ignore logging failure
                }

                $success_message = 'A new verification code has been sent to your email.';
            }
        } catch (Exception $e) {
            error_log('OTP resend error: ' . $e->getMessage());
            $error_message = 'Unable to resend verification code right now.';
        }
    }
}

$obfuscated_email = '';
$pending_full_name = $_SESSION['full_name'] ?? 'Admin User';
try {
    $pdo = getAdminPDO();
    $stmt = $pdo->prepare('SELECT email FROM employees WHERE id = ? LIMIT 1');
    $stmt->execute([$otp_employee_id]);
    $admin = $stmt->fetch();
    if ($admin && $admin['email']) {
        $parts = explode('@', $admin['email']);
        if (count($parts) === 2) {
            $local = $parts[0];
            $domain = $parts[1];
            $obfuscated_email = substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) . '@' . $domain;
        } else {
            $obfuscated_email = $admin['email'];
        }
        $pending_full_name = $admin['full_name'] ?? $pending_full_name;
    }
} catch (Exception $e) {
    $obfuscated_email = 'your email';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - HR Employee Self-Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .otp-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 2.5rem 2rem;
        }
        .btn-verify {
            background: var(--gradient);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="otp-container">
                    <h3 class="text-center mb-3">Two-Factor Authentication</h3>
                    <p class="text-center mb-4">
                        An OTP has been sent to your registered email address for
                        <strong><?php echo htmlspecialchars($pending_full_name); ?></strong>.
                        Please check your email and enter the 6-digit code below to complete your login.
                    </p>

                    <?php if (isset($_SESSION['debug_otp'])): ?>
                        <div class="alert alert-warning border-0 rounded-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Email delivery issue:</strong> Your OTP is <code><?php echo $_SESSION['debug_otp']; ?></code>
                            <br><small>Email sending failed, showing OTP here for testing. Email would be sent to: <?php echo htmlspecialchars($_SESSION['debug_email'] ?? 'N/A'); ?></small>
                            <?php if (isset($_SESSION['debug_error'])): ?>
                                <br><small class="text-muted">Error: <?php echo htmlspecialchars($_SESSION['debug_error']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger border-0 rounded-3" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success border-0 rounded-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="mt-3">
                        <div class="mb-4">
                            <label for="otp" class="form-label">One-Time Password (OTP)</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp_code" maxlength="6" placeholder="Enter 6-digit code" required>
                        </div>
                        <div class="d-grid mb-2">
                            <button type="submit" name="verify_otp" class="btn btn-verify">
                                <i class="fas fa-shield-alt me-2"></i>
                                Verify &amp; Continue
                            </button>
                        </div>
                        <div class="d-grid mb-2">
                            <button type="submit" name="resend_otp" class="btn btn-link text-decoration-none">Resend Code</button>
                        </div>
                        <div class="text-center mt-2">
                            <a href="login.php" class="small">Back to login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
