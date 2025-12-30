<?php
// Email Configuration for OTP System
// Restores development-friendly OTP delivery with production SMTP placeholders

require_once 'config.php';

function sendOTPEmailSMTP($email, $otp_code, $full_name) {
    // Determine environment
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $server_name = $_SERVER['SERVER_NAME'] ?? '';
    $is_cli_server = PHP_SAPI === 'cli-server';
    $is_local_host = (
        in_array($host, ['localhost', '127.0.0.1'], true) ||
        strpos($host, 'localhost') !== false ||
        strpos($host, '127.0.0.1') !== false ||
        in_array($server_name, ['localhost', '127.0.0.1'], true)
    );
    $is_development = $is_cli_server || $is_local_host || $host === '';
    if (defined('FORCE_PRODUCTION_EMAIL') && FORCE_PRODUCTION_EMAIL) {
        $is_development = false;
    }

    $debug_payload = sprintf(
        "%s | host=%s | server_name=%s | cli_server=%s | is_development=%s\n",
        date('Y-m-d H:i:s'),
        $host,
        $server_name,
        $is_cli_server ? 'yes' : 'no',
        $is_development ? 'yes' : 'no'
    );
    file_put_contents('otp_env_log.txt', $debug_payload, FILE_APPEND | LOCK_EX);

    if ($is_development) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['dev_otp_code'] = $otp_code;
        $_SESSION['dev_otp_email'] = $email;
        $_SESSION['dev_otp_name'] = $full_name;
        $_SESSION['dev_otp_host'] = $host ?: ($server_name ?: 'cli');

        $log_message = sprintf('[%s] OTP for %s (%s): %s%s', date('Y-m-d H:i:s'), $full_name, $email, $otp_code, PHP_EOL);
        file_put_contents('otp_dev_log.txt', $log_message, FILE_APPEND | LOCK_EX);
        return true;
    }

    // Production SMTP configuration (update with real Gmail + App Password)
    $smtp_config = [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'jerickdellosa3131@gmail.com',
        'password' => 'zujvcqkhtumgrbqp',
        'from_email' => 'jerickdellosa3131@gmail.com',
        'from_name' => 'HR Admin System',
        'helo' => gethostname() ?: 'localhost'
    ];

    $subject = 'HR Admin Login - OTP Code';
    $body = getOTPEmailTemplate($otp_code, $full_name);

    try {
        sendOtpViaGmailSmtp($smtp_config, $email, $subject, $body);
        return true;
    } catch (Exception $e) {
        error_log('OTP SMTP error: ' . $e->getMessage());

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['debug_otp'] = $otp_code;
        $_SESSION['debug_email'] = $email;
        $_SESSION['debug_error'] = $e->getMessage();
        return false;
    }
}

function sendOtpViaGmailSmtp(array $config, string $to_email, string $subject, string $body) {
    $errno = $errstr = null;
    $connection = fsockopen($config['host'], $config['port'], $errno, $errstr, 30);
    if (!$connection) {
        throw new Exception("Unable to connect to SMTP server: {$errstr} ({$errno})");
    }

    stream_set_timeout($connection, 30);

    $expect = function($code) use ($connection) {
        $response = '';
        while ($line = fgets($connection, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if (strpos($response, (string)$code) !== 0) {
            throw new Exception("SMTP expected {$code} but got: {$response}");
        }
        return $response;
    };

    $write = function($command) use ($connection) {
        fwrite($connection, $command . "\r\n");
    };

    $expect(220);
    $write('EHLO ' . $config['helo']);
    $expect(250);

    $write('STARTTLS');
    $expect(220);

    if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new Exception('Failed to enable TLS encryption. Ensure openssl extension is enabled.');
    }

    $write('EHLO ' . $config['helo']);
    $expect(250);

    $write('AUTH LOGIN');
    $expect(334);
    $write(base64_encode($config['username']));
    $expect(334);
    $write(base64_encode($config['password']));
    $expect(235);

    $write('MAIL FROM: <' . $config['from_email'] . '>');
    $expect(250);

    $write('RCPT TO: <' . $to_email . '>');
    $expect(250);

    $write('DATA');
    $expect(354);

    $headers = [
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
        'To: ' . $to_email,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $write($message . "\r\n.");
    $expect(250);

    $write('QUIT');
    fclose($connection);
}

function sendOTPEmailBasic($email, $otp_code, $full_name) {
    $subject = 'HR Admin Login - OTP Code';
    $message = getOTPEmailTemplate($otp_code, $full_name);

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type:text/html;charset=UTF-8';
    $headers[] = 'From: HR Admin <noreply@company.com>';

    if (@mail($email, $subject, $message, implode("\r\n", $headers))) {
        return true;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['debug_otp'] = $otp_code;
    $_SESSION['debug_email'] = $email;
    $_SESSION['debug_error'] = 'mail() fallback failed';
    error_log("OTP mail() fallback failed. Showing OTP in dev mode for {$full_name} ({$email})");

    return true;
}

function getOTPEmailTemplate($otp_code, $full_name) {
    return "
    <html>
        <head>
            <title>HR Admin - OTP Code</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; }
                .otp-box { background: white; padding: 20px; text-align: center; margin: 20px 0; border-radius: 10px; border: 2px solid #667eea; }
                .otp-code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 4px; }
                .footer { background: #343a40; color: white; padding: 15px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HR Admin Portal</h1>
                    <p>Login Verification</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>{$full_name}</strong>,</p>
                    <p>Use the verification code below to finish signing in to the HR Admin Portal. This code is valid for the next 5 minutes.</p>
                    <div class='otp-box'>
                        <div>Your verification code is:</div>
                        <div class='otp-code'>{$otp_code}</div>
                    </div>
                    <p style='color:#b45309;'>Do not share this code with anyone. If you did not request it, please contact the IT administrator immediately.</p>
                </div>
                <div class='footer'>
                    This is an automated message. Please do not reply.
                </div>
            </div>
        </body>
    </html>
    ";
}

function getEmailSetupInstructions() {
    return "
        <div class='alert alert-info'>
            <h5><i class='fas fa-info-circle'></i> Email Setup Instructions</h5>
            <p><strong>Development (localhost):</strong> OTP codes will be written to the session and to <code>otp_dev_log.txt</code>.</p>
            <p><strong>Production:</strong></p>
            <ol>
                <li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
                <li>Update SMTP credentials in <code>email_config.php</code></li>
                <li>Replace the placeholders inside <code>$smtp_config</code> with real values</li>
                <li>Test delivery on a staging environment</li>
            </ol>
        </div>
    ";
}
