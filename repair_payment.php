<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$user = getCurrentUser();

// Only customers can access this page
if (!isCustomer()) {
    header('Location: dashboard.php');
    exit();
}

$repair_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

// Get repair details
$stmt = $conn->prepare("SELECT r.*, u.full_name FROM repairs r LEFT JOIN users u ON r.customer_id = u.id WHERE r.id = ? AND r.customer_id = ?");
$stmt->bind_param("ii", $repair_id, $user['id']);
$stmt->execute();
$repair = $stmt->get_result()->fetch_assoc();

if (!$repair) {
    header('Location: customer_dashboard.php');
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    
    if (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } else {
        // Update repair payment status
        $update_stmt = $conn->prepare("UPDATE repairs SET payment_method = ?, payment_status = 'paid', payment_date = NOW() WHERE id = ?");
        $update_stmt->bind_param("si", $payment_method, $repair_id);
        
        if ($update_stmt->execute()) {
            $message = "Payment processed successfully! Your repair payment has been recorded.";
            
            // Send payment confirmation email
            $subject = "Payment Received - Repair Ticket {$repair['ticket_number']}";
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
                    .payment-badge { background: #38a169; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 18px; margin: 20px 0; }
                    .details { background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; }
                    .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Sims-Tech Zambia</h1>
                        <p>Payment Confirmation</p>
                    </div>
                    <div class='content'>
                        <h2>Payment Received</h2>
                        <p>Hello <strong>{$user['full_name']}</strong>,</p>
                        <p>Your payment for repair ticket <strong>{$repair['ticket_number']}</strong> has been successfully received.</p>
                        
                        <div class='payment-badge'>
                            <i class='fas fa-check-circle'></i> Payment Completed
                        </div>
                        
                        <div class='details'>
                            <p><strong>Ticket:</strong> {$repair['ticket_number']}</p>
                            <p><strong>Device:</strong> {$repair['device_type']}";
            if ($repair['device_brand']) $body .= " - {$repair['device_brand']}";
            if ($repair['device_model']) $body .= " {$repair['device_model']}";
            $body .= "</p>
                            <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
                            <p><strong>Amount:</strong> K" . number_format($repair['final_cost'], 2) . "</p>
                            <p><strong>Payment Date:</strong> " . date('M d, Y H:i') . "</p>
                        </div>
                        
                        <p>Thank you for your payment. Your device is ready for collection once the repair is completed.</p>
                        
                        <p style='text-align: center; margin-top: 20px;'>
                            <a href='" . SYSTEM_URL . "/customer_dashboard.php' style='display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>View Dashboard</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Sims-Tech Zambia. All rights reserved.</p>
                        <p>For questions, contact us at info@actechnology.co.zm</p>
                    </div>
                </div>
            </body>
            </html>";
            
            require_once 'config/email.php';
            sendEmail($user['email'], $subject, $body);
            
            // Refresh repair data
            $stmt = $conn->prepare("SELECT r.*, u.full_name FROM repairs r LEFT JOIN users u ON r.customer_id = u.id WHERE r.id = ? AND r.customer_id = ?");
            $stmt->bind_param("ii", $repair_id, $user['id']);
            $stmt->execute();
            $repair = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Error processing payment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay for Repair - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f7fafc; }
        .customer-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .customer-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .customer-header h1 {
            color: #1a365d;
            margin-bottom: 10px;
        }
        .repair-details {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        .repair-details p {
            margin: 10px 0;
            color: #4a5568;
        }
        .repair-details strong {
            color: #2d3748;
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .payment-option {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-option:hover {
            border-color: #667eea;
            background: #f7fafc;
        }
        .payment-option input[type="radio"] {
            display: none;
        }
        .payment-option.selected {
            border-color: #667eea;
            background: #ebf4ff;
        }
        .payment-option i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        .payment-option span {
            display: block;
            font-weight: 500;
            color: #2d3748;
        }
    </style>
</head>
<body>
    <div class="customer-container">
        <div class="customer-header">
            <img src="assets/images/sims-tech-logo.jpg" alt="Sims-Tech Zambia Logo" onerror="this.style.display='none'" style="max-height: 60px; margin-bottom: 15px;">
            <h1><i class="fas fa-credit-card"></i> Pay for Repair</h1>
            <p>Complete payment for your repair service</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($repair['payment_status'] === 'paid'): ?>
            <div class="alert alert-success" style="text-align: center; padding: 30px;">
                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <h3>Payment Already Completed</h3>
                <p>This repair has already been paid for on <?php echo date('M d, Y H:i', strtotime($repair['payment_date'])); ?>.</p>
                <a href="customer_dashboard.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
            </div>
        <?php elseif (!$repair['final_cost'] || $repair['final_cost'] == 0): ?>
            <div class="alert alert-warning" style="text-align: center; padding: 30px;">
                <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <h3>Cost Not Yet Determined</h3>
                <p>The final cost for this repair has not been set yet. Please wait for our team to provide an estimate.</p>
                <a href="customer_dashboard.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="repair-details">
                <h3><i class="fas fa-clipboard-list"></i> Repair Details</h3>
                <p><strong>Ticket Number:</strong> <?php echo htmlspecialchars($repair['ticket_number']); ?></p>
                <p><strong>Device:</strong> <?php echo htmlspecialchars($repair['device_type']); ?>
                    <?php if ($repair['device_brand']): ?> - <?php echo htmlspecialchars($repair['device_brand']); ?><?php endif; ?>
                    <?php if ($repair['device_model']): ?> <?php echo htmlspecialchars($repair['device_model']); ?><?php endif; ?>
                </p>
                <p><strong>Problem:</strong> <?php echo htmlspecialchars($repair['problem_description']); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?></p>
                <p><strong>Final Cost:</strong> <span style="font-size: 1.5rem; color: #38a169; font-weight: 700;">K<?php echo number_format($repair['final_cost'], 2); ?></span></p>
            </div>
            
            <?php if (!$message): ?>
                <form method="POST">
                    <h3><i class="fas fa-wallet"></i> Select Payment Method</h3>
                    
                    <div class="payment-methods">
                        <label class="payment-option" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="mobile_money" required>
                            <i class="fas fa-mobile-alt"></i>
                            <span>Mobile Money</span>
                        </label>
                        
                        <label class="payment-option" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="card">
                            <i class="fas fa-credit-card"></i>
                            <span>Card Payment</span>
                        </label>
                        
                        <label class="payment-option" onclick="selectPayment(this)">
                            <input type="radio" name="payment_method" value="cash">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Pay on Collection</span>
                        </label>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-check"></i> Complete Payment
                        </button>
                        <a href="customer_dashboard.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div style="text-align: center; margin-top: 30px;">
                    <a href="customer_dashboard.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function selectPayment(element) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Check the radio button
            element.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>
