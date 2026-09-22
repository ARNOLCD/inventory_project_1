<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

// Get current company info
$company = $conn->query("SELECT * FROM company_info LIMIT 1")->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $tagline = trim($_POST['tagline']);
    $about_us = trim($_POST['about_us']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $facebook = trim($_POST['facebook']);
    $twitter = trim($_POST['twitter']);
    $instagram = trim($_POST['instagram']);
    
    // Bank details
    $mobile = trim($_POST['mobile']);
    $tpin = trim($_POST['tpin']);
    $bank_name = trim($_POST['bank_name']);
    $account_name = trim($_POST['account_name']);
    $account_number = trim($_POST['account_number']);
    $branch = trim($_POST['branch']);
    $pay_to_sale = trim($_POST['pay_to_sale']);
    
    // Handle logo upload
    $logo = $company['logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'assets/images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $logo = 'logo.' . $file_ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo);
        }
    }
    
    if ($company) {
        $stmt = $conn->prepare("UPDATE company_info SET name=?, tagline=?, about_us=?, address=?, phone=?, mobile=?, email=?, tpin=?, bank_name=?, account_name=?, account_number=?, branch=?, pay_to_sale=?, facebook=?, twitter=?, instagram=?, logo=? WHERE id=?");
        $stmt->bind_param("sssssssssssssssssi", $name, $tagline, $about_us, $address, $phone, $mobile, $email, $tpin, $bank_name, $account_name, $account_number, $branch, $pay_to_sale, $facebook, $twitter, $instagram, $logo, $company['id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO company_info (name, tagline, about_us, address, phone, mobile, email, tpin, bank_name, account_name, account_number, branch, pay_to_sale, facebook, twitter, instagram, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssssssss", $name, $tagline, $about_us, $address, $phone, $mobile, $email, $tpin, $bank_name, $account_name, $account_number, $branch, $pay_to_sale, $facebook, $twitter, $instagram, $logo);
    }
    
    if ($stmt->execute()) {
        $message = 'Settings updated successfully!';
        $company = $conn->query("SELECT * FROM company_info LIMIT 1")->fetch_assoc();
    } else {
        $error = 'Error updating settings: ' . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AC-TECHNOLOGY</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="fas fa-cog"></i> System Settings</h1>
                    <p>Configure company information and system settings</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="grid-2">
                        <!-- Company Information -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-building"></i> Company Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Company Name *</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($company['name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Tagline</label>
                                    <input type="text" name="tagline" class="form-control" value="<?php echo htmlspecialchars($company['tagline'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>About Us</label>
                                    <textarea name="about_us" class="form-control" rows="5"><?php echo htmlspecialchars($company['about_us'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Company Logo</label>
                                    <div class="file-upload">
                                        <input type="file" name="logo" accept="image/*">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Click to upload logo</p>
                                        <div class="file-preview">
                                            <?php if (!empty($company['logo'])): ?>
                                                <img src="assets/images/<?php echo htmlspecialchars($company['logo']); ?>" alt="Current Logo" style="max-width: 150px;">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($company['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Mobile Number</label>
                                    <input type="text" name="mobile" class="form-control" value="<?php echo htmlspecialchars($company['mobile'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>TPIN Number</label>
                                    <input type="text" name="tpin" class="form-control" value="<?php echo htmlspecialchars($company['tpin'] ?? ''); ?>">
                                </div>
                                
                                <hr style="margin: 1.5rem 0;">
                                <h4 style="margin-bottom: 1rem;"><i class="fas fa-university"></i> Bank Details (for Invoices)</h4>
                                
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($company['bank_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Account Name</label>
                                    <input type="text" name="account_name" class="form-control" value="<?php echo htmlspecialchars($company['account_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Account Number</label>
                                    <input type="text" name="account_number" class="form-control" value="<?php echo htmlspecialchars($company['account_number'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Branch</label>
                                    <input type="text" name="branch" class="form-control" value="<?php echo htmlspecialchars($company['branch'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Pay to Sale (Mobile Money)</label>
                                    <input type="text" name="pay_to_sale" class="form-control" value="<?php echo htmlspecialchars($company['pay_to_sale'] ?? ''); ?>">
                                </div>
                                
                                <hr style="margin: 1.5rem 0;">
                                <h4 style="margin-bottom: 1rem;"><i class="fas fa-share-alt"></i> Social Media Links</h4>
                                
                                <div class="form-group">
                                    <label><i class="fab fa-facebook"></i> Facebook</label>
                                    <input type="url" name="facebook" class="form-control" value="<?php echo htmlspecialchars($company['facebook'] ?? ''); ?>" placeholder="https://facebook.com/...">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fab fa-twitter"></i> Twitter</label>
                                    <input type="url" name="twitter" class="form-control" value="<?php echo htmlspecialchars($company['twitter'] ?? ''); ?>" placeholder="https://twitter.com/...">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fab fa-instagram"></i> Instagram</label>
                                    <input type="url" name="instagram" class="form-control" value="<?php echo htmlspecialchars($company['instagram'] ?? ''); ?>" placeholder="https://instagram.com/...">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4" style="text-align: right;">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
                
                <!-- System Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> System Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid-3">
                            <div>
                                <strong>System Version:</strong><br>
                                <span class="badge badge-info">v1.0.0</span>
                            </div>
                            <div>
                                <strong>PHP Version:</strong><br>
                                <span class="badge badge-success"><?php echo phpversion(); ?></span>
                            </div>
                            <div>
                                <strong>Database:</strong><br>
                                <span class="badge badge-success">MySQL <?php echo $conn->server_info; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
