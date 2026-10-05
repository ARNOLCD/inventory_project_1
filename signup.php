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

$error = '';
$success = '';

// Add phone column to users table if not exists
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email");

ensureUserRoles($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(?)");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Username already exists. Please choose a different username.';
        } else {
            // Check if email already exists
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $check_email_result = $check_email->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $error = 'Email already registered. Please use a different email or login.';
            } else {
                // Create new customer account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'customer';
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $hashed_password, $full_name, $email, $phone, $role);
                
                if ($stmt->execute()) {
                    $success = 'Account created successfully! You can now login.';
                    
                    // Send welcome email
                    $loginLink = SYSTEM_URL . "/login.php";
                    $subject = "Welcome to Sims-Tech Zambia - Customer Account Created";
                    
                    $body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
                            .button { display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                            .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Sims-Tech Zambia</h1>
                                <p>Customer Account Created</p>
                            </div>
                            <div class='content'>
                                <h2>Welcome, $full_name!</h2>
                                <p>Your customer account has been created successfully.</p>
                                <p>You can now:</p>
                                <ul>
                                    <li>Book repair services for your devices</li>
                                    <li>Track repair status in real-time</li>
                                    <li>Receive email notifications about your repairs</li>
                                </ul>
                                <p style='text-align: center;'>
                                    <a href='$loginLink' class='button'>Login to Your Account</a>
                                </p>
                                <p>If you have any questions, please contact us.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " Sims-Tech Zambia. All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>";
                    
                    sendEmail($email, $subject, $body);
                } else {
                    $error = 'Error creating account. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Sims-Tech Zambia</title>
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
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 500px;
        }
        
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 50px 40px;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .auth-header img {
            max-height: 80px;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        
        .auth-header h1 {
            color: #1a365d;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .auth-header p {
            color: #718096;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #2d3748;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .form-group .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        
        .form-group .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-input-container .form-control {
            padding-right: 50px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        
        .auth-footer {
            margin-top: 30px;
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }
        
        .auth-footer a {
            display: block;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            margin: 10px 0;
            transition: color 0.3s ease;
        }
        
        .auth-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
            z-index: -1;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite;
        }
        
        .floating-element:nth-child(1) {
            width: 80px;
            height: 80px;
            left: 10%;
            top: 20%;
            animation-delay: 0s;
        }
        
        .floating-element:nth-child(2) {
            width: 120px;
            height: 120px;
            left: 80%;
            top: 30%;
            animation-delay: 2s;
        }
        
        .floating-element:nth-child(3) {
            width: 60px;
            height: 60px;
            left: 70%;
            top: 70%;
            animation-delay: 4s;
        }
        
        .floating-element:nth-child(4) {
            width: 100px;
            height: 100px;
            left: 20%;
            top: 80%;
            animation-delay: 6s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }
    </style>
</head>
<body>
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>
    
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <img src="assets/images/sims-tech-logo.jpg" alt="Sims-Tech Zambia Logo" onerror="this.style.display='none'">
                <h1>Create Your Account</h1>
                <p>Sign up to shop products, book repairs, and track your orders</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" style="background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="background: #f0fff4; border: 1px solid #9ae6b4; color: #22543d; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>
            <?php else: ?>
                <form class="auth-form" method="POST" action="">
                    <div class="form-group">
                        <label for="full_name"><i class="fas fa-user"></i> Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username"><i class="fas fa-at"></i> Username *</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Choose a username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="Enter your phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password *</label>
                        <div class="password-input-container">
                            <input type="password" id="password" name="password" class="form-control" placeholder="Create a password (min 6 characters)" required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="password-toggle-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                        <div class="password-input-container">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="confirm_password-toggle-icon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                
                <div class="auth-footer">
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Already have an account? Login</a>
                    <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
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