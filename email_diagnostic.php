<?php
// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to load configurations safely
$email_config_loaded = false;
try {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/email.php';
    
    // Redirect customers from diagnostic tools
    if (isCustomer()) {
        header('Location: customer_dashboard.php');
        exit();
    }
    
    $email_config_loaded = true;
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading email configuration: " . $e->getMessage() . "</p>";
}

echo "<h1>📧 Email Diagnostic Tool</h1>";
echo "<p>Troubleshooting why emails aren't being sent...</p>";

// Check 1: Basic PHP mail function
echo "<h2>1. PHP Mail Function Test</h2>";
$test_email = 'test@example.com';
$subject = 'PHP Mail Test';
$message = 'This is a test of PHP mail() function.';
$headers = 'From: test@localhost';

$mail_result = @mail($test_email, $subject, $message, $headers);

if ($mail_result) {
    echo "<p style='color: green;'>✅ PHP mail() function is working</p>";
} else {
    echo "<p style='color: red;'>❌ PHP mail() function failed</p>";
    echo "<p><strong>Possible causes:</strong></p>";
    echo "<ul>";
    echo "<li>SMTP server not configured in php.ini</li>";
    echo "<li>sendmail_path not set correctly</li>";
    echo "<li>Firewall blocking outbound connections</li>";
    echo "<li>Hosting provider disabled mail() function</li>";
    echo "</ul>";
}

// Check 2: Email configuration
echo "<h2>2. Email Configuration Check</h2>";

$config_checks = [];
if ($email_config_loaded && defined('SMTP_HOST')) {
    $config_checks = [
        'SMTP_HOST' => SMTP_HOST,
        'SMTP_PORT' => SMTP_PORT,
        'SMTP_USERNAME' => SMTP_USERNAME,
        'SMTP_PASSWORD' => defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD) ? 'SET (' . strlen(SMTP_PASSWORD) . ' chars)' : 'NOT SET',
        'SMTP_FROM_EMAIL' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NOT SET',
        'SMTP_FROM_NAME' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NOT SET',
        'SMTP_ENCRYPTION' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'NOT SET'
    ];
} else {
    echo "<p style='color: red;'>❌ Email configuration not loaded properly</p>";
}

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

foreach ($config_checks as $setting => $value) {
    $status = 'OK';
    $color = 'green';
    
    if (empty($value) || $value === 'your-email@gmail.com' || $value === 'your-16-digit-app-password') {
        $status = 'NEEDS CONFIGURATION';
        $color = 'red';
    }
    
    echo "<tr style='color: $color;'>";
    echo "<td>$setting</td>";
    echo "<td>" . htmlspecialchars($value) . "</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table>";

// Check 3: Server capabilities
echo "<h2>3. Server Capabilities Check</h2>";

$checks = [];

// Check if OpenSSL is loaded (for TLS/SSL)
$checks['openssl'] = extension_loaded('openssl');

// Check if sockets are available
$checks['sockets'] = extension_loaded('sockets');

// Check if allow_url_fopen is enabled
$checks['allow_url_fopen'] = ini_get('allow_url_fopen');

// Check SMTP port connectivity
if ($email_config_loaded && defined('SMTP_HOST') && defined('SMTP_PORT')) {
    $smtp_host = SMTP_HOST;
    $smtp_port = SMTP_PORT;
    $connection = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
    $checks['smtp_connection'] = $connection !== false;
    if ($connection) {
        fclose($connection);
    }
} else {
    $checks['smtp_connection'] = false;
}

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Capability</th><th>Status</th><th>Details</th></tr>";

foreach ($checks as $check => $result) {
    $status = $result ? '✅ AVAILABLE' : '❌ NOT AVAILABLE';
    $color = $result ? 'green' : 'red';
    
    $details = '';
    switch ($check) {
        case 'openssl':
            $details = 'Required for TLS/SSL encryption';
            break;
        case 'sockets':
            $details = 'Required for SMTP connections';
            break;
        case 'allow_url_fopen':
            $details = 'Required for external connections';
            break;
        case 'smtp_connection':
            $details = $email_config_loaded && defined('SMTP_HOST') && defined('SMTP_PORT') ? 
                "Connection to " . SMTP_HOST . ":" . SMTP_PORT : "SMTP settings not available";
            break;
    }
    
    echo "<tr style='color: $color;'>";
    echo "<td>$check</td>";
    echo "<td>$status</td>";
    echo "<td>$details</td>";
    echo "</tr>";
}
echo "</table>";

// Check 4: PHPMailer availability
echo "<h2>4. PHPMailer Check</h2>";

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer is available</p>";
} else {
    echo "<p style='color: red;'>❌ PHPMailer not found</p>";
    echo "<p><strong>Solution:</strong> Install PHPMailer via Composer or download manually</p>";
    echo "<pre><code>composer require phpmailer/phpmailer</code></pre>";
}

// Check 5: Email logs
echo "<h2>5. Email Logs</h2>";

