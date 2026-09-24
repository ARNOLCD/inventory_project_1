<?php
require_once 'config/email.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = trim($_POST['test_email']);
    
    if (empty($test_email)) {
        $error = 'Please enter an email address to test.';
    } else {
        // Send test email
        $subject = 'Email Test - Sims-Tech Zambia Inventory System';
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
                .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Sims-Tech Zambia</h1>
                    <p>Inventory Management System</p>
                </div>
                <div class='content'>
                    <h2>📧 Email Test Successful!</h2>
                    <p>This is a test email to verify that your email configuration is working correctly.</p>
                    <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p><strong>SMTP Server:</strong> " . SMTP_HOST . "</p>
                    <p><strong>From Email:</strong> " . SMTP_FROM_EMAIL . "</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Sims-Tech Zambia. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $result = sendEmail($test_email, $subject, $body);
        
        if ($result) {
            $message = "✅ Test email sent successfully to $test_email! Check your inbox (and spam folder).";
        } else {
            // Check error log for details
            $error_log = __DIR__ . '/logs/email_errors.log';
            $error_details = '';
            if (file_exists($error_log)) {
                $lines = file($error_log);
                $error_details = end($lines);
            }
            $error = "❌ Failed to send email. " . ($error_details ? "Error: " . htmlspecialchars($error_details) : "Check logs/email_errors.log for details.");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .test-container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .test-card { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .config-section { background: #f7fafc; border-radius: 10px; padding: 20px; margin: 20px 0; }
        .config-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .config-item:last-child { border-bottom: none; }
        .config-label { font-weight: 600; color: #4a5568; }
        .config-value { color: #2d3748; font-family: monospace; background: #edf2f7; padding: 2px 8px; border-radius: 4px; }
        .steps { background: #ebf8ff; border-left: 4px solid #4299e1; padding: 20px; margin: 20px 0; }
        .steps h3 { color: #2b6cb0; margin-top: 0; }
        .steps ol { margin: 10px 0; padding-left: 20px; }
        .steps li { margin: 10px 0; }
        .warning { background: #fff5f5; border-left: 4px solid #fc8181; padding: 15px; margin: 20px 0; }
        .success { background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-card">
            <h1 style="text-align: center; color: #1a365d; margin-bottom: 10px;">
                <i class="fas fa-envelope"></i> Email Configuration Test
            </h1>
            <p style="text-align: center; color: #718096; margin-bottom: 30px;">
                Test your email configuration before using the system features
            </p>
            
            <?php if ($message): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="warning">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Current Configuration -->
            <div class="config-section">
                <h3><i class="fas fa-cog"></i> Current Email Configuration</h3>
                <div class="config-item">
                    <span class="config-label">SMTP Host:</span>
                    <span class="config-value"><?php echo SMTP_HOST; ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">SMTP Port:</span>
                    <span class="config-value"><?php echo SMTP_PORT; ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">SMTP Username:</span>
                    <span class="config-value"><?php echo SMTP_USERNAME; ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">SMTP Password:</span>
                    <span class="config-value"><?php echo str_repeat('*', strlen(SMTP_PASSWORD)); ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">From Email:</span>
                    <span class="config-value"><?php echo SMTP_FROM_EMAIL; ?></span>
                </div>
                <div class="config-item">
                    <span class="config-label">Encryption:</span>
                    <span class="config-value"><?php echo SMTP_ENCRYPTION; ?></span>
                </div>
            </div>
            
            <!-- Setup Instructions -->
            <div class="steps">
                <h3><i class="fas fa-list-ol"></i> Gmail Setup Instructions</h3>
                <ol>
                    <li><strong>Enable 2-Factor Authentication</strong> in your Google Account settings</li>
                    <li><strong>Generate App Password:</strong> Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a></li>
                    <li>Select "Mail" for app and "Other (Custom name)" for device</li>
                    <li>Enter "Sims-Tech Zambia Inventory" as the name</li>
                    <li><strong>Copy the 16-character password</strong> and update the SMTP_PASSWORD in config/email.php</li>
                    <li>Update SMTP_USERNAME and SMTP_FROM_EMAIL with your Gmail address</li>
                </ol>
            </div>
            
            <!-- Test Form -->
            <form method="POST" style="margin-top: 30px;">
                <div class="form-group">
                    <label for="test_email"><i class="fas fa-envelope"></i> Test Email Address:</label>
                    <input type="email" id="test_email" name="test_email" class="form-control" 
                           placeholder="Enter your email address to test" required
                           value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Send Test Email
                </button>
            </form>
            
            <!-- Troubleshooting -->
            <div class="warning">
                <h3><i class="fas fa-tools"></i> Troubleshooting</h3>
                <p><strong>If emails don't send:</strong></p>
                <ul>
                    <li>Check that your Gmail account has 2FA enabled</li>
                    <li>Verify you're using an App Password (not your regular password)</li>
                    <li>Check if your server blocks outbound SMTP connections</li>
                    <li>View email logs in <code>logs/email.log</code> for detailed error messages</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
