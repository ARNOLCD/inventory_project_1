<?php
require_once 'config/database.php';
require_once 'config/email.php';

$message = '';
$error = '';

// Get company email from database
$company_email = 'info@actechnology.co.zm';
$company_result = $conn->query("SELECT email FROM company_info LIMIT 1");
if ($company_result && $row = $company_result->fetch_assoc()) {
    $company_email = $row['email'] ?: $company_email;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $msg = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($msg)) {
        $error = 'Please fill in all fields.';
    } else {
        // Build email body
        $emailBody = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #1a365d, #2b6cb0); padding: 20px; border-radius: 8px 8px 0 0;">
                <h2 style="color: #fff; margin: 0;">New Contact Form Message</h2>
            </div>
            <div style="background: #f7fafc; padding: 20px; border: 1px solid #e2e8f0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 10px; font-weight: bold; color: #1a365d; width: 120px;">Name:</td>
                        <td style="padding: 10px;">' . htmlspecialchars($name) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; color: #1a365d;">Email:</td>
                        <td style="padding: 10px;"><a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; color: #1a365d;">Subject:</td>
                        <td style="padding: 10px;">' . htmlspecialchars($subject) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; font-weight: bold; color: #1a365d; vertical-align: top;">Message:</td>
                        <td style="padding: 10px;">' . nl2br(htmlspecialchars($msg)) . '</td>
                    </tr>
                </table>
            </div>
            <div style="background: #edf2f7; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px; color: #718096;">
                <p>This message was sent from the Sims-Tech Zambia website contact form.</p>
                <p>You can reply directly to <a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></p>
            </div>
        </div>';

        // Send to company email
        $emailSubject = 'Contact Form: ' . $subject;
        $result = sendEmail($company_email, $emailSubject, $emailBody);

        if ($result) {
            $message = 'Thank you for your message! We will get back to you soon.';
        } else {
            $error = 'Sorry, there was a problem sending your message. Please try again later or contact us directly at ' . htmlspecialchars($company_email) . '.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .result-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 50%, var(--secondary-color) 100%);
            padding: 2rem;
        }
        .result-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            animation: scaleIn 0.5s ease;
        }
        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        .result-icon.success {
            background: rgba(56, 161, 105, 0.1);
            color: var(--success-color);
        }
        .result-icon.error {
            background: rgba(229, 62, 62, 0.1);
            color: var(--danger-color);
        }
        .result-card h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .result-card p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="result-card">
            <?php if ($message): ?>
                <div class="result-icon success">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Message Sent!</h2>
                <p><?php echo htmlspecialchars($message); ?></p>
            <?php else: ?>
                <div class="result-icon error">
                    <i class="fas fa-times"></i>
                </div>
                <h2>Error</h2>
                <p><?php echo htmlspecialchars($error ?: 'Something went wrong. Please try again.'); ?></p>
            <?php endif; ?>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>