$log_file = __DIR__ . '/logs/email.log';
if (file_exists($log_file)) {
    echo "<p><strong>Recent email attempts:</strong></p>";
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace; font-size: 12px;'>";
    $logs = file_get_contents($log_file);
    $lines = explode("\n", $logs);
    $recent_lines = array_slice($lines, -20); // Last 20 lines
    echo implode("\n", $recent_lines);
    echo "</textarea>";
} else {
    echo "<p style='color: orange;'>⚠️ No email logs found (logs/email.log)</p>";
}

// Check 6: Test with different methods
echo "<h2>6. Email Sending Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = trim($_POST['test_email']);
    
    if (!empty($test_email)) {
        echo "<h3>Testing email to: " . htmlspecialchars($test_email) . "</h3>";
        
        // Test 1: PHP mail()
        echo "<p><strong>Testing PHP mail()...</strong></p>";
        $subject = 'Test Email - PHP mail()';
        $message = "This is a test email sent using PHP mail() function.\n\nTime: " . date('Y-m-d H:i:s');
        $headers = "From: " . SMTP_FROM_EMAIL . "\r\n";
        $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $php_mail_result = @mail($test_email, $subject, $message, $headers);
        echo "<p style='color: " . ($php_mail_result ? 'green' : 'red') . ";'>" . ($php_mail_result ? '✅ PHP mail() sent' : '❌ PHP mail() failed') . "</p>";
        
        // Test 2: System sendEmail function
        echo "<p><strong>Testing system sendEmail()...</strong></p>";
        $subject = 'Test Email - System Function';
        $body = "
        <html>
        <body>
            <h2>Email Test</h2>
            <p>This is a test email sent using the system sendEmail() function.</p>
            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>Method:</strong> System sendEmail()</p>
        </body>
        </html>";
        
        $system_mail_result = sendEmail($test_email, $subject, $body);
        echo "<p style='color: " . ($system_mail_result ? 'green' : 'red') . ";'>" . ($system_mail_result ? '✅ System sendEmail() sent' : '❌ System sendEmail() failed') . "</p>";
        
        echo "<hr>";
        echo "<p><strong>Check your inbox (and spam folder) for test emails.</strong></p>";
    }
}

// Recommendations
echo "<h2>7. Troubleshooting Recommendations</h2>";

echo "<div style='background: #f7fafc; padding: 15px; border-radius: 5px; margin: 20px 0;'>";

if (!$email_config_loaded || (defined('SMTP_USERNAME') && SMTP_USERNAME === 'your-email@gmail.com')) {
    echo "<h4 style='color: red;'>🚨 Issue Found: Email Not Configured</h4>";
    echo "<ol>";
    echo "<li>Visit <a href='email_settings.php'>Email Settings</a> to configure your SMTP settings</li>";
    echo "<li>Or edit <code>config/email.php</code> directly</li>";
    echo "<li>Generate a Gmail App Password if using Gmail</li>";
    echo "</ol>";
} elseif (!$checks['smtp_connection']) {
    echo "<h4 style='color: red;'>🚨 Issue Found: SMTP Connection Failed</h4>";
    echo "<ol>";
    echo "<li>Check if SMTP server is correct</li>";
    echo "<li>Verify port number (587 for TLS, 465 for SSL)</li>";
    echo "<li>Check firewall settings</li>";
    echo "<li>Try different encryption (TLS/SSL/None)</li>";
    echo "</ol>";
} elseif (!$checks['openssl']) {
    echo "<h4 style='color: red;'>🚨 Issue Found: OpenSSL Not Available</h4>";
    echo "<ol>";
    echo "<li>Enable OpenSSL extension in php.ini</li>";
    echo "<li>Restart web server</li>";
    echo "</ol>";
} else {
    echo "<h4 style='color: orange;'>⚠️ General Troubleshooting</h4>";
    echo "<ol>";
    echo "<li>Check email logs in <code>logs/email.log</code></li>";
    echo "<li>Verify Gmail App Password (not regular password)</li>";
    echo "<li>Try sending to a different email address</li>";
    echo "<li>Check spam/junk folders</li>";
    echo "<li>Test with a different SMTP provider</li>";
    echo "</ol>";
}

echo "</div>";

// Test form
echo "<h2>8. Send Test Email</h2>";
echo "<form method='POST'>";
echo "<div class='form-group'>";
echo "<label for='test_email'>Email Address:</label>";
echo "<input type='email' id='test_email' name='test_email' required placeholder='Enter email to test'>";
echo "</div>";
echo "<button type='submit' class='btn btn-primary'>📧 Send Test Email</button>";
echo "</form>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='email_settings.php' class='btn btn-secondary'>⚙️ Email Settings</a> ";
echo "<a href='test_email.php' class='btn btn-secondary'>📧 Simple Test</a> ";
echo "<a href='dashboard.php' class='btn btn-secondary'>🏠 Dashboard</a>";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #1a365d; }
h2 { color: #2d3748; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
h3 { color: #4a5568; }
table { width: 100%; margin: 10px 0; }
th, td { padding: 8px 12px; text-align: left; border: 1px solid #e2e8f0; }
th { background: #f7fafc; font-weight: bold; }
.form-group { margin: 15px 0; }
.form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
.form-group input { width: 100%; max-width: 400px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; }
.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #718096; color: white; }
textarea { background: #1a202c; color: #48bb78; border: 1px solid #4a5568; }
</style>
