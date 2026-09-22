<?php
// Email Configuration for AC-TECHNOLOGY Inventory System
// Uses SMTP for sending emails

// Email Settings - Update these with your SMTP credentials
define('SMTP_HOST', 'smtp.gmail.com');          // SMTP server
define('SMTP_PORT', 587);                        // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_USERNAME', 'Arnoldchama36@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'djfvfrvcfmueidsz');    // App password (not regular password)
define('SMTP_FROM_EMAIL', 'Arnoldchama36@gmail.com');
define('SMTP_FROM_NAME', 'AC-TECHNOLOGY');
define('SMTP_ENCRYPTION', 'tls');                // 'tls' or 'ssl'

// System URL for links in emails
define('SYSTEM_URL', 'http://localhost/Inventory_Mgt');

/**
 * Send email using SMTP with fsockopen
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    // Try SMTP first
    $result = sendEmailSMTP($to, $subject, $body, $isHtml);
    
    // Log email attempt
    logEmailAttempt($to, $subject, $result);
    
    return $result;
}

/**
 * Send email via SMTP using sockets
 */
function sendEmailSMTP($to, $subject, $body, $isHtml = true) {
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;
    $from = SMTP_FROM_EMAIL;
    $fromName = SMTP_FROM_NAME;
    
    // Build email headers and body
    $contentType = $isHtml ? 'text/html' : 'text/plain';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: $contentType; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    
    $message = $headers . "\r\n" . $body;
    
    try {
        // Connect to SMTP server
        $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        if (!$socket) {
            logEmailError("Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Set stream timeout
        stream_set_timeout($socket, 30);
        
        // Read greeting
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            logEmailError("Invalid greeting: $response");
            return false;
        }
        
        // Send EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // Start TLS
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            logEmailError("STARTTLS failed: $response");
            return false;
        }
        
        // Enable crypto
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            logEmailError("TLS encryption failed");
            return false;
        }
        
        // Send EHLO again after TLS
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            logEmailError("AUTH LOGIN failed: $response");
            return false;
        }
        
        // Send username
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            logEmailError("Username rejected: $response");
            return false;
        }
        
        // Send password
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            logEmailError("Authentication failed: $response");
            return false;
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM:<$from>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            logEmailError("MAIL FROM failed: $response");
            return false;
        }
        
        // RCPT TO
        fputs($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            logEmailError("RCPT TO failed: $response");
            return false;
        }
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            logEmailError("DATA failed: $response");
            return false;
        }
        
        // Send message
        fputs($socket, $message . "\r\n.\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            logEmailError("Message send failed: $response");
            return false;
        }
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        logEmailError("Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Log email errors
 */
function logEmailError($error) {
    $log_file = __DIR__ . '/../logs/email_errors.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . " | ERROR | $error\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log email attempts for debugging
 */
function logEmailAttempt($to, $subject, $success) {
    $log_file = __DIR__ . '/../logs/email.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $status = $success ? 'SUCCESS' : 'FAILED';
    $log_entry = date('Y-m-d H:i:s') . " | $status | To: $to | Subject: $subject\n";
    
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
 * Send repair status update email to customer
 */
function sendRepairStatusEmail($customerEmail, $customerName, $ticketNumber, $status, $deviceInfo, $notes = '') {
    $statusMessages = [
        'booked' => [
            'title' => 'Repair Booked',
            'message' => 'Your repair request has been successfully booked.',
            'color' => '#3182ce',
            'icon' => 'fa-clipboard-list'
        ],
        'in_progress' => [
            'title' => 'Repair In Progress',
            'message' => 'Your device is currently being repaired by our technicians.',
            'color' => '#dd6b20',
            'icon' => 'fa-wrench'
        ],
        'completed' => [
            'title' => 'Repair Completed',
            'message' => 'Your device repair has been completed successfully.',
            'color' => '#38a169',
            'icon' => 'fa-check-circle'
        ],
        'delivered' => [
            'title' => 'Device Delivered',
            'message' => 'Your device has been delivered and is ready for pickup.',
            'color' => '#805ad5',
            'icon' => 'fa-hand-holding'
        ],
        'cancelled' => [
            'title' => 'Repair Cancelled',
            'message' => 'Your repair request has been cancelled.',
            'color' => '#e53e3e',
            'icon' => 'fa-times-circle'
        ]
    ];
    
    $statusInfo = $statusMessages[$status] ?? $statusMessages['booked'];
    
    $subject = "Repair Status Update: {$statusInfo['title']} - Ticket $ticketNumber";
    
    $notesHtml = $notes ? "<div style='background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; margin: 15px 0;'>
        <strong>Notes:</strong>
        <p style='margin: 5px 0 0 0;'>$notes</p>
    </div>" : '';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
            .status-badge { background: {$statusInfo['color']}; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 18px; margin: 20px 0; }
            .details { background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; }
            .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AC-TECHNOLOGY</h1>
                <p>Repair Status Update</p>
            </div>
            <div class='content'>
                <h2>{$statusInfo['title']}</h2>
                <p>Hello <strong>$customerName</strong>,</p>
                <p>{$statusInfo['message']}</p>
                
                <div class='status-badge'>
                    <i class='fas {$statusInfo['icon']}'></i> Ticket: $ticketNumber
                </div>
                
                <div class='details'>
                    <p><strong>Device:</strong> $deviceInfo</p>
                    <p><strong>Status:</strong> " . ucfirst(str_replace('_', ' ', $status)) . "</p>
                    <p><strong>Updated:</strong> " . date('M d, Y H:i') . "</p>
                </div>
                
                $notesHtml
                
                <p>You can track your repair status by logging into your account.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " AC-TECHNOLOGY. All rights reserved.</p>
                <p>For questions, contact us at info@actechnology.co.zm</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($customerEmail, $subject, $body);
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
