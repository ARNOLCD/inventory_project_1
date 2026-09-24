<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

// Create backups directory if not exists
$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Create settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS backup_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Get current settings
function getBackupSetting($conn, $name, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM backup_settings WHERE setting_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

function setBackupSetting($conn, $name, $value) {
    $stmt = $conn->prepare("INSERT INTO backup_settings (setting_name, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $name, $value, $value);
    return $stmt->execute();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $frequency = $_POST['backup_frequency'];
        $email_notify = isset($_POST['email_notify']) ? '1' : '0';
        $admin_email = trim($_POST['admin_email']);
        
        setBackupSetting($conn, 'backup_frequency', $frequency);
        setBackupSetting($conn, 'email_notify', $email_notify);
        setBackupSetting($conn, 'admin_email', $admin_email);
        
        $message = 'Backup settings saved successfully!';
    }
    
    if (isset($_POST['backup_now'])) {
        // Perform manual backup
        $backup_file = performBackup($conn, $backup_dir);
        if ($backup_file) {
            $message = "Backup created successfully: " . basename($backup_file);
            
            // Send email notification if enabled
            $email_notify = getBackupSetting($conn, 'email_notify', '0');
            $admin_email = getBackupSetting($conn, 'admin_email', '');
            
            if ($email_notify === '1' && !empty($admin_email)) {
                $filesize = formatFileSize(filesize($backup_file));
                sendBackupNotificationEmail($admin_email, basename($backup_file), $filesize, true);
            }
        } else {
            $error = 'Failed to create backup. Please check server permissions.';
        }
    }
    
    if (isset($_POST['delete_backup'])) {
        $file_to_delete = $backup_dir . '/' . basename($_POST['backup_file']);
        if (file_exists($file_to_delete) && unlink($file_to_delete)) {
            $message = 'Backup deleted successfully!';
        } else {
            $error = 'Failed to delete backup file.';
        }
    }
    
    if (isset($_POST['download_backup'])) {
        $file_to_download = $backup_dir . '/' . basename($_POST['backup_file']);
        if (file_exists($file_to_download)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_to_download) . '"');
            header('Content-Length: ' . filesize($file_to_download));
            readfile($file_to_download);
            exit;
        }
    }
}

// Perform database backup
function performBackup($conn, $backup_dir) {
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . '/' . $filename;
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $sql = "-- Sims-Tech Zambia Inventory System Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Get create table statement
        $create_result = $conn->query("SHOW CREATE TABLE `$table`");
        $create_row = $create_result->fetch_row();
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create_row[1] . ";\n\n";
        
        // Get table data
        $data_result = $conn->query("SELECT * FROM `$table`");
        while ($data_row = $data_result->fetch_assoc()) {
            $values = array_map(function($val) use ($conn) {
                if ($val === null) return 'NULL';
                return "'" . $conn->real_escape_string($val) . "'";
            }, array_values($data_row));
            
            $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $sql .= "\n";
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    if (file_put_contents($filepath, $sql)) {
        return $filepath;
    }
    return false;
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Get existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backup_dir . '/' . $file;
            $backups[] = [
                'name' => $file,
                'size' => formatFileSize(filesize($filepath)),
                'date' => date('Y-m-d H:i:s', filemtime($filepath))
            ];
        }
    }
}

