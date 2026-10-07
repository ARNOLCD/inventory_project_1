<?php
// Email for the inventory system. Uses SMTP for sending emails.
// SMTP settings are managed by the admin under System Information > Email and stored in the
// database - never put credentials in this file.
require_once __DIR__ . '/database.php';

define('SMTP_HOST', getSetting('smtp_host', 'smtp.gmail.com'));
define('SMTP_PORT', (int)getSetting('smtp_port', '587'));
define('SMTP_USERNAME', getSetting('smtp_username'));
define('SMTP_PASSWORD', preg_replace('/\s+/', '', getSetting('smtp_password')));
define('SMTP_FROM_EMAIL', getSetting('smtp_from_email', SMTP_USERNAME));
define('SMTP_FROM_NAME', getSetting('smtp_from_name', companyName()));
define('SMTP_ENCRYPTION', getSetting('smtp_encryption', 'tls'));

// System URL for links in emails
define('SYSTEM_URL', appUrl());

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
    $encryption = SMTP_ENCRYPTION;

    if ($host === '' || $username === '' || $password === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError($password === '' ? 'SMTP is not configured (no password set in System Information > Email)' : "Invalid recipient or SMTP settings for: $to");
        return false;
    }

    // Build email headers and body (encode non-ASCII names/subjects, dot-stuff the body per RFC 5321)
    $contentType = $isHtml ? 'text/html' : 'text/plain';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: $contentType; charset=UTF-8\r\n";
    $headers .= "From: " . mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: " . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $body));
    $message = $headers . "\r\n" . $body;
    
    try {
        // Connect to SMTP server (implicit SSL on port 465, STARTTLS otherwise)
        $socket = @fsockopen(($encryption === 'ssl' ? 'ssl://' : '') . $host, $port, $errno, $errstr, 15);
        if (!$socket) {
            logEmailError("Connection failed: $errstr ($errno)");
            return false;
        }
        
        // Set stream timeout
        stream_set_timeout($socket, 15);
        
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
        
        if ($encryption === 'tls') {
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
 * Queue an email. Queued emails are sent after the page has been delivered to the browser,
 * so users never wait for the mail server. Failed emails are retried on later requests.
 */
function queueEmail($to, $subject, $body) {
    global $conn;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $stmt = $conn->prepare("INSERT INTO email_queue (to_email, subject, body) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $to, $subject, $body);
    $stmt->execute();
    scheduleEmailQueue();
    return true;
}

function scheduleEmailQueue() {
    static $scheduled = false;
    if (!$scheduled) {
        $scheduled = true;
        register_shutdown_function(function () {
            finishResponseEarly();
            processEmailQueue();
        });
    }
}

// Deliver the page to the browser now and keep running in the background
function finishResponseEarly() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ignore_user_abort(true);
    set_time_limit(120);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }
    if (!headers_sent()) {
        $content = '';
        while (ob_get_level() > 0) {
            $content = ob_get_clean() . $content;
        }
        header('Connection: close');
        header('Content-Length: ' . strlen($content));
        echo $content;
    } else {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
    }
    flush();
}

function processEmailQueue($limit = 20) {
    global $conn;
    $lock = $conn->query("SELECT GET_LOCK('inventory_email_queue', 0) AS l")->fetch_assoc();
    if ((int)($lock['l'] ?? 0) !== 1) {
        return;
    }
    try {
        $emails = $conn->query("SELECT id, to_email, subject, body, attempts FROM email_queue WHERE status = 'pending' AND attempts < 3 ORDER BY id LIMIT " . (int)$limit);
        $update = $conn->prepare("UPDATE email_queue SET status = ?, attempts = attempts + 1, sent_at = IF(? = 'sent', NOW(), NULL), last_error = ? WHERE id = ?");
        while ($email = $emails->fetch_assoc()) {
            $sent = sendEmail($email['to_email'], $email['subject'], $email['body']);
            $status = $sent ? 'sent' : ($email['attempts'] + 1 >= 3 ? 'failed' : 'pending');
            $last_error = $sent ? null : 'Send failed - see logs/email_errors.log';
            $update->bind_param("sssi", $status, $status, $last_error, $email['id']);
            $update->execute();
        }
    } finally {
        $conn->query("DO RELEASE_LOCK('inventory_email_queue')");
    }
}

// Retry emails left over from earlier requests (e.g. mail server was temporarily down)
function retryPendingEmails() {
    global $conn;
    $row = $conn->query("SELECT COUNT(*) AS c FROM email_queue WHERE status = 'pending' AND attempts < 3 AND created_at < NOW() - INTERVAL 1 MINUTE")->fetch_assoc();
    if ((int)$row['c'] > 0) {
        scheduleEmailQueue();
    }
}

/**
 * Standard branded email layout used by all system emails
 */
function emailLayout($title, $subtitle, $contentHtml) {
    $name = e(companyName());
    $logo = e(appUrl() . '/' . companyLogo());
    $contact = companyContactEmail();
    $phone = companyInfo()['phone'] ?? '';
    $contactLine = trim(($contact ? 'Email: ' . e($contact) : '') . ($phone ? ' | Phone: ' . e($phone) : ''), ' |');
    return "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                <img src='$logo' alt='$name' style='max-height: 60px; margin-bottom: 10px; background: #fff; border-radius: 6px; padding: 4px;'>
                <h1 style='margin: 0;'>$name</h1>
                <p style='margin: 5px 0 0;'>" . e($subtitle) . "</p>
            </div>
            <div style='background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0;'>
                <h2 style='margin-top: 0;'>" . e($title) . "</h2>
                $contentHtml
            </div>
            <div style='background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>&copy; " . date('Y') . " $name. All rights reserved.</p>
                " . ($contactLine ? "<p style='margin: 5px 0 0;'>$contactLine</p>" : '') . "
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Email every internal user (admin, employee, technician, sales)
 */
function queueEmailToStaff($subject, $body) {
    global $conn;
    $roles = "'" . implode("','", STAFF_ROLES) . "'";
    $staff = $conn->query("SELECT DISTINCT email FROM users WHERE role IN ($roles) AND email IS NOT NULL AND email <> ''");
    while ($member = $staff->fetch_assoc()) {
        queueEmail($member['email'], $subject, $body);
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $username, $resetToken) {
    $resetLink = e(SYSTEM_URL . "/reset_password.php?token=" . urlencode($resetToken));
    $content = "
        <p>Hello <strong>" . e($username) . "</strong>,</p>
        <p>We received a request to reset your password. Click the button below to reset it:</p>
        <p style='text-align: center;'>
            <a href='$resetLink' style='display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0;'>Reset Password</a>
        </p>
        <p>Or copy and paste this link into your browser:</p>
        <p style='word-break: break-all; background: #edf2f7; padding: 10px; border-radius: 5px;'>$resetLink</p>
        <p><strong>This link will expire in 1 hour.</strong></p>
        <p>If you didn't request this, please ignore this email.</p>";

    return queueEmail($email, "Password Reset Request - " . companyName(), emailLayout('Password Reset Request', 'Account Security', $content));
}

/**
 * Send new user welcome email with credentials
 */
function sendNewUserEmail($email, $username, $password, $fullName) {
    $loginLink = e(SYSTEM_URL . "/login.php");
    $content = "
        <p>Hello <strong>" . e($fullName) . "</strong>,</p>
        <p>An account has been created for you. Here are your login credentials:</p>
        <div style='background: #fff; border: 2px solid #667eea; padding: 20px; border-radius: 10px; margin: 20px 0;'>
            <p><strong>Username:</strong> " . e($username) . "</p>
            <p><strong>Temporary password:</strong> " . e($password) . "</p>
        </div>
        <div style='background: #fff5f5; border-left: 4px solid #fc8181; padding: 10px 15px; margin: 15px 0;'>
            <strong>Security notice:</strong> You will be asked to choose a new password the first time you log in.
        </div>
        <p style='text-align: center;'>
            <a href='$loginLink' style='display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0;'>Login to Your Account</a>
        </p>
        <p>If you have any questions, please contact your system administrator.</p>";

    return queueEmail($email, "Welcome to " . companyName() . " - Your Account Details", emailLayout('Welcome, ' . $fullName . '!', 'Your Account', $content));
}

/**
 * Email a user their new credentials after an admin resets their password
 */
function sendAdminPasswordResetEmail($email, $username, $password, $fullName) {
    $loginLink = e(SYSTEM_URL . "/login.php");
    $content = "
        <p>Hello <strong>" . e($fullName) . "</strong>,</p>
        <p>An administrator has reset the password on your account. Here are your login credentials:</p>
        <div style='background: #fff; border: 2px solid #667eea; padding: 20px; border-radius: 10px; margin: 20px 0;'>
            <p><strong>Username:</strong> " . e($username) . "</p>
            <p><strong>Temporary password:</strong> " . e($password) . "</p>
        </div>
        <div style='background: #fff5f5; border-left: 4px solid #fc8181; padding: 10px 15px; margin: 15px 0;'>
            <strong>Security notice:</strong> You will be asked to choose a new password the next time you log in.
        </div>
        <p style='text-align: center;'>
            <a href='$loginLink' style='display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0;'>Login to Your Account</a>
        </p>
        <p>If you did not expect this change, please contact your system administrator immediately.</p>";

    return queueEmail($email, "Password Reset - " . companyName(), emailLayout('Password Reset', 'Account Security', $content));
}

/**
 * Send repair status update email to customer
 */
function sendRepairStatusEmail($customerEmail, $customerName, $ticketNumber, $status, $deviceInfo, $notes = '') {
    $statusMessages = [
        'pending_approval' => ['Repair Request Received', 'We have received your repair request. Our team will review it and let you know whether it has been accepted.', '#718096'],
        'booked' => ['Repair Request Accepted', 'Your repair request has been accepted and booked. Please bring your device to our workshop.', '#3182ce'],
        'rejected' => ['Repair Request Declined', 'Unfortunately we are unable to accept your repair request. Please see the reason below or contact us for more information.', '#e53e3e'],
        'item_received' => ['Device Received', 'We have received your device at our workshop and it is queued for repair.', '#319795'],
        'in_progress' => ['Repair In Progress', 'Your device is currently being repaired by our technicians.', '#dd6b20'],
        'completed' => ['Repair Completed', 'Your device repair has been completed successfully.', '#38a169'],
        'in_transit' => ['Device In Transit', 'Your repaired device is on its way back to you.', '#d69e2e'],
        'delivered' => ['Device Delivered', 'Your device has been delivered and is ready for pickup.', '#805ad5'],
        'cancelled' => ['Repair Cancelled', 'Your repair request has been cancelled.', '#e53e3e'],
    ];
    [$title, $text, $color] = $statusMessages[$status] ?? $statusMessages['booked'];

    $notesHtml = $notes !== '' ? "<div style='background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; margin: 15px 0;'>
        <strong>" . ($status === 'rejected' ? 'Reason' : 'Notes') . ":</strong>
        <p style='margin: 5px 0 0 0;'>" . nl2br(e($notes)) . "</p>
    </div>" : '';

    $content = "
        <p>Hello <strong>" . e($customerName) . "</strong>,</p>
        <p>$text</p>
        <div style='background: $color; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 18px; margin: 20px 0;'>
            Ticket: " . e($ticketNumber) . "
        </div>
        <div style='background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0;'>
            <p><strong>Device:</strong> " . e($deviceInfo) . "</p>
            <p><strong>Status:</strong> " . e(repairStatusLabel($status)) . "</p>
            <p><strong>Updated:</strong> " . date('M d, Y H:i') . "</p>
        </div>
        $notesHtml
        <p style='text-align: center; margin-top: 20px;'>
            <a href='" . e(SYSTEM_URL . '/customer_dashboard.php') . "' style='display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>Track Your Repair</a>
        </p>";

    return queueEmail($customerEmail, "Repair Status Update: $title - Ticket $ticketNumber", emailLayout($title, 'Repair Status Update', $content));
}

/**
 * Load a repair with the best customer contact (account email, falling back to the email captured on the ticket)
 */
function getRepairForNotification($conn, $id) {
    return $conn->query("SELECT r.*,
                                COALESCE(NULLIF(u.email, ''), r.customer_email) as notify_email,
                                COALESCE(NULLIF(u.full_name, ''), r.customer_name) as notify_name
                         FROM repairs r
                         LEFT JOIN users u ON r.customer_id = u.id
                         WHERE r.id = " . intval($id))->fetch_assoc();
}

function getRepairDeviceInfo($repair) {
    $deviceInfo = $repair['device_type'];
    if ($repair['device_brand']) $deviceInfo .= ' - ' . $repair['device_brand'];
    if ($repair['device_model']) $deviceInfo .= ' ' . $repair['device_model'];
    return $deviceInfo;
}

/**
 * Email the customer about a repair status change
 */
function notifyRepairCustomer($conn, $id, $status, $notes = '') {
    $repair = getRepairForNotification($conn, $id);
    if (!$repair || !filter_var($repair['notify_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return sendRepairStatusEmail($repair['notify_email'], $repair['notify_name'], $repair['ticket_number'], $status, getRepairDeviceInfo($repair), $notes);
}

/**
 * Email all internal users (admin, employee, technician, sales) about a new customer repair request
 */
function notifyStaffOfRepairRequest($conn, $id) {
    $repair = getRepairForNotification($conn, $id);
    if (!$repair || getSetting('repair_request_email_alerts', '1') !== '1') {
        return;
    }

    $content = "
        <p>A customer has submitted a new repair request that needs to be accepted or denied.</p>
        <div style='background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0;'>
            <p><strong>Ticket:</strong> " . e($repair['ticket_number']) . "</p>
            <p><strong>Customer:</strong> " . e($repair['notify_name']) . "</p>
            <p><strong>Phone:</strong> " . e($repair['customer_phone']) . "</p>
            <p><strong>Email:</strong> " . e($repair['notify_email']) . "</p>
            <p><strong>Device:</strong> " . e(getRepairDeviceInfo($repair)) . "</p>
            <p><strong>Problem:</strong> " . nl2br(e($repair['problem_description'])) . "</p>
        </div>
        <p style='text-align: center; margin-top: 20px;'>
            <a href='" . e(SYSTEM_URL . '/repair_requests.php') . "' style='display: inline-block; background: #3182ce; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>Review Request</a>
        </p>";

    queueEmailToStaff("New Repair Request - Ticket {$repair['ticket_number']}", emailLayout('New Repair Request Awaiting Review', 'Repair Requests', $content));
}

/**
 * Send backup notification email
 */
function sendBackupNotificationEmail($adminEmail, $backupFile, $backupSize, $status) {
    $subject = "Database Backup " . ($status ? "Successful" : "Failed") . " - " . companyName();
    
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
                <h1>" . e(companyName()) . "</h1>
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
                <p>&copy; " . date('Y') . " " . e(companyName()) . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($adminEmail, $subject, $body);
}

retryPendingEmails();
?>
