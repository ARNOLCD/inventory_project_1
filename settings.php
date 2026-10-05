<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

$tabs = [
    'company' => ['Company & Location', 'fa-building'],
    'about' => ['About', 'fa-info-circle'],
    'contact' => ['Contact Details', 'fa-address-card'],
    'branding' => ['Logo & Branding', 'fa-image'],
    'banking' => ['Banking', 'fa-university'],
    'services' => ['Services', 'fa-cogs'],
    'email' => ['Email', 'fa-envelope'],
    'alerts' => ['Alerts', 'fa-bell'],
    'system' => ['System', 'fa-server'],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'company';

// company_info columns editable per tab (whitelisted)
$company_fields = [
    'company' => ['name', 'tagline', 'address', 'tpin'],
    'about' => ['about_us'],
    'contact' => ['phone', 'mobile', 'email', 'facebook', 'twitter', 'instagram'],
    'banking' => ['bank_name', 'account_name', 'account_number', 'branch', 'pay_to_sale'],
];
// system_settings keys editable per tab
$setting_fields = [
    'company' => ['map_link', 'working_hours'],
    'contact' => ['whatsapp'],
    'email' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_from_email', 'smtp_from_name', 'smtp_encryption'],
    'alerts' => ['system_url'],
];

function updateCompanyInfo($conn, array $values) {
    $company = companyInfo();
    $columns = implode(', ', array_map(fn($c) => "$c = ?", array_keys($values)));
    $stmt = $conn->prepare("UPDATE company_info SET $columns WHERE id = ?");
    $params = array_values($values);
    $params[] = $company['id'];
    $stmt->bind_param(str_repeat('s', count($values)) . 'i', ...$params);
    $stmt->execute();
    companyInfo(true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    $tab = isset($tabs[$section]) ? $section : $tab;

    if ($section === 'company' && trim($_POST['name'] ?? '') === '') {
        $error = 'Company name is required.';
    } elseif ($section === 'contact' && ($_POST['email'] ?? '') !== '' && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid contact email address.';
    } elseif ($section === 'email' && ($_POST['smtp_from_email'] ?? '') !== '' && !filter_var($_POST['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid "send from" email address.';
    } elseif ($section === 'test_email') {
        $tab = 'email';
        $to = trim($_POST['test_to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address to send the test to.';
        } elseif (sendEmail($to, 'Test email - ' . companyName(), emailLayout('Email is working', 'Email Test', '<p>This test email confirms that the email settings in System Information are working.</p>'))) {
            $message = 'Test email sent to ' . e($to) . '. Check the inbox (and spam folder).';
        } else {
            $error = 'Test email failed. Check the settings below and logs/email_errors.log for details.';
        }
    } elseif ($section === 'branding') {
        $company = companyInfo();
        if (!empty($_POST['remove_logo'])) {
            updateCompanyInfo($conn, ['logo' => '']);
            $message = 'Custom logo removed. The default logo is now shown.';
        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || @getimagesize($_FILES['logo']['tmp_name']) === false) {
                $error = 'Please upload a valid image (JPG, PNG, GIF or WEBP).';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $error = 'The logo must be smaller than 2 MB.';
            } else {
                $logo = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/assets/images/' . $logo)) {
                    $old = basename($company['logo'] ?? '');
                    if (strpos($old, 'logo_') === 0 && is_file(__DIR__ . '/assets/images/' . $old)) {
                        unlink(__DIR__ . '/assets/images/' . $old);
                    }
                    updateCompanyInfo($conn, ['logo' => $logo]);
                    $message = 'Logo updated. It is now shown across the system, on receipts and in emails.';
                } else {
                    $error = 'Could not save the uploaded logo.';
                }
            }
        } else {
            $error = 'Please choose a logo image to upload.';
        }
    } elseif (isset($company_fields[$section]) || isset($setting_fields[$section])) {
        if (isset($company_fields[$section])) {
            $values = [];
            foreach ($company_fields[$section] as $field) {
                $values[$field] = trim($_POST[$field] ?? '');
            }
            updateCompanyInfo($conn, $values);
        }
        $settings = [];
        foreach ($setting_fields[$section] ?? [] as $field) {
            $settings[$field] = trim($_POST[$field] ?? '');
        }
        if ($section === 'email') {
            $settings['smtp_port'] = (string)(int)$settings['smtp_port'];
            $settings['smtp_encryption'] = in_array($settings['smtp_encryption'], ['tls', 'ssl', 'none'], true) ? $settings['smtp_encryption'] : 'tls';
            if (($_POST['smtp_password'] ?? '') !== '') {
                $settings['smtp_password'] = $_POST['smtp_password'];
            }
        }
        if ($section === 'alerts') {
            $settings['low_stock_email_alerts'] = !empty($_POST['low_stock_email_alerts']) ? '1' : '0';
            $settings['repair_request_email_alerts'] = !empty($_POST['repair_request_email_alerts']) ? '1' : '0';
        }
        if ($settings) {
            saveSettings($settings);
        }
        $message = $tabs[$section][0] . ' saved successfully.';
    }
}

$company = companyInfo();
$smtp_password_set = getSetting('smtp_password') !== '';
$services_count = $conn->query("SELECT COUNT(*) AS total, SUM(status = 'active') AS active FROM services")->fetch_assoc();
$queue = $conn->query("SELECT SUM(status = 'pending') AS pending, SUM(status = 'failed') AS failed, SUM(status = 'sent') AS sent FROM email_queue")->fetch_assoc();
$field = fn($name) => e($company[$name] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Information - <?php echo e(companyName()); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .settings-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .settings-tabs a { padding: 10px 16px; color: #4a5568; text-decoration: none; border-radius: 8px 8px 0 0; font-size: 0.9rem; }
        .settings-tabs a.active { background: #1a365d; color: #fff; }
        .settings-tabs a:hover:not(.active) { background: #edf2f7; }
        .settings-panel { max-width: 800px; }
        .hint { font-size: 0.8rem; color: #718096; margin-top: 4px; }
        .logo-preview { max-width: 220px; max-height: 120px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; background: #fff; }
        .stat-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .stat-row div { background: #f7fafc; border-radius: 8px; padding: 12px 18px; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="fas fa-sliders-h"></i> System Information</h1>
                    <p>Manage company details, website content, branding, email and alerts</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <nav class="settings-tabs">
                    <?php foreach ($tabs as $key => [$label, $icon]): ?>
                        <a href="settings.php?tab=<?php echo $key; ?>" class="<?php echo $tab === $key ? 'active' : ''; ?>"><i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="card settings-panel">
                    <div class="card-body">
                    <?php if ($tab === 'company'): ?>
                        <form method="POST" action="settings.php?tab=company">
                            <input type="hidden" name="section" value="company">
                            <div class="form-group">
                                <label>Company Name *</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $field('name'); ?>" required>
                                <div class="hint">Shown in the sidebar, website, receipts and emails.</div>
                            </div>
                            <div class="form-group">
                                <label>Tagline</label>
                                <input type="text" name="tagline" class="form-control" value="<?php echo $field('tagline'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Location / Physical Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo $field('address'); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Google Maps Link</label>
                                <input type="url" name="map_link" class="form-control" value="<?php echo e(getSetting('map_link')); ?>" placeholder="https://maps.google.com/...">
                                <div class="hint">Shown as a "Get directions" link on the website.</div>
                            </div>
                            <div class="form-group">
                                <label>Working Hours</label>
                                <input type="text" name="working_hours" class="form-control" value="<?php echo e(getSetting('working_hours')); ?>" placeholder="Mon - Fri: 08:00 - 17:00, Sat: 09:00 - 13:00">
                            </div>
                            <div class="form-group">
                                <label>TPIN Number</label>
                                <input type="text" name="tpin" class="form-control" value="<?php echo $field('tpin'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                        </form>

                    <?php elseif ($tab === 'about'): ?>
                        <form method="POST" action="settings.php?tab=about">
                            <input type="hidden" name="section" value="about">
                            <div class="form-group">
                                <label>About Us</label>
                                <textarea name="about_us" class="form-control" rows="10"><?php echo $field('about_us'); ?></textarea>
                                <div class="hint">Shown in the "About" section of the public website.</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                        </form>

                    <?php elseif ($tab === 'contact'): ?>
                        <form method="POST" action="settings.php?tab=contact">
                            <input type="hidden" name="section" value="contact">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo $field('phone'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Mobile Number</label>
                                    <input type="text" name="mobile" class="form-control" value="<?php echo $field('mobile'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Contact Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo $field('email'); ?>">
                                    <div class="hint">Public contact address (website, receipts, email footers).</div>
                                </div>
                                <div class="form-group">
                                    <label>WhatsApp Number</label>
                                    <input type="text" name="whatsapp" class="form-control" value="<?php echo e(getSetting('whatsapp')); ?>" placeholder="+260...">
                                </div>
                            </div>
                            <h4 style="margin: 1rem 0;"><i class="fas fa-share-alt"></i> Social Media</h4>
                            <div class="form-group">
                                <label><i class="fab fa-facebook"></i> Facebook</label>
                                <input type="url" name="facebook" class="form-control" value="<?php echo $field('facebook'); ?>" placeholder="https://facebook.com/...">
                            </div>
                            <div class="form-group">
                                <label><i class="fab fa-twitter"></i> Twitter / X</label>
                                <input type="url" name="twitter" class="form-control" value="<?php echo $field('twitter'); ?>" placeholder="https://twitter.com/...">
                            </div>
                            <div class="form-group">
                                <label><i class="fab fa-instagram"></i> Instagram</label>
                                <input type="url" name="instagram" class="form-control" value="<?php echo $field('instagram'); ?>" placeholder="https://instagram.com/...">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                        </form>

                    <?php elseif ($tab === 'branding'): ?>
                        <p><strong>Current logo</strong></p>
                        <img src="<?php echo e(companyLogo()); ?>" alt="Current logo" class="logo-preview" id="logoPreview">
                        <form method="POST" action="settings.php?tab=branding" enctype="multipart/form-data" style="margin-top: 20px;">
                            <input type="hidden" name="section" value="branding">
                            <div class="form-group">
                                <label>Upload New Logo</label>
                                <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" onchange="previewLogo(this)">
                                <div class="hint">JPG, PNG, GIF or WEBP, max 2 MB. Used in the sidebar, login pages, website, receipts and emails.</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Logo</button>
                            <?php if (!empty($company['logo'])): ?>
                                <button type="submit" name="remove_logo" value="1" class="btn btn-secondary" formnovalidate onclick="return confirm('Remove the custom logo and use the default?')"><i class="fas fa-undo"></i> Use Default Logo</button>
                            <?php endif; ?>
                        </form>

                    <?php elseif ($tab === 'banking'): ?>
                        <form method="POST" action="settings.php?tab=banking">
                            <input type="hidden" name="section" value="banking">
                            <p class="hint" style="margin-bottom: 1rem;">Printed on invoices and receipts.</p>
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" value="<?php echo $field('bank_name'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Account Name</label>
                                <input type="text" name="account_name" class="form-control" value="<?php echo $field('account_name'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Account Number</label>
                                <input type="text" name="account_number" class="form-control" value="<?php echo $field('account_number'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Branch</label>
                                <input type="text" name="branch" class="form-control" value="<?php echo $field('branch'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Pay to Sale (Mobile Money)</label>
                                <input type="text" name="pay_to_sale" class="form-control" value="<?php echo $field('pay_to_sale'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                        </form>

                    <?php elseif ($tab === 'services'): ?>
                        <p>Services are shown on the public website and can be sold in the Point of Sale.</p>
                        <div class="stat-row" style="margin: 15px 0;">
                            <div><strong><?php echo (int)$services_count['total']; ?></strong> services</div>
                            <div><strong><?php echo (int)$services_count['active']; ?></strong> active (shown on website)</div>
                        </div>
                        <a href="services.php" class="btn btn-primary"><i class="fas fa-edit"></i> Add, Edit or Remove Services</a>

                    <?php elseif ($tab === 'email'): ?>
                        <?php if (!$smtp_password_set): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> No SMTP password is set, so the system cannot send emails. Enter an app password below.</div>
                        <?php endif; ?>
                        <form method="POST" action="settings.php?tab=email">
                            <input type="hidden" name="section" value="email">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Send Emails From (address)</label>
                                    <input type="email" name="smtp_from_email" class="form-control" value="<?php echo e(getSetting('smtp_from_email')); ?>" placeholder="info@yourcompany.com">
                                    <div class="hint">The address customers and staff see emails coming from.</div>
                                </div>
                                <div class="form-group">
                                    <label>Sender Name</label>
                                    <input type="text" name="smtp_from_name" class="form-control" value="<?php echo e(getSetting('smtp_from_name')); ?>" placeholder="<?php echo e(companyName()); ?>">
                                </div>
                                <div class="form-group">
                                    <label>SMTP Server</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?php echo e(getSetting('smtp_host', 'smtp.gmail.com')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Port</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?php echo e(getSetting('smtp_port', '587')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>SMTP Username</label>
                                    <input type="text" name="smtp_username" class="form-control" value="<?php echo e(getSetting('smtp_username')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>SMTP Password / App Password</label>
                                    <input type="password" name="smtp_password" class="form-control" autocomplete="new-password" placeholder="<?php echo $smtp_password_set ? 'Saved - leave blank to keep' : 'Enter app password'; ?>">
                                    <div class="hint">Stored in the database, never in code. For Gmail use an App Password.</div>
                                </div>
                                <div class="form-group">
                                    <label>Encryption</label>
                                    <select name="smtp_encryption" class="form-control">
                                        <?php foreach (['tls' => 'TLS / STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (not recommended)'] as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo getSetting('smtp_encryption', 'tls') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Email Settings</button>
                        </form>
                        <hr style="margin: 25px 0;">
                        <form method="POST" action="settings.php?tab=email" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                            <input type="hidden" name="section" value="test_email">
                            <div class="form-group" style="margin: 0; flex: 1; min-width: 220px;">
                                <label>Send a test email to</label>
                                <input type="email" name="test_to" class="form-control" value="<?php echo e($company['email'] ?? ''); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send Test</button>
                        </form>
                        <div class="stat-row" style="margin-top: 20px;">
                            <div>Queued: <strong><?php echo (int)$queue['pending']; ?></strong></div>
                            <div>Sent: <strong><?php echo (int)$queue['sent']; ?></strong></div>
                            <div>Failed: <strong><?php echo (int)$queue['failed']; ?></strong></div>
                        </div>

                    <?php elseif ($tab === 'alerts'): ?>
                        <form method="POST" action="settings.php?tab=alerts">
                            <input type="hidden" name="section" value="alerts">
                            <div class="form-group">
                                <label style="display: flex; gap: 10px; align-items: center;">
                                    <input type="checkbox" name="low_stock_email_alerts" value="1" <?php echo getSetting('low_stock_email_alerts', '1') === '1' ? 'checked' : ''; ?>>
                                    Email all internal users when a product falls to its minimum stock level
                                </label>
                                <div class="hint">The minimum level is set per product. Each product alerts once until it is restocked.</div>
                            </div>
                            <div class="form-group">
                                <label style="display: flex; gap: 10px; align-items: center;">
                                    <input type="checkbox" name="repair_request_email_alerts" value="1" <?php echo getSetting('repair_request_email_alerts', '1') === '1' ? 'checked' : ''; ?>>
                                    Email all internal users when a customer submits a repair request
                                </label>
                            </div>
                            <div class="form-group">
                                <label>System Address (URL used in email links)</label>
                                <input type="url" name="system_url" class="form-control" value="<?php echo e(getSetting('system_url')); ?>" placeholder="<?php echo e(appUrl()); ?>">
                                <div class="hint">Leave blank to detect automatically. Set it when the system is accessed through a domain name.</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                        </form>

                    <?php else: ?>
                        <div class="stat-row">
                            <div><strong>PHP</strong><br><?php echo e(phpversion()); ?></div>
                            <div><strong>Database</strong><br><?php echo e($conn->server_info); ?></div>
                            <div><strong>Schema version</strong><br><?php echo e(getSetting('schema_version', '0')); ?></div>
                            <div><strong>System URL</strong><br><?php echo e(appUrl()); ?></div>
                        </div>
                        <p style="margin-top: 20px;">
                            <a href="users.php" class="btn btn-secondary"><i class="fas fa-users"></i> Users & Roles</a>
                            <a href="backup.php" class="btn btn-secondary"><i class="fas fa-database"></i> Backup</a>
                        </p>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => document.getElementById('logoPreview').src = e.target.result;
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
