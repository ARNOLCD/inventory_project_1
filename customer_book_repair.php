<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';
requireLogin();

$user = getCurrentUser();

// Only customers can access this page
if (!isCustomer()) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Create repairs table if not exists (for consistency)
$conn->query("
    CREATE TABLE IF NOT EXISTS repairs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_number VARCHAR(50) UNIQUE NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20),
        customer_email VARCHAR(100),
        customer_id INT,
        device_type VARCHAR(50) NOT NULL,
        device_brand VARCHAR(50),
        device_model VARCHAR(100),
        serial_number VARCHAR(100),
        problem_description TEXT NOT NULL,
        diagnosis TEXT,
        repair_notes TEXT,
        estimated_cost DECIMAL(10, 2),
        final_cost DECIMAL(10, 2),
        status ENUM('booked', 'in_progress', 'completed', 'delivered', 'cancelled') DEFAULT 'booked',
        priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
        received_by INT,
        technician_id INT,
        received_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_date DATETIME,
        completed_date DATETIME,
        delivered_date DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Add columns if not exists
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS item_photo VARCHAR(255) DEFAULT NULL AFTER serial_number");
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL AFTER customer_email");

// Create uploads directory for repair photos
$repair_upload_dir = 'uploads/repairs/';
if (!is_dir($repair_upload_dir)) {
    mkdir($repair_upload_dir, 0777, true);
}

// Generate ticket number
function generateTicketNumber($conn) {
    $prefix = 'REP';
    $date = date('Ymd');
    $result = $conn->query("SELECT MAX(id) as max_id FROM repairs");
    $row = $result->fetch_assoc();
    $next_id = ($row['max_id'] ?? 0) + 1;
    return $prefix . $date . str_pad($next_id, 4, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_type = $_POST['device_type'];
    $device_brand = trim($_POST['device_brand']);
    $device_model = trim($_POST['device_model']);
    $serial_number = trim($_POST['serial_number']);
    $problem_description = trim($_POST['problem_description']);
    
    if (empty($device_type) || empty($problem_description)) {
        $error = 'Device type and problem description are required.';
    } else {
        $ticket_number = generateTicketNumber($conn);
        
        // Handle photo upload
        $item_photo = null;
        if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['item_photo'];
            $ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed) && $photo['size'] <= 5 * 1024 * 1024) {
                $item_photo = 'repair_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($photo['tmp_name'], $repair_upload_dir . $item_photo);
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO repairs (ticket_number, customer_name, customer_phone, customer_email, customer_id, device_type, device_brand, device_model, serial_number, item_photo, problem_description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'booked')");
        $stmt->bind_param("sssssisssss", $ticket_number, $user['full_name'], $user['phone'], $user['email'], $user['id'], $device_type, $device_brand, $device_model, $serial_number, $item_photo, $problem_description);
        
        if ($stmt->execute()) {
            $message = "Repair ticket $ticket_number created successfully! We will contact you soon.";
            
            // Send confirmation email
            $deviceInfo = $device_type;
            if ($device_brand) $deviceInfo .= ' - ' . $device_brand;
            if ($device_model) $deviceInfo .= ' ' . $device_model;
            
            $subject = "Repair Booking Confirmed - Ticket $ticket_number";
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #1a365d, #2d3748); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f7fafc; padding: 30px; border: 1px solid #e2e8f0; }
                    .ticket-badge { background: #3182ce; color: white; padding: 15px; border-radius: 5px; text-align: center; font-size: 18px; margin: 20px 0; }
                    .details { background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; }
                    .footer { background: #edf2f7; padding: 15px; text-align: center; font-size: 12px; color: #718096; border-radius: 0 0 10px 10px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>AC-TECHNOLOGY</h1>
                        <p>Repair Booking Confirmed</p>
                    </div>
                    <div class='content'>
                        <h2>Repair Request Received</h2>
                        <p>Hello <strong>{$user['full_name']}</strong>,</p>
                        <p>Your repair request has been successfully received and booked.</p>
                        
                        <div class='ticket-badge'>
                            <i class='fas fa-clipboard-list'></i> Ticket: $ticket_number
                        </div>
                        
                        <div class='details'>
                            <p><strong>Device:</strong> $deviceInfo</p>
                            <p><strong>Serial Number:</strong> " . ($serial_number ?: 'Not provided') . "</p>
                            <p><strong>Problem:</strong> $problem_description</p>
                            <p><strong>Status:</strong> Booked</p>
                            <p><strong>Date:</strong> " . date('M d, Y H:i') . "</p>
                        </div>
                        
                        <p>We will review your request and contact you with an estimated cost and timeline. You can track the status of your repair by logging into your account.</p>
                        
                        <p style='text-align: center; margin-top: 20px;'>
                            <a href='" . SYSTEM_URL . "/customer_dashboard.php' style='display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>Track Your Repair</a>
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " AC-TECHNOLOGY. All rights reserved.</p>
                        <p>For questions, contact us at info@actechnology.co.zm</p>
                    </div>
                </div>
            </body>
            </html>";
            
            sendEmail($user['email'], $subject, $body);
        } else {
            $error = 'Error creating repair ticket. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Repair - AC-TECHNOLOGY</title>
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
        .customer-header p {
            color: #718096;
        }
        .form-section {
            margin-bottom: 25px;
        }
        .form-section h3 {
            color: #2d3748;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .photo-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            margin-top: 10px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="customer-container">
        <div class="customer-header">
            <img src="assets/images/logo.png" alt="AC-TECHNOLOGY Logo" onerror="this.style.display='none'" style="max-height: 60px; margin-bottom: 15px;">
            <h1><i class="fas fa-tools"></i> Book a Repair</h1>
            <p>Submit your device for repair service</p>
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
        
        <?php if (!$message): ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-section">
                    <h3><i class="fas fa-laptop"></i> Device Information</h3>
                    
                    <div class="form-group">
                        <label>Device Type *</label>
                        <select name="device_type" class="form-control" required>
                            <option value="">-- Select Device Type --</option>
                            <option value="laptop">Laptop</option>
                            <option value="phone">Phone</option>
                            <option value="desktop">Desktop</option>
                            <option value="tablet">Tablet</option>
                            <option value="printer">Printer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Brand</label>
                            <input type="text" name="device_brand" class="form-control" placeholder="e.g., HP, Dell, Samsung">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="device_model" class="form-control" placeholder="e.g., Pavilion, Galaxy S21">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" class="form-control" placeholder="Device serial number (if available)">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-exclamation-triangle"></i> Problem Description</h3>
                    
                    <div class="form-group">
                        <label>Describe the Problem *</label>
                        <textarea name="problem_description" class="form-control" rows="4" required placeholder="Please describe what's wrong with your device..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-camera"></i> Device Photo (Optional)</h3>
                    
                    <div class="form-group">
                        <label>Upload a photo of your device</label>
                        <input type="file" name="item_photo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewPhoto(this)">
                        <small style="color: #718096;">Accepted: JPG, PNG, GIF, WebP (Max 5MB)</small>
                        <div id="photoPreview" style="display: none; margin-top: 10px;">
                            <img id="photoPreviewImg" src="" alt="Preview" class="photo-preview">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Your Information</h3>
                    
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Submit Repair Request
                    </button>
                    <a href="customer_dashboard.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="customer_dashboard.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreviewImg').src = e.target.result;
                    document.getElementById('photoPreview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>