<?php
session_start();
require_once 'config.php';

// If user is already fully logged in, go to dashboard
if (isset($_SESSION['employee_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Require pending user from login step
if (!isset($_SESSION['pending_user'], $_SESSION['otp_code'], $_SESSION['otp_expires'])) {
    header('Location: login.php');
    exit;
}

$pendingUser = $_SESSION['pending_user'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = $_POST['otp'] ?? '';

    if (empty($enteredOtp)) {
        $error = 'Please enter the OTP code.';
    } elseif (time() > $_SESSION['otp_expires']) {
        $error = 'Your OTP code has expired. Please log in again to request a new code.';
        unset($_SESSION['pending_user'], $_SESSION['otp_code'], $_SESSION['otp_expires']);
    } elseif ($enteredOtp != $_SESSION['otp_code']) {
        $error = 'Invalid OTP code. Please try again.';
    } else {
        // OTP is valid
        try {
            $pdo = getPDO();

            // Update last login
            $stmt = $pdo->prepare('UPDATE employees SET updated_at = NOW() WHERE id = ?');
            $stmt->execute([$pendingUser['id']]);
        } catch (Exception $e) {
            // Log but do not block login
            error_log('OTP verify update error: ' . $e->getMessage());
        }

        // Promote pending user to fully logged-in user
        $_SESSION['employee_id'] = $pendingUser['id'];
        $_SESSION['username'] = $pendingUser['username'];
        $_SESSION['full_name'] = $pendingUser['full_name'];
        $_SESSION['role'] = $pendingUser['role'];
        $_SESSION['department'] = $pendingUser['department'];
        $_SESSION['position'] = $pendingUser['position'];
        $_SESSION['login_time'] = time();

        // Clear OTP data
        unset($_SESSION['pending_user'], $_SESSION['otp_code'], $_SESSION['otp_expires']);

        header('Location: dashboard.php');
        exit;
    }
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
                        <strong><?php echo htmlspecialchars($pendingUser['full_name']); ?></strong>.
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

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-3" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="mt-3">
                        <div class="mb-4">
                            <label for="otp" class="form-label">One-Time Password (OTP)</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" maxlength="6" placeholder="Enter 6-digit code" required>
                        </div>
                        <div class="d-grid mb-2">
                            <button type="submit" class="btn btn-verify">
                                <i class="fas fa-shield-alt me-2"></i>
                                Verify &amp; Continue
                            </button>
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