// Get current settings
$backup_frequency = getBackupSetting($conn, 'backup_frequency', 'daily');
$email_notify = getBackupSetting($conn, 'email_notify', '0');
$admin_email = getBackupSetting($conn, 'admin_email', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .backup-card { background: #fff; border-radius: 10px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .backup-card h3 { margin-top: 0; color: #1a365d; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .frequency-options { display: flex; gap: 15px; flex-wrap: wrap; margin: 15px 0; }
        .frequency-option { flex: 1; min-width: 120px; }
        .frequency-option input[type="radio"] { display: none; }
        .frequency-option label { display: block; padding: 15px; border: 2px solid #e2e8f0; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .frequency-option input:checked + label { border-color: #667eea; background: #ebf4ff; color: #667eea; }
        .frequency-option label:hover { border-color: #667eea; }
        .frequency-option i { font-size: 24px; display: block; margin-bottom: 8px; }
        .backup-list { max-height: 400px; overflow-y: auto; }
        .backup-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; }
        .backup-item:hover { background: #f7fafc; }
        .backup-info h4 { margin: 0 0 5px 0; color: #2d3748; }
        .backup-info p { margin: 0; font-size: 13px; color: #718096; }
        .backup-actions { display: flex; gap: 8px; }
        .btn-icon { padding: 8px 12px; border-radius: 5px; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-download { background: #48bb78; color: white; }
        .btn-download:hover { background: #38a169; }
        .btn-delete { background: #fc8181; color: white; }
        .btn-delete:hover { background: #f56565; }
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 26px; }
        .toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: #667eea; }
        input:checked + .toggle-slider:before { transform: translateX(24px); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="fas fa-database"></i> Database Backup</h1>
                    <p>Manage automatic and manual database backups</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="grid-2">
                    <!-- Backup Settings -->
                    <div class="backup-card">
                        <h3><i class="fas fa-cog"></i> Backup Settings</h3>
                        <form method="POST">
                            <p><strong>Automatic Backup Frequency:</strong></p>
                            <div class="frequency-options">
                                <div class="frequency-option">
                                    <input type="radio" name="backup_frequency" id="freq_hourly" value="hourly" <?php echo $backup_frequency === 'hourly' ? 'checked' : ''; ?>>
                                    <label for="freq_hourly">
                                        <i class="fas fa-clock"></i>
                                        Hourly
                                    </label>
                                </div>
                                <div class="frequency-option">
                                    <input type="radio" name="backup_frequency" id="freq_daily" value="daily" <?php echo $backup_frequency === 'daily' ? 'checked' : ''; ?>>
                                    <label for="freq_daily">
                                        <i class="fas fa-calendar-day"></i>
                                        Daily
                                    </label>
                                </div>
                                <div class="frequency-option">
                                    <input type="radio" name="backup_frequency" id="freq_weekly" value="weekly" <?php echo $backup_frequency === 'weekly' ? 'checked' : ''; ?>>
                                    <label for="freq_weekly">
                                        <i class="fas fa-calendar-week"></i>
                                        Weekly
                                    </label>
                                </div>
                                <div class="frequency-option">
                                    <input type="radio" name="backup_frequency" id="freq_monthly" value="monthly" <?php echo $backup_frequency === 'monthly' ? 'checked' : ''; ?>>
                                    <label for="freq_monthly">
                                        <i class="fas fa-calendar-alt"></i>
                                        Monthly
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 20px;">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <span>Email Notifications:</span>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="email_notify" <?php echo $email_notify === '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label>Admin Email for Notifications:</label>
                                <input type="email" name="admin_email" class="form-control" value="<?php echo htmlspecialchars($admin_email); ?>" placeholder="admin@example.com">
                            </div>
                            
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- Manual Backup -->
                    <div class="backup-card">
                        <h3><i class="fas fa-download"></i> Manual Backup</h3>
                        <p>Create an instant backup of your database.</p>
                        <form method="POST" style="margin-top: 20px;">
                            <button type="submit" name="backup_now" class="btn btn-success btn-lg" style="width: 100%;">
                                <i class="fas fa-database"></i> Backup Now
                            </button>
                        </form>
                        
                        <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <p style="margin: 0;"><strong>Last Backup:</strong></p>
                            <p style="margin: 5px 0 0 0; color: #718096;">
                                <?php echo !empty($backups) ? $backups[0]['date'] : 'No backups yet'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Backup History -->
                <div class="backup-card">
                    <h3><i class="fas fa-history"></i> Backup History</h3>
                    <?php if (empty($backups)): ?>
                        <p style="text-align: center; color: #718096; padding: 30px;">
                            <i class="fas fa-folder-open" style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                            No backups found. Create your first backup above.
                        </p>
                    <?php else: ?>
                        <div class="backup-list">
                            <?php foreach ($backups as $backup): ?>
                                <div class="backup-item">
                                    <div class="backup-info">
                                        <h4><i class="fas fa-file-code"></i> <?php echo htmlspecialchars($backup['name']); ?></h4>
                                        <p><i class="fas fa-calendar"></i> <?php echo $backup['date']; ?> | <i class="fas fa-weight"></i> <?php echo $backup['size']; ?></p>
                                    </div>
                                    <div class="backup-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                            <button type="submit" name="download_backup" class="btn-icon btn-download" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                            <button type="submit" name="delete_backup" class="btn-icon btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
