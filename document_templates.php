<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Create document_templates table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS document_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('invoice', 'quotation', 'receipt', 'other') NOT NULL DEFAULT 'other',
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    description TEXT,
    is_default TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Create uploads directory if not exists
$upload_dir = 'uploads/templates/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload') {
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $description = trim($_POST['description'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if (empty($name)) {
            $error = 'Template name is required.';
        } elseif (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a file to upload.';
        } else {
            $file = $_FILES['template_file'];
            $original_name = $file['name'];
            $file_size = $file['size'];
            $file_type = $file['type'];
            
            // Get file extension
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            
            // Allowed file types
            $allowed_types = [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 
                'txt', 'rtf', 'html', 'htm', 'png', 'jpg', 'jpeg'
            ];
            
            if (!in_array($ext, $allowed_types)) {
                $error = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
            } elseif ($file_size > 10 * 1024 * 1024) { // 10MB limit
                $error = 'File size exceeds 10MB limit.';
            } else {
                // Generate unique filename
                $new_filename = uniqid('template_') . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // If setting as default, unset other defaults of same type
                    if ($is_default) {
                        $conn->query("UPDATE document_templates SET is_default = 0 WHERE user_id = {$user['id']} AND type = '$type'");
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO document_templates (user_id, name, type, file_name, original_name, file_type, file_size, description, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssisi", $user['id'], $name, $type, $new_filename, $original_name, $file_type, $file_size, $description, $is_default);
                    
                    if ($stmt->execute()) {
                        $message = 'Template uploaded successfully!';
                    } else {
                        $error = 'Error saving template to database.';
                        unlink($upload_path); // Remove uploaded file
                    }
                } else {
                    $error = 'Error uploading file.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        
        // Get file info before deleting
        $stmt = $conn->prepare("SELECT file_name FROM document_templates WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $template = $result->fetch_assoc();
            $file_path = $upload_dir . $template['file_name'];
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM document_templates WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user['id']);
            
            if ($stmt->execute()) {
                // Delete file
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                $message = 'Template deleted successfully!';
            } else {
                $error = 'Error deleting template.';
            }
        } else {
            $error = 'Template not found or access denied.';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $description = trim($_POST['description'] ?? '');
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        
        if (empty($name)) {
            $error = 'Template name is required.';
        } else {
            // Get current template info
            $stmt = $conn->prepare("SELECT * FROM document_templates WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $current_template = $result->fetch_assoc();
                
                // Handle new file upload if provided
                $new_filename = $current_template['file_name'];
                $original_name = $current_template['original_name'];
                $file_type = $current_template['file_type'];
                $file_size = $current_template['file_size'];
                
                if (isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['template_file'];
                    $original_name = $file['name'];
                    $file_size = $file['size'];
                    $file_type = $file['type'];
                    
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'rtf', 'html', 'htm', 'png', 'jpg', 'jpeg'];
                    
                    if (in_array($ext, $allowed_types) && $file_size <= 10 * 1024 * 1024) {
                        // Delete old file
                        $old_file_path = $upload_dir . $current_template['file_name'];
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                        }
                        
                        // Upload new file
                        $new_filename = uniqid('template_') . '_' . time() . '.' . $ext;
                        move_uploaded_file($file['tmp_name'], $upload_dir . $new_filename);
                    }
                }
                
                // If setting as default, unset other defaults of same type
                if ($is_default) {
                    $conn->query("UPDATE document_templates SET is_default = 0 WHERE user_id = {$user['id']} AND type = '$type'");
                }
                
                // Update template
                $stmt = $conn->prepare("UPDATE document_templates SET name = ?, type = ?, file_name = ?, original_name = ?, file_type = ?, file_size = ?, description = ?, is_default = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("sssssissii", $name, $type, $new_filename, $original_name, $file_type, $file_size, $description, $is_default, $id, $user['id']);
                
                if ($stmt->execute()) {
                    $message = 'Template updated successfully!';
                } else {
                    $error = 'Error updating template.';
                }
            } else {
                $error = 'Template not found or access denied.';
            }
        }
    } elseif ($action === 'set_default') {
        $id = intval($_POST['id']);
        
        // Get template type
        $stmt = $conn->prepare("SELECT type FROM document_templates WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $template = $result->fetch_assoc();
            
            // Unset other defaults of same type
            $conn->query("UPDATE document_templates SET is_default = 0 WHERE user_id = {$user['id']} AND type = '{$template['type']}'");
            
            // Set this as default
            $stmt = $conn->prepare("UPDATE document_templates SET is_default = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = 'Default template updated!';
            } else {
                $error = 'Error updating default template.';
            }
        }
    }
}

// Get user's templates
$stmt = $conn->prepare("SELECT * FROM document_templates WHERE user_id = ? ORDER BY type, name");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$templates = $stmt->get_result();

// Group templates by type
$grouped_templates = [
    'invoice' => [],
    'quotation' => [],
    'receipt' => [],
    'other' => []
];

while ($row = $templates->fetch_assoc()) {
    $grouped_templates[$row['type']][] = $row;
}

// File type icons
function getFileIcon($ext) {
    $icons = [
        'pdf' => 'fa-file-pdf text-danger',
        'doc' => 'fa-file-word text-primary',
        'docx' => 'fa-file-word text-primary',
        'xls' => 'fa-file-excel text-success',
        'xlsx' => 'fa-file-excel text-success',
        'txt' => 'fa-file-alt text-secondary',
        'rtf' => 'fa-file-alt text-secondary',
        'html' => 'fa-file-code text-warning',
        'htm' => 'fa-file-code text-warning',
        'png' => 'fa-file-image text-info',
        'jpg' => 'fa-file-image text-info',
        'jpeg' => 'fa-file-image text-info',
        'odt' => 'fa-file-alt text-primary',
        'ods' => 'fa-file-alt text-success'
    ];
    return $icons[$ext] ?? 'fa-file text-secondary';
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Templates - AC-TECHNOLOGY</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .template-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
        .template-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border: 2px solid transparent; transition: all 0.3s; }
        .template-card:hover { border-color: #667eea; transform: translateY(-2px); }
        .template-card.is-default { border-color: #48bb78; background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%); }
        .template-icon { font-size: 48px; margin-bottom: 15px; }
        .template-name { font-weight: 600; font-size: 16px; margin-bottom: 5px; }
        .template-meta { color: #718096; font-size: 13px; margin-bottom: 10px; }
        .template-actions { display: flex; gap: 8px; margin-top: 15px; }
        .template-actions .btn { padding: 6px 12px; font-size: 12px; }
        .default-badge { background: #48bb78; color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; display: inline-block; margin-bottom: 10px; }
        .type-section { margin-bottom: 40px; }
        .type-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }
        .type-header i { font-size: 24px; color: #667eea; }
        .type-header h3 { margin: 0; color: #2d3748; }
        .upload-zone { border: 2px dashed #cbd5e0; border-radius: 12px; padding: 40px; text-align: center; background: #f7fafc; cursor: pointer; transition: all 0.3s; }
        .upload-zone:hover { border-color: #667eea; background: #edf2f7; }
        .upload-zone.dragover { border-color: #48bb78; background: #f0fff4; }
        .upload-zone i { font-size: 48px; color: #a0aec0; margin-bottom: 15px; }
        .upload-zone p { color: #718096; margin: 0; }
        .upload-zone .supported { font-size: 12px; color: #a0aec0; margin-top: 10px; }
        .empty-state { text-align: center; padding: 40px; color: #a0aec0; }
        .empty-state i { font-size: 64px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><i class="fas fa-file-import"></i> Document Templates</h1>
                        <p>Upload and manage your custom invoice, quotation, and receipt templates</p>
                    </div>
                    <button class="btn btn-primary" onclick="openUploadModal()">
                        <i class="fas fa-upload"></i> Upload Template
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Invoice Templates -->
                <div class="type-section">
                    <div class="type-header">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h3>Invoice Templates</h3>
                        <span class="badge badge-info"><?php echo count($grouped_templates['invoice']); ?> templates</span>
                    </div>
                    <?php if (count($grouped_templates['invoice']) > 0): ?>
                        <div class="template-grid">
                            <?php foreach ($grouped_templates['invoice'] as $template): 
                                $ext = strtolower(pathinfo($template['original_name'], PATHINFO_EXTENSION));
                            ?>
                                <div class="template-card <?php echo $template['is_default'] ? 'is-default' : ''; ?>">
                                    <?php if ($template['is_default']): ?>
                                        <span class="default-badge"><i class="fas fa-star"></i> Default</span>
                                    <?php endif; ?>
                                    <div class="template-icon">
                                        <i class="fas <?php echo getFileIcon($ext); ?>"></i>
                                    </div>
                                    <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                    <div class="template-meta">
                                        <i class="fas fa-file"></i> <?php echo htmlspecialchars($template['original_name']); ?><br>
                                        <i class="fas fa-weight"></i> <?php echo formatFileSize($template['file_size']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                    </div>
                                    <?php if ($template['description']): ?>
                                        <p style="font-size: 13px; color: #718096;"><?php echo htmlspecialchars($template['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="template-actions">
                                        <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-primary" download="<?php echo htmlspecialchars($template['original_name']); ?>" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-secondary" target="_blank" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-warning" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$template['is_default']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="set_default">
                                                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <p>No invoice templates uploaded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quotation Templates -->
                <div class="type-section">
                    <div class="type-header">
                        <i class="fas fa-file-signature"></i>
                        <h3>Quotation Templates</h3>
                        <span class="badge badge-info"><?php echo count($grouped_templates['quotation']); ?> templates</span>
                    </div>
                    <?php if (count($grouped_templates['quotation']) > 0): ?>
                        <div class="template-grid">
                            <?php foreach ($grouped_templates['quotation'] as $template): 
                                $ext = strtolower(pathinfo($template['original_name'], PATHINFO_EXTENSION));
                            ?>
                                <div class="template-card <?php echo $template['is_default'] ? 'is-default' : ''; ?>">
                                    <?php if ($template['is_default']): ?>
                                        <span class="default-badge"><i class="fas fa-star"></i> Default</span>
                                    <?php endif; ?>
                                    <div class="template-icon">
                                        <i class="fas <?php echo getFileIcon($ext); ?>"></i>
                                    </div>
                                    <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                    <div class="template-meta">
                                        <i class="fas fa-file"></i> <?php echo htmlspecialchars($template['original_name']); ?><br>
                                        <i class="fas fa-weight"></i> <?php echo formatFileSize($template['file_size']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                    </div>
                                    <?php if ($template['description']): ?>
                                        <p style="font-size: 13px; color: #718096;"><?php echo htmlspecialchars($template['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="template-actions">
                                        <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-primary" download="<?php echo htmlspecialchars($template['original_name']); ?>" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-secondary" target="_blank" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-warning" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$template['is_default']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="set_default">
                                                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-file-signature"></i>
                            <p>No quotation templates uploaded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Receipt Templates -->
                <div class="type-section">
                    <div class="type-header">
                        <i class="fas fa-receipt"></i>
                        <h3>Receipt Templates</h3>
                        <span class="badge badge-info"><?php echo count($grouped_templates['receipt']); ?> templates</span>
                    </div>
                    <?php if (count($grouped_templates['receipt']) > 0): ?>
                        <div class="template-grid">
                            <?php foreach ($grouped_templates['receipt'] as $template): 
                                $ext = strtolower(pathinfo($template['original_name'], PATHINFO_EXTENSION));
                            ?>
                                <div class="template-card <?php echo $template['is_default'] ? 'is-default' : ''; ?>">
                                    <?php if ($template['is_default']): ?>
                                        <span class="default-badge"><i class="fas fa-star"></i> Default</span>
                                    <?php endif; ?>
                                    <div class="template-icon">
                                        <i class="fas <?php echo getFileIcon($ext); ?>"></i>
                                    </div>
                                    <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                    <div class="template-meta">
                                        <i class="fas fa-file"></i> <?php echo htmlspecialchars($template['original_name']); ?><br>
                                        <i class="fas fa-weight"></i> <?php echo formatFileSize($template['file_size']); ?><br>
                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                    </div>
                                    <?php if ($template['description']): ?>
                                        <p style="font-size: 13px; color: #718096;"><?php echo htmlspecialchars($template['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="template-actions">
                                        <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-primary" download="<?php echo htmlspecialchars($template['original_name']); ?>" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-secondary" target="_blank" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-warning" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!$template['is_default']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="set_default">
                                                <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                <button type="submit" class="btn btn-success" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No receipt templates uploaded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Other Templates -->
                <?php if (count($grouped_templates['other']) > 0): ?>
                <div class="type-section">
                    <div class="type-header">
                        <i class="fas fa-folder"></i>
                        <h3>Other Templates</h3>
                        <span class="badge badge-info"><?php echo count($grouped_templates['other']); ?> templates</span>
                    </div>
                    <div class="template-grid">
                        <?php foreach ($grouped_templates['other'] as $template): 
                            $ext = strtolower(pathinfo($template['original_name'], PATHINFO_EXTENSION));
                        ?>
                            <div class="template-card <?php echo $template['is_default'] ? 'is-default' : ''; ?>">
                                <?php if ($template['is_default']): ?>
                                    <span class="default-badge"><i class="fas fa-star"></i> Default</span>
                                <?php endif; ?>
                                <div class="template-icon">
                                    <i class="fas <?php echo getFileIcon($ext); ?>"></i>
                                </div>
                                <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                                <div class="template-meta">
                                    <i class="fas fa-file"></i> <?php echo htmlspecialchars($template['original_name']); ?><br>
                                    <i class="fas fa-weight"></i> <?php echo formatFileSize($template['file_size']); ?><br>
                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($template['created_at'])); ?>
                                </div>
                                <?php if ($template['description']): ?>
                                    <p style="font-size: 13px; color: #718096;"><?php echo htmlspecialchars($template['description']); ?></p>
                                <?php endif; ?>
                                <div class="template-actions">
                                    <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-primary" download="<?php echo htmlspecialchars($template['original_name']); ?>" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="uploads/templates/<?php echo $template['file_name']; ?>" class="btn btn-secondary" target="_blank" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-warning" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal" style="max-width: 550px;">
            <div class="modal-header">
                <h3><i class="fas fa-upload"></i> Upload Template</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="form-group">
                        <label>Template Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Company Invoice Template">
                    </div>
                    
                    <div class="form-group">
                        <label>Template Type *</label>
                        <select name="type" class="form-control" required>
                            <option value="invoice">Invoice</option>
                            <option value="quotation">Quotation</option>
                            <option value="receipt">Receipt</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this template"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Template File *</label>
                        <div class="upload-zone" id="uploadZone" onclick="document.getElementById('templateFile').click();">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to select or drag and drop your file</p>
                            <p class="supported">Supported: PDF, Word, Excel, Images (Max 10MB)</p>
                            <div id="filePreview" style="margin-top: 10px; display: none;">
                                <i class="fas fa-file"></i> <span id="fileName"></span>
                            </div>
                        </div>
                        <input type="file" name="template_file" id="templateFile" style="display: none;" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.rtf,.html,.htm,.png,.jpg,.jpeg" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_default" value="1">
                            Set as default template for this type
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal" style="max-width: 550px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Template</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="form-group">
                        <label>Template Name *</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Template Type *</label>
                        <select name="type" id="editType" class="form-control" required>
                            <option value="invoice">Invoice</option>
                            <option value="quotation">Quotation</option>
                            <option value="receipt">Receipt</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Current File</label>
                        <div id="currentFileInfo" style="background: #f7fafc; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                            <i class="fas fa-file"></i> <span id="currentFileName"></span>
                        </div>
                        <label>Replace File (optional)</label>
                        <div class="upload-zone" id="editUploadZone" onclick="document.getElementById('editTemplateFile').click();">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to select a new file (optional)</p>
                            <p class="supported">Leave empty to keep current file</p>
                            <div id="editFilePreview" style="margin-top: 10px; display: none;">
                                <i class="fas fa-file"></i> <span id="editFileName"></span>
                            </div>
                        </div>
                        <input type="file" name="template_file" id="editTemplateFile" style="display: none;" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.odt,.ods,.txt,.rtf,.html,.htm,.png,.jpg,.jpeg">
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_default" id="editIsDefault" value="1">
                            Set as default template for this type
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('uploadModal').classList.remove('active');
        }
        
        function openEditModal(template) {
            document.getElementById('editId').value = template.id;
            document.getElementById('editName').value = template.name;
            document.getElementById('editType').value = template.type;
            document.getElementById('editDescription').value = template.description || '';
            document.getElementById('editIsDefault').checked = template.is_default == 1;
            document.getElementById('currentFileName').textContent = template.original_name;
            document.getElementById('editFilePreview').style.display = 'none';
            document.getElementById('editTemplateFile').value = '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        
        // File input change handler for upload
        document.getElementById('templateFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('filePreview').style.display = 'block';
            }
        });
        
        // File input change handler for edit
        document.getElementById('editTemplateFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('editFileName').textContent = file.name;
                document.getElementById('editFilePreview').style.display = 'block';
            }
        });
        
        // Drag and drop for upload
        const uploadZone = document.getElementById('uploadZone');
        
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('templateFile').files = files;
                document.getElementById('fileName').textContent = files[0].name;
                document.getElementById('filePreview').style.display = 'block';
            }
        });
        
        // Drag and drop for edit
        const editUploadZone = document.getElementById('editUploadZone');
        
        editUploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        editUploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        editUploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('editTemplateFile').files = files;
                document.getElementById('editFileName').textContent = files[0].name;
                document.getElementById('editFilePreview').style.display = 'block';
            }
        });
    </script>
</body>
</html>
