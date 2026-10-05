<?php
require_once 'config/database.php';
require_once 'config/session.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE LOWER(username) = LOWER(?)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                setUserSession($user);
                
                // Redirect based on role
                if ($user['role'] === 'customer') {
                    header('Location: customer_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sims-Tech Zambia</title>
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
        
        .auth-footer .create-account {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: 600;
            margin: 20px 0;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);
        }
        
        .auth-footer .create-account:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72, 187, 120, 0.5);
            text-decoration: none;
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
                <h1>Welcome Back</h1>
                <p>Sign in to your account to shop products and book repairs</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" style="background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            
            <div class="auth-footer">
                <a href="reset_password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Website</a>
                
                <div style="margin-top: 25px; padding-top: 25px; border-top: 1px solid #e2e8f0;">
                    <p style="color: #718096; margin-bottom: 15px;">New to Sims-Tech Zambia?</p>
                    <a href="signup.php" class="create-account">
                        <i class="fas fa-user-plus"></i> Create Your Account
                    </a>
                </div>
            </div>
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
