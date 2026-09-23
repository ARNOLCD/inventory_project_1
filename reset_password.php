<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isCustomer()) {
        header('Location: customer_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$message = '';
$error = '';
$step = 1; // 1: enter email/username, 2: set new password, 3: success

// Create password_resets table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['verify_user'])) {
        // Step 1: Verify user exists by email or username
        $identifier = trim($_POST['identifier']);
        
        if (empty($identifier)) {
            $error = 'Please enter your email address or username.';
        } else {
            // Find user by email OR username
            $stmt = $conn->prepare("SELECT id, username, full_name, email FROM users WHERE email = ? OR username = ?");
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                // Store user ID in session for password reset
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_username'] = $user['username'];
                
                // Try to send email notification
                if (!empty($user['email'])) {
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    $stmt2 = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt2->bind_param("iss", $user['id'], $token, $expires_at);
                    $stmt2->execute();
                    
                    // Send email (non-blocking - reset still works even if email fails)
                    @sendPasswordResetEmail($user['email'], $user['username'], $token);
                }
                
                $step = 2; // Go to password reset form
            } else {
                $error = 'No account found with this email or username.';
            }
        }
        
    } elseif (isset($_POST['reset_password'])) {
        // Step 2: Reset the password
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Check if reset session is valid
        if (!isset($_SESSION['reset_user_id'])) {
            $error = 'Session expired. Please start over.';
            $step = 1;
        } elseif (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters long.';
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
            $step = 2;
        } else {
            // Update password in database
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['reset_user_id']);
            
            if ($stmt->execute()) {
                $message = 'Password reset successfully! Redirecting to login...';
                
                // Clear session variables
                $reset_username = $_SESSION['reset_username'] ?? '';
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_username']);
                unset($_SESSION['reset_token']);
                
                // Redirect to login after 3 seconds
                header('refresh:3;url=login.php');
                $step = 3; // Success state
            } else {
                $error = 'Error resetting password. Please try again.';
                $step = 2;
            }
        }
    }
} else {
    // GET request - clear any previous reset session data
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_username']);
    unset($_SESSION['reset_token']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .reset-header {
            margin-bottom: 2rem;
        }
        
        .reset-header img {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            border-radius: 15px;
        }
        
        .reset-header h2 {
            color: #1a365d;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .reset-header p {
            color: #718096;
            font-size: 0.95rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #718096;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }
        
        .step-dot.active {
            background: #667eea;
            color: #fff;
            transform: scale(1.1);
        }
        
        .step-dot.completed {
            background: #48bb78;
            color: #fff;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #4a5568;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-danger {
            background: #fff5f5;
            color: #742a2a;
            border: 1px solid #feb2b2;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #48bb78;
            margin-bottom: 1rem;
        }
        
        .user-info {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
            text-align: left;
        }
        
        .user-info p {
            margin: 0;
            color: #4a5568;
        }
        
        .user-info strong {
            color: #2d3748;
        }
        
        .password-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-input-container input {
            padding-right: 50px;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <img src="assets/images/sims-tech-logo.jpg" alt="Sims-Tech Zambia Logo" onerror="this.style.display='none'">
            <h2>Reset Password</h2>
            <p>Follow the steps to reset your password</p>
        </div>
        
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">1</div>
            <div class="step-dot <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">2</div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <!-- Step 1: Enter Email or Username -->
            <form method="POST" action="reset_password.php">
                <div class="form-group">
                    <label for="identifier">Email Address or Username</label>
                    <input type="text" id="identifier" name="identifier" required 
                           placeholder="Enter your email or username"
                           value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>">
                </div>
                
                <button type="submit" name="verify_user" class="btn btn-primary">
                    <i class="fas fa-arrow-right"></i> Continue
                </button>
            </form>
            
        <?php elseif ($step === 2): ?>
            <!-- Step 2: Set New Password -->
            <div class="user-info">
                <p>Resetting password for: <strong><?php echo htmlspecialchars($_SESSION['reset_username'] ?? ''); ?></strong></p>
            </div>
            
            <form method="POST" action="reset_password.php">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password" required 
                               placeholder="Enter new password (min. 6 characters)">
                        <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                            <i class="fas fa-eye" id="new_password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm new password">
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye" id="confirm_password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" name="reset_password" class="btn btn-primary">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
            
        <?php elseif ($step === 3): ?>
            <!-- Success -->
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>Password Reset Successful!</h3>
            <p>Your password has been reset successfully. You will be redirected to the login page.</p>
            
            <a href="login.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        <?php endif; ?>
        
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>
    
    <script>
    function togglePassword(inputId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(inputId + '-toggle-icon');
        
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
    </script>
</body>
</html>
