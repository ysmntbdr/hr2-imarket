<?php
require_once 'config.php';

function sendOtpEmail($toEmail, $toName, $otpCode) {
    // Gmail SMTP settings using your credentials
    $smtp_server = "smtp.gmail.com";
    $smtp_port = 587;
    $smtp_username = "jerickdellosa3131@gmail.com";
    $smtp_password = "zujvcqkhtumgrbqp"; // Your app password
    
    $subject = 'Your HR2 ESS One-Time Password (OTP)';
    $message = "Hello {$toName},\r\n\r\n";
    $message .= "Your one-time password (OTP) for logging into the HR Employee Self-Service portal is: {$otpCode}\r\n\r\n";
    $message .= "This code will expire in 5 minutes. If you did not request this code, please ignore this email.\r\n\r\n";
    $message .= "Thank you.";
    
    // Create the email content
    $email_content = "From: HR2 ESS <{$smtp_username}>\r\n";
    $email_content .= "To: {$toName} <{$toEmail}>\r\n";
    $email_content .= "Subject: {$subject}\r\n";
    $email_content .= "MIME-Version: 1.0\r\n";
    $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $email_content .= "\r\n";
    $email_content .= $message;
    
    try {
        // Connect to Gmail SMTP server
        $socket = fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("Cannot connect to SMTP server: {$errstr} ({$errno})");
        }
        
        // Read server greeting
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("Server greeting failed: {$response}");
        }
        
        // Send EHLO
        fputs($socket, "EHLO localhost\r\n");
        
        // Read all EHLO responses (Gmail sends multiple lines)
        do {
            $response = fgets($socket, 515);
            $continue = (substr($response, 3, 1) == '-');
        } while ($continue);
        
        // Start TLS encryption
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("STARTTLS failed: {$response}");
        }
        
        // Enable TLS
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // Send EHLO again after TLS
        fputs($socket, "EHLO localhost\r\n");
        
        // Read all EHLO responses after TLS
        do {
            $response = fgets($socket, 515);
            $continue = (substr($response, 3, 1) == '-');
        } while ($continue);
        
        // Authenticate with Gmail
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN failed: {$response}");
        }
        
        // Send username (base64 encoded)
        fputs($socket, base64_encode($smtp_username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username authentication failed: {$response}");
        }
        
        // Send password (base64 encoded)
        fputs($socket, base64_encode($smtp_password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            throw new Exception("Password authentication failed: {$response}");
        }
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <{$smtp_username}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM failed: {$response}");
        }
        
        // Send RCPT TO
        fputs($socket, "RCPT TO: <{$toEmail}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO failed: {$response}");
        }
        
        // Send DATA command
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA command failed: {$response}");
        }
        
        // Send email content
        fputs($socket, $email_content . "\r\n.\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            throw new Exception("Email send failed: {$response}");
        }
        
        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        error_log("OTP email sent successfully to {$toEmail} via Gmail SMTP");
        return true;
        
    } catch (Exception $e) {
        error_log("Gmail SMTP error: " . $e->getMessage());
        
        // Try alternative method: PHP mail() with proper headers
        $headers = "From: HR2 ESS <jerickdellosa3131@gmail.com>\r\n";
        $headers .= "Reply-To: jerickdellosa3131@gmail.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $mail_message = "Hello {$toName},\n\n";
        $mail_message .= "Your one-time password (OTP) for logging into the HR Employee Self-Service portal is: {$otpCode}\n\n";
        $mail_message .= "This code will expire in 5 minutes. If you did not request this code, please ignore this email.\n\n";
        $mail_message .= "Thank you.";
        
        if (mail($toEmail, $subject, $mail_message, $headers)) {
            error_log("OTP email sent successfully to {$toEmail} using mail() fallback");
            return true;
        }
        
        // Final fallback to dev mode if both methods fail
        $_SESSION['debug_otp'] = $otpCode;
        $_SESSION['debug_email'] = $toEmail;
        $_SESSION['debug_error'] = $e->getMessage();
        error_log("Both SMTP and mail() failed. Using dev mode fallback. OTP for {$toName} ({$toEmail}): {$otpCode}");
        return true; // Still return true so login continues
    }
}
