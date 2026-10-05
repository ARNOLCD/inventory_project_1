<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

// Create email_settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Get current settings
function getEmailSetting($conn, $name, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM email_settings WHERE setting_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

function setEmailSetting($conn, $name, $value) {
    $stmt = $conn->prepare("INSERT INTO email_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $name, $value, $value);
    return $stmt->execute();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_username = trim($_POST['smtp_username']);
    $smtp_password = $_POST['smtp_password'];
    $smtp_from_email = trim($_POST['smtp_from_email']);
    $smtp_from_name = trim($_POST['smtp_from_name']);
    $smtp_encryption = $_POST['smtp_encryption'];
    
    // Validate settings
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_from_email)) {
        $error = 'SMTP Host, Username, and From Email are required.';
    } else {
        // Save settings
        setEmailSetting($conn, 'smtp_host', $smtp_host);
        setEmailSetting($conn, 'smtp_port', $smtp_port);
        setEmailSetting($conn, 'smtp_username', $smtp_username);
        setEmailSetting($conn, 'smtp_password', $smtp_password);
        setEmailSetting($conn, 'smtp_from_email', $smtp_from_email);
        setEmailSetting($conn, 'smtp_from_name', $smtp_from_name);
        setEmailSetting($conn, 'smtp_encryption', $smtp_encryption);
        
        $message = 'Email settings saved successfully!';
        
        // Update the config file with new settings
        updateConfigFile($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name, $smtp_encryption);
    }
}

// Update config file with new settings
function updateConfigFile($host, $port, $username, $password, $from_email, $from_name, $encryption) {
    $config_file = __DIR__ . '/config/email.php';
    $config_content = file_get_contents($config_file);
    
    // Replace the settings in the file
    $config_content = preg_replace("/define\('SMTP_HOST', '[^']*'\)/", "define('SMTP_HOST', '$host')", $config_content);
    $config_content = preg_replace("/define\('SMTP_PORT', [^)]*\)/", "define('SMTP_PORT', $port)", $config_content);
    $config_content = preg_replace("/define\('SMTP_USERNAME', '[^']*'\)/", "define('SMTP_USERNAME', '$username')", $config_content);
    $config_content = preg_replace("/define\('SMTP_PASSWORD', '[^']*'\)/", "define('SMTP_PASSWORD', '$password')", $config_content);
    $config_content = preg_replace("/define\('SMTP_FROM_EMAIL', '[^']*'\)/", "define('SMTP_FROM_EMAIL', '$from_email')", $config_content);
    $config_content = preg_replace("/define\('SMTP_FROM_NAME', '[^']*'\)/", "define('SMTP_FROM_NAME', '$from_name')", $config_content);
    $config_content = preg_replace("/define\('SMTP_ENCRYPTION', '[^']*'\)/", "define('SMTP_ENCRYPTION', '$encryption')", $config_content);
    
    file_put_contents($config_file, $config_content);
}

