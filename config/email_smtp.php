<?php
// SMTP Email Configuration using PHPMailer
// This provides more reliable email sending than PHP mail()

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer (you need to install via composer or download manually)
require_once __DIR__ . '/../vendor/autoload.php'; // If using Composer
// OR require_once __DIR__ . '/../phpmailer/PHPMailer.php'; // If using manual download

// Email Settings - UPDATE THESE WITH YOUR CREDENTIALS
define('SMTP_HOST', 'smtp.gmail.com');          // SMTP server
define('SMTP_PORT', 587);                        // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your-16-digit-app-password');    // App password (not regular password)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'AC-TECHNOLOGY');
define('SMTP_ENCRYPTION', 'tls');                // 'tls' or 'ssl'

// System URL for links in emails
define('SYSTEM_URL', 'http://localhost/Inventory_Mgt');

/**
 * Send email using PHPMailer with SMTP
 */
function sendEmailSMTP($to, $subject, $body, $isHtml = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        
        // Log success
        logEmailAttempt($to, $subject, true);
        return true;
        
    } catch (Exception $e) {
        // Log error
        logEmailAttempt($to, $subject, false, $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send email using PHP mail() function (fallback)
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    // Try SMTP first if PHPMailer is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return sendEmailSMTP($to, $subject, $body, $isHtml);
    }
    
    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    
    if ($isHtml) {
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    }
    
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Try to send email
    $result = @mail($to, $subject, $body, $headers);
    
    // Log email attempt
    logEmailAttempt($to, $subject, $result);
    
    return $result;
}

/**
 * Log email attempts for debugging
 */
function logEmailAttempt($to, $subject, $success, $error = '') {
    $log_file = __DIR__ . '/../logs/email.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $status = $success ? 'SUCCESS' : 'FAILED';
    $error_info = $error ? " | Error: $error" : "";
    $log_entry = date('Y-m-d H:i:s') . " | $status | To: $to | Subject: $subject$error_info\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $username, $resetToken) {
    $resetLink = SYSTEM_URL . "/reset_password.php?token=" . $resetToken;
    
    $subject = "Password Reset Request - AC-TECHNOLOGY";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AC-TECHNOLOGY</h1>
                <p>Inventory Management System</p>
            </div>
            <div class='content'>
                <h2>Password Reset Request</h2>
                <p>Hello <strong>$username</strong>,</p>
                <p>We received a request to reset your password. Click the button below to reset it:</p>
                <p style='text-align: center;'>
                    <a href='$resetLink' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; background: #edf2f7; padding: 10px; border-radius: 5px;'>$resetLink</p>
                <p><strong>This link will expire in 1 hour.</strong></p>
                <p>If you didn't request this, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " AC-TECHNOLOGY. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send new user welcome email with credentials
 */
function sendNewUserEmail($email, $username, $password, $fullName) {
    $loginLink = SYSTEM_URL . "/login.php";
    
    $subject = "Welcome to AC-TECHNOLOGY - Your Account Details";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
            .credentials { background: #fff; border: 2px solid #667eea; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .credentials h3 { color: #667eea; margin-top: 0; }
            .button { display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
            .warning { background: #fff5f5; border-left: 4px solid #fc8181; padding: 10px 15px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AC-TECHNOLOGY</h1>
                <p>Inventory Management System</p>
            </div>
            <div class='content'>
                <h2>Welcome, $fullName!</h2>
                <p>Your account has been created successfully. Here are your login credentials:</p>
                
                <div class='credentials'>
                    <h3>Your Login Details</h3>
                    <p><strong>Username:</strong> $username</p>
                    <p><strong>Password:</strong> $password</p>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong> Please change your password after your first login for security purposes.
                </div>
                
                <p style='text-align: center;'>
                    <a href='$loginLink' class='button'>Login to Your Account</a>
                </p>
                
                <p>If you have any questions, please contact your system administrator.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " AC-TECHNOLOGY. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send backup notification email
 */
function sendBackupNotificationEmail($adminEmail, $backupFile, $backupSize, $status) {
    $subject = "Database Backup " . ($status ? "Successful" : "Failed") . " - AC-TECHNOLOGY";
    
    $statusText = $status ? "completed successfully" : "failed";
    $statusColor = $status ? "#48bb78" : "#fc8181";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
            .status { background: $statusColor; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 18px; margin: 20px 0; }
            .details { background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; }
            .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AC-TECHNOLOGY</h1>
                <p>Database Backup Notification</p>
            </div>
            <div class='content'>
                <div class='status'>
                    Backup $statusText
                </div>
                
                <div class='details'>
                    <p><strong>Date/Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p><strong>Backup File:</strong> $backupFile</p>
                    <p><strong>File Size:</strong> $backupSize</p>
                </div>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " AC-TECHNOLOGY. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($adminEmail, $subject, $body);
}
?>