// Get current settings (from database or defaults)
$smtp_host = getEmailSetting($conn, 'smtp_host', 'smtp.gmail.com');
$smtp_port = getEmailSetting($conn, 'smtp_port', '587');
$smtp_username = getEmailSetting($conn, 'smtp_username', '');
$smtp_password = getEmailSetting($conn, 'smtp_password', '');
$smtp_from_email = getEmailSetting($conn, 'smtp_from_email', '');
$smtp_from_name = getEmailSetting($conn, 'smtp_from_name', 'Sims-Tech Zambia');
$smtp_encryption = getEmailSetting($conn, 'smtp_encryption', 'tls');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .settings-card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .settings-card h3 { margin-top: 0; color: #1a365d; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #4a5568; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .password-field { position: relative; }
        .password-toggle { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #718096; cursor: pointer; padding: 5px; }
        .password-toggle:hover { color: #667eea; }
        .preset-buttons { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .preset-btn { padding: 8px 16px; border: 1px solid #e2e8f0; background: #f7fafc; border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .preset-btn:hover { background: #667eea; color: white; border-color: #667eea; }
        .test-section { background: #f7fafc; border-radius: 10px; padding: 20px; margin-top: 20px; }
        .info-box { background: #ebf8ff; border-left: 4px solid #4299e1; padding: 15px; margin: 20px 0; }
        .warning-box { background: #fff5f5; border-left: 4px solid #fc8181; padding: 15px; margin: 20px 0; }
        .success-box { background: #f0fff4; border-left: 4px solid #48bb78; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="fas fa-envelope"></i> Email Settings</h1>
                    <p>Configure email server settings for system notifications</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="settings-card">
                    <h3><i class="fas fa-cog"></i> SMTP Configuration</h3>
                    
                    <form method="POST">
                        <div class="preset-buttons">
                            <button type="button" class="preset-btn" onclick="loadPreset('gmail')">
                                <i class="fab fa-google"></i> Gmail
                            </button>
                            <button type="button" class="preset-btn" onclick="loadPreset('outlook')">
                                <i class="fab fa-microsoft"></i> Outlook
                            </button>
                            <button type="button" class="preset-btn" onclick="loadPreset('yahoo')">
                                <i class="fab fa-yahoo"></i> Yahoo
                            </button>
                            <button type="button" class="preset-btn" onclick="loadPreset('custom')">
                                <i class="fas fa-server"></i> Custom
                            </button>
                        </div>
                        
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="smtp_host"><i class="fas fa-server"></i> SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" required 
                                       value="<?php echo htmlspecialchars($smtp_host); ?>"
                                       placeholder="smtp.gmail.com">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_port"><i class="fas fa-plug"></i> SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" required 
                                       value="<?php echo htmlspecialchars($smtp_port); ?>"
                                       placeholder="587">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_username"><i class="fas fa-user"></i> SMTP Username</label>
                            <input type="email" id="smtp_username" name="smtp_username" required 
                                   value="<?php echo htmlspecialchars($smtp_username); ?>"
                                   placeholder="your-email@gmail.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_password"><i class="fas fa-lock"></i> SMTP Password</label>
                            <div class="password-field">
                                <input type="password" id="smtp_password" name="smtp_password" 
                                       value="<?php echo htmlspecialchars($smtp_password); ?>"
                                       placeholder="Your app password (not regular password)">
                                <button type="button" class="password-toggle" onclick="togglePassword('smtp_password')">
                                    <i class="fas fa-eye" id="smtp_password-toggle"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid-2">
                            <div class="form-group">
                                <label for="smtp_from_email"><i class="fas fa-envelope"></i> From Email</label>
                                <input type="email" id="smtp_from_email" name="smtp_from_email" required 
                                       value="<?php echo htmlspecialchars($smtp_from_email); ?>"
                                       placeholder="your-email@gmail.com">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_from_name"><i class="fas fa-building"></i> From Name</label>
                                <input type="text" id="smtp_from_name" name="smtp_from_name" required 
                                       value="<?php echo htmlspecialchars($smtp_from_name); ?>"
                                       placeholder="Sims-Tech Zambia">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_encryption"><i class="fas fa-shield-alt"></i> Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption">
                                <option value="tls" <?php echo $smtp_encryption === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo $smtp_encryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo $smtp_encryption === 'none' ? 'selected' : ''; ?>>None (Not Recommended)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
                
                <div class="settings-card">
                    <h3><i class="fas fa-info-circle"></i> Setup Instructions</h3>
                    
                    <div class="info-box">
                        <h4><i class="fab fa-google"></i> Gmail Setup</h4>
                        <ol>
                            <li>Enable 2-Factor Authentication in your Google Account</li>
                            <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">App Passwords</a></li>
                            <li>Select "Mail" + "Other (Custom name)" → Enter "Sims-Tech Zambia Inventory"</li>
                            <li>Copy the 16-character password and use it as SMTP Password</li>
                        </ol>
                    </div>
                    
                    <div class="warning-box">
                        <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                        <ul>
                            <li><strong>Use App Passwords:</strong> Never use your regular email password</li>
                            <li><strong>Check Spam Folder:</strong> Test emails might go to spam initially</li>
                            <li><strong>Server Requirements:</strong> Ensure your server allows outbound SMTP connections</li>
                            <li><strong>Email Logs:</strong> Check <code>logs/email.log</code> for error details</li>
                        </ul>
                    </div>
                </div>
                
                <div class="test-section">
                    <h3><i class="fas fa-paper-plane"></i> Test Email Configuration</h3>
                    <p>After saving your settings, test them by sending a test email:</p>
                    <a href="test_email.php" class="btn btn-success">
                        <i class="fas fa-envelope"></i> Go to Email Test Page
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(inputId + '-toggle');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    function loadPreset(provider) {
        const presets = {
            gmail: {
                host: 'smtp.gmail.com',
                port: '587',
                encryption: 'tls'
            },
            outlook: {
                host: 'smtp-mail.outlook.com',
                port: '587',
                encryption: 'tls'
            },
            yahoo: {
                host: 'smtp.mail.yahoo.com',
                port: '587',
                encryption: 'tls'
            },
            custom: {
                host: '',
                port: '587',
                encryption: 'tls'
            }
        };
        
        const preset = presets[provider];
        document.getElementById('smtp_host').value = preset.host;
        document.getElementById('smtp_port').value = preset.port;
        document.getElementById('smtp_encryption').value = preset.encryption;
        
        // Focus on username field for custom presets
        if (provider === 'custom') {
            document.getElementById('smtp_username').focus();
        }
    }
    </script>
</body>
</html>
