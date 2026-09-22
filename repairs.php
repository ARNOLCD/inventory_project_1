<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Create repairs table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS repairs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_number VARCHAR(50) UNIQUE NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20),
        customer_email VARCHAR(100),
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
        FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Add item_photo column if not exists
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS item_photo VARCHAR(255) DEFAULT NULL AFTER serial_number");

// Add customer_id column if not exists
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS customer_id INT DEFAULT NULL AFTER customer_email");

// Add foreign key constraint for customer_id if not exists (safe approach)
try {
    $conn->query("ALTER TABLE repairs ADD CONSTRAINT fk_repairs_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Foreign key might already exist, ignore error
}

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $ticket_number = generateTicketNumber($conn);
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $customer_email = trim($_POST['customer_email']);
        $device_type = $_POST['device_type'];
        $device_brand = trim($_POST['device_brand']);
        $device_model = trim($_POST['device_model']);
        $serial_number = trim($_POST['serial_number']);
        $problem_description = trim($_POST['problem_description']);
        $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
        $priority = $_POST['priority'];
        $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        
        if (empty($customer_name) || empty($device_type) || empty($problem_description)) {
            $error = 'Customer name, device type, and problem description are required.';
        } else {
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
            
            $stmt = $conn->prepare("INSERT INTO repairs (ticket_number, customer_name, customer_phone, customer_email, customer_id, device_type, device_brand, device_model, serial_number, item_photo, problem_description, estimated_cost, priority, received_by, technician_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'booked')");
            $stmt->bind_param("sssssisssdsii", $ticket_number, $customer_name, $customer_phone, $customer_email, $customer_id, $device_type, $device_brand, $device_model, $serial_number, $item_photo, $problem_description, $estimated_cost, $priority, $user['id'], $technician_id);
            
            if ($stmt->execute()) {
                $message = "Repair ticket $ticket_number created successfully!";
            } else {
                $error = 'Error creating repair ticket.';
            }
        }
    } elseif ($action === 'update_status') {
        $id = intval($_POST['id']);
        $new_status = $_POST['status'];
        $notes = trim($_POST['notes'] ?? '');
        
        // Update status and set appropriate date
        $date_field = '';
        if ($new_status === 'in_progress') {
            $date_field = ", started_date = NOW()";
        } elseif ($new_status === 'completed') {
            $date_field = ", completed_date = NOW()";
        } elseif ($new_status === 'delivered') {
            $date_field = ", delivered_date = NOW()";
        }
        
        $sql = "UPDATE repairs SET status = ?, repair_notes = CONCAT(IFNULL(repair_notes, ''), '\n[" . date('Y-m-d H:i') . "] Status: $new_status - ', ?) $date_field WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_status, $notes, $id);
        
        if ($stmt->execute()) {
            $message = 'Repair status updated successfully!';
            
            // Send email notification to customer if customer email exists
            $repair = $conn->query("SELECT r.*, u.email as customer_email, u.full_name as customer_name 
                                    FROM repairs r 
                                    LEFT JOIN users u ON r.customer_id = u.id 
                                    WHERE r.id = $id")->fetch_assoc();
            
            if ($repair && !empty($repair['customer_email'])) {
                $deviceInfo = $repair['device_type'];
                if ($repair['device_brand']) $deviceInfo .= ' - ' . $repair['device_brand'];
                if ($repair['device_model']) $deviceInfo .= ' ' . $repair['device_model'];
                
                sendRepairStatusEmail(
                    $repair['customer_email'],
                    $repair['customer_name'],
                    $repair['ticket_number'],
                    $new_status,
                    $deviceInfo,
                    $notes
                );
            }
        } else {
            $error = 'Error updating status.';
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id']);
        $customer_name = trim($_POST['customer_name']);
        $customer_phone = trim($_POST['customer_phone']);
        $device_type = $_POST['device_type'];
        $device_brand = trim($_POST['device_brand']);
        $device_model = trim($_POST['device_model']);
        $problem_description = trim($_POST['problem_description']);
        $diagnosis = trim($_POST['diagnosis']);
        $estimated_cost = floatval($_POST['estimated_cost'] ?? 0);
        $final_cost = floatval($_POST['final_cost'] ?? 0);
        $priority = $_POST['priority'];
        $technician_id = !empty($_POST['technician_id']) ? intval($_POST['technician_id']) : null;
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        
        // Handle photo upload on edit
        $photo_sql = '';
        $photo_param = '';
        $item_photo = null;
        if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {
            $photo = $_FILES['item_photo'];
            $ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed) && $photo['size'] <= 5 * 1024 * 1024) {
                // Delete old photo if exists
                $old = $conn->query("SELECT item_photo FROM repairs WHERE id = $id")->fetch_assoc();
                if (!empty($old['item_photo']) && file_exists($repair_upload_dir . $old['item_photo'])) {
                    unlink($repair_upload_dir . $old['item_photo']);
                }
                $item_photo = 'repair_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($photo['tmp_name'], $repair_upload_dir . $item_photo);
                $photo_sql = ', item_photo=?';
                $photo_param = 's';
            }
        }
        
        if ($photo_sql) {
            $stmt = $conn->prepare("UPDATE repairs SET customer_name=?, customer_phone=?, device_type=?, device_brand=?, device_model=?, problem_description=?, diagnosis=?, estimated_cost=?, final_cost=?, priority=?, technician_id=?, customer_id=?" . $photo_sql . " WHERE id=?");
            $stmt->bind_param("sssssssddsiii" . $photo_param . "i", $customer_name, $customer_phone, $device_type, $device_brand, $device_model, $problem_description, $diagnosis, $estimated_cost, $final_cost, $priority, $technician_id, $customer_id, $item_photo, $id);
        } else {
            $stmt = $conn->prepare("UPDATE repairs SET customer_name=?, customer_phone=?, device_type=?, device_brand=?, device_model=?, problem_description=?, diagnosis=?, estimated_cost=?, final_cost=?, priority=?, technician_id=?, customer_id=? WHERE id=?");
            $stmt->bind_param("sssssssddsiii", $customer_name, $customer_phone, $device_type, $device_brand, $device_model, $problem_description, $diagnosis, $estimated_cost, $final_cost, $priority, $technician_id, $customer_id, $id);
        }
        
        if ($stmt->execute()) {
            $message = 'Repair updated successfully!';
        } else {
            $error = 'Error updating repair.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM repairs WHERE id = $id")) {
            $message = 'Repair ticket deleted successfully!';
        } else {
            $error = 'Error deleting repair ticket.';
        }
    }
}

// Get filter
$status_filter = $_GET['status'] ?? '';
$technician_filter = $_GET['technician'] ?? '';

// Build query
$where = "WHERE 1=1";
if ($status_filter) {
    $where .= " AND r.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($technician_filter) {
    $where .= " AND r.technician_id = " . intval($technician_filter);
}

// Get repairs
$repairs = $conn->query("
    SELECT r.*, 
           u1.full_name as received_by_name,
           u2.full_name as technician_name,
           u3.full_name as customer_full_name,
           u3.email as customer_email,
           u3.phone as customer_phone
    FROM repairs r 
    LEFT JOIN users u1 ON r.received_by = u1.id 
    LEFT JOIN users u2 ON r.technician_id = u2.id 
    LEFT JOIN users u3 ON r.customer_id = u3.id 
    $where 
    ORDER BY 
        CASE r.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'low' THEN 4 
        END,
        r.created_at DESC
");

// Get counts
$booked_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE status = 'booked'")->fetch_assoc()['c'];
$in_progress_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE status = 'in_progress'")->fetch_assoc()['c'];
$completed_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE status = 'completed'")->fetch_assoc()['c'];
$delivered_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE status = 'delivered'")->fetch_assoc()['c'];

// Get technicians for dropdown
$technicians = $conn->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'employee') ORDER BY full_name");

// Get customers for dropdown
$customers = $conn->query("SELECT id, full_name, email FROM users WHERE role = 'customer' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Tracking - AC-TECHNOLOGY</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .status-booked { background: #3182ce; color: white; }
        .status-in_progress { background: #dd6b20; color: white; }
        .status-completed { background: #38a169; color: white; }
        .status-delivered { background: #805ad5; color: white; }
        .status-cancelled { background: #e53e3e; color: white; }
        .priority-urgent { border-left: 4px solid #e53e3e; }
        .priority-high { border-left: 4px solid #dd6b20; }
        .priority-normal { border-left: 4px solid #3182ce; }
        .priority-low { border-left: 4px solid #a0aec0; }
        .repair-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .repair-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .repair-ticket {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a365d;
        }
        .repair-device {
            color: #4a5568;
            font-size: 0.9rem;
        }
        .repair-customer {
            font-weight: 500;
        }
        .repair-problem {
            background: #f7fafc;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            margin: 10px 0;
        }
        .repair-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .status-btn {
            padding: 5px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .status-btn:hover { opacity: 0.8; }
        .btn-start { background: #dd6b20; color: white; }
        .btn-complete { background: #38a169; color: white; }
        .btn-deliver { background: #805ad5; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1><i class="fas fa-tools"></i> Repair Tracking</h1>
                        <p>Track repair status for laptops, phones, and other devices</p>
                    </div>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> New Repair Ticket
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $booked_count; ?></h3>
                            <p>Booked</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-wrench"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $in_progress_count; ?></h3>
                            <p>In Progress</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $completed_count; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple" style="background: #805ad5;">
                            <i class="fas fa-hand-holding"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $delivered_count; ?></h3>
                            <p>Delivered</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                        <a href="repairs.php" class="btn <?php echo !$status_filter && !$technician_filter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">All</a>
                        <a href="repairs.php?status=booked" class="btn <?php echo $status_filter === 'booked' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-clipboard-list"></i> Booked
                        </a>
                        <a href="repairs.php?status=in_progress" class="btn <?php echo $status_filter === 'in_progress' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-wrench"></i> In Progress
                        </a>
                        <a href="repairs.php?status=completed" class="btn <?php echo $status_filter === 'completed' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-check-circle"></i> Completed
                        </a>
                        <a href="repairs.php?status=delivered" class="btn <?php echo $status_filter === 'delivered' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-hand-holding"></i> Delivered
                        </a>
                        
                        <div style="display: flex; align-items: center; gap: 8px; margin-left: 10px;">
                            <label style="font-size: 0.9rem; color: #4a5568;"><i class="fas fa-user-cog"></i> Technician:</label>
                            <select class="form-control" style="width: 180px; padding: 5px 10px; font-size: 0.85rem;" onchange="filterByTechnician(this.value)">
                                <option value="">All Technicians</option>
                                <?php 
                                $technicians->data_seek(0);
                                while ($tech = $technicians->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $tech['id']; ?>" <?php echo $technician_filter == $tech['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tech['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <span style="margin-left: auto;">
                            <input type="text" id="searchInput" class="form-control" placeholder="Search..." style="width: 200px;" onkeyup="searchRepairs()">
                        </span>
                    </div>
                </div>
                
                <!-- Repairs List -->
                <div id="repairsList">
                    <?php if ($repairs->num_rows > 0): ?>
                        <?php while ($repair = $repairs->fetch_assoc()): ?>
                            <div class="repair-card priority-<?php echo $repair['priority']; ?>" data-search="<?php echo strtolower($repair['ticket_number'] . ' ' . $repair['customer_name'] . ' ' . $repair['device_type'] . ' ' . $repair['device_brand'] . ' ' . ($repair['technician_name'] ?? '')); ?>">
                                <div class="repair-header">
                                    <div>
                                        <div class="repair-ticket"><?php echo htmlspecialchars($repair['ticket_number']); ?></div>
                                        <div class="repair-device">
                                            <i class="fas fa-<?php echo $repair['device_type'] === 'laptop' ? 'laptop' : ($repair['device_type'] === 'phone' ? 'mobile-alt' : 'desktop'); ?>"></i>
                                            <?php echo htmlspecialchars($repair['device_type']); ?>
                                            <?php if ($repair['device_brand']): ?> - <?php echo htmlspecialchars($repair['device_brand']); ?><?php endif; ?>
                                            <?php if ($repair['device_model']): ?> <?php echo htmlspecialchars($repair['device_model']); ?><?php endif; ?>
                                        </div>
                                        <?php if ($repair['technician_name']): ?>
                                            <div style="margin-top: 8px; display: flex; align-items: center; gap: 6px;">
                                                <i class="fas fa-user-cog" style="color: #3182ce; font-size: 0.85rem;"></i>
                                                <span style="font-size: 0.85rem; color: #2c5282; font-weight: 500;">
                                                    <?php echo htmlspecialchars($repair['technician_name']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="badge status-<?php echo $repair['status']; ?>" style="padding: 5px 12px; border-radius: 20px;">
                                            <?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?>
                                        </span>
                                        <div style="font-size: 0.8rem; color: #718096; margin-top: 5px;">
                                            <?php echo date('M d, Y', strtotime($repair['received_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="repair-customer">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($repair['customer_name']); ?>
                                    <?php if ($repair['customer_phone']): ?>
                                        <span style="color: #718096; margin-left: 15px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($repair['customer_phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($repair['item_photo'])): ?>
                                    <div style="margin: 10px 0;">
                                        <img src="uploads/repairs/<?php echo htmlspecialchars($repair['item_photo']); ?>" 
                                             alt="Item Photo" 
                                             style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 2px solid #e2e8f0; cursor: pointer; object-fit: cover;"
                                             onclick="viewPhoto('uploads/repairs/<?php echo htmlspecialchars($repair['item_photo']); ?>')">
                                        <div style="font-size: 0.75rem; color: #718096; margin-top: 3px;"><i class="fas fa-camera"></i> Item photo (click to enlarge)</div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="repair-problem">
                                    <strong>Problem:</strong> <?php echo htmlspecialchars($repair['problem_description']); ?>
                                </div>
                                
                                <?php if ($repair['diagnosis']): ?>
                                    <div style="font-size: 0.85rem; color: #4a5568; margin-bottom: 10px;">
                                        <strong>Diagnosis:</strong> <?php echo htmlspecialchars($repair['diagnosis']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div style="font-size: 0.85rem; color: #718096;">
                                        <?php if ($repair['estimated_cost']): ?>
                                            Est: K<?php echo number_format($repair['estimated_cost'], 2); ?>
                                        <?php endif; ?>
                                        <?php if ($repair['final_cost']): ?>
                                            | Final: <strong>K<?php echo number_format($repair['final_cost'], 2); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($repair['technician_name']): ?>
                                        <div style="display: flex; align-items: center; gap: 8px; background: #ebf8ff; padding: 6px 12px; border-radius: 20px; border: 1px solid #bee3f8;">
                                            <i class="fas fa-user-cog" style="color: #3182ce;"></i>
                                            <span style="font-size: 0.85rem; color: #2c5282; font-weight: 500;">
                                                Assigned: <?php echo htmlspecialchars($repair['technician_name']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="repair-actions">
                                        <?php if ($repair['status'] === 'booked'): ?>
                                            <button class="status-btn btn-start" onclick="updateStatus(<?php echo $repair['id']; ?>, 'in_progress')">
                                                <i class="fas fa-play"></i> Start Work
                                            </button>
                                        <?php elseif ($repair['status'] === 'in_progress'): ?>
                                            <button class="status-btn btn-complete" onclick="updateStatus(<?php echo $repair['id']; ?>, 'completed')">
                                                <i class="fas fa-check"></i> Mark Complete
                                            </button>
                                        <?php elseif ($repair['status'] === 'completed'): ?>
                                            <button class="status-btn btn-deliver" onclick="updateStatus(<?php echo $repair['id']; ?>, 'delivered')">
                                                <i class="fas fa-hand-holding"></i> Mark Delivered
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="action-btn edit" onclick='editRepair(<?php echo htmlspecialchars(json_encode($repair), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteRepair(<?php echo $repair['id']; ?>, '<?php echo htmlspecialchars($repair['ticket_number']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center" style="padding: 40px;">
                                <i class="fas fa-tools" style="font-size: 48px; color: #a0aec0; margin-bottom: 15px;"></i>
                                <p>No repair tickets found</p>
                                <button class="btn btn-primary" onclick="openAddModal()">Create First Ticket</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="repairModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-tools"></i> New Repair Ticket</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="repairForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="repairId">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Customer Name *</label>
                            <input type="text" name="customer_name" id="customerName" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="customer_phone" id="customerPhone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="customer_email" id="customerEmail" class="form-control">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Device Type *</label>
                            <select name="device_type" id="deviceType" class="form-control" required>
                                <option value="laptop">Laptop</option>
                                <option value="phone">Phone</option>
                                <option value="desktop">Desktop</option>
                                <option value="tablet">Tablet</option>
                                <option value="printer">Printer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Brand</label>
                            <input type="text" name="device_brand" id="deviceBrand" class="form-control" placeholder="e.g., HP, Dell, Samsung">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="device_model" id="deviceModel" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" id="serialNumber" class="form-control">
                    </div>
                    
                    <div class="form-group" id="customerGroup">
                        <label><i class="fas fa-user"></i> Link to Customer Account (Optional)</label>
                        <select name="customer_id" id="customerId" class="form-control">
                            <option value="">-- No Customer Account --</option>
                            <?php 
                            $customers->data_seek(0);
                            while ($customer = $customers->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #718096;">Link to existing customer account for email notifications</small>
                    </div>
                    
                    <div class="form-group" id="technicianGroup">
                        <label><i class="fas fa-user-cog"></i> Assigned Technician</label>
                        <select name="technician_id" id="technicianId" class="form-control">
                            <option value="">-- Select Technician --</option>
                            <?php 
                            $technicians->data_seek(0);
                            while ($tech = $technicians->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $tech['id']; ?>"><?php echo htmlspecialchars($tech['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small style="color: #718096;">Assign a technician to work on this repair</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Item Photo</label>
                        <div id="photoPreviewContainer" style="display: none; margin-bottom: 10px;">
                            <img id="currentPhoto" src="" alt="Current Photo" style="max-width: 150px; max-height: 120px; border-radius: 8px; border: 2px solid #e2e8f0; object-fit: cover;">
                            <div style="font-size: 0.75rem; color: #718096; margin-top: 3px;">Current photo</div>
                        </div>
                        <input type="file" name="item_photo" id="itemPhoto" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewPhoto(this)">
                        <div id="photoPreview" style="display: none; margin-top: 8px;">
                            <img id="photoPreviewImg" src="" alt="Preview" style="max-width: 150px; max-height: 120px; border-radius: 8px; border: 2px solid #90cdf4; object-fit: cover;">
                        </div>
                        <small style="color: #718096;">Accepted: JPG, PNG, GIF, WebP (Max 5MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Problem Description *</label>
                        <textarea name="problem_description" id="problemDescription" class="form-control" rows="3" required placeholder="Describe the issue..."></textarea>
                    </div>
                    
                    <div class="form-group" id="diagnosisGroup" style="display: none;">
                        <label>Diagnosis</label>
                        <textarea name="diagnosis" id="diagnosis" class="form-control" rows="2" placeholder="Technical diagnosis..."></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label>Estimated Cost (K)</label>
                            <input type="number" name="estimated_cost" id="estimatedCost" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group" id="finalCostGroup" style="display: none;">
                            <label>Final Cost (K)</label>
                            <input type="number" name="final_cost" id="finalCost" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-sync"></i> Update Status</h3>
                <button class="modal-close" onclick="closeStatusModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" id="statusRepairId">
                    <input type="hidden" name="status" id="newStatus">
                    
                    <p id="statusMessage"></p>
                    
                    <div class="form-group">
                        <label>Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Add any notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    
    <script src="assets/js/main.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tools"></i> New Repair Ticket';
            document.getElementById('formAction').value = 'add';
            document.getElementById('repairForm').reset();
            document.getElementById('diagnosisGroup').style.display = 'none';
            document.getElementById('finalCostGroup').style.display = 'none';
            document.getElementById('technicianGroup').style.display = 'block';
            document.getElementById('customerGroup').style.display = 'block';
            document.getElementById('photoPreviewContainer').style.display = 'none';
            document.getElementById('photoPreview').style.display = 'none';
            document.getElementById('itemPhoto').value = '';
            document.getElementById('repairModal').classList.add('active');
        }
        
        function editRepair(repair) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Repair';
            document.getElementById('formAction').value = 'update';
            document.getElementById('repairId').value = repair.id;
            document.getElementById('customerName').value = repair.customer_name;
            document.getElementById('customerPhone').value = repair.customer_phone || '';
            document.getElementById('customerEmail').value = repair.customer_email || '';
            document.getElementById('deviceType').value = repair.device_type;
            document.getElementById('deviceBrand').value = repair.device_brand || '';
            document.getElementById('deviceModel').value = repair.device_model || '';
            document.getElementById('serialNumber').value = repair.serial_number || '';
            document.getElementById('problemDescription').value = repair.problem_description;
            document.getElementById('diagnosis').value = repair.diagnosis || '';
            document.getElementById('estimatedCost').value = repair.estimated_cost || '';
            document.getElementById('finalCost').value = repair.final_cost || '';
            document.getElementById('priority').value = repair.priority;
            document.getElementById('technicianId').value = repair.technician_id || '';
            document.getElementById('customerId').value = repair.customer_id || '';
            
            document.getElementById('diagnosisGroup').style.display = 'block';
            document.getElementById('finalCostGroup').style.display = 'block';
            document.getElementById('technicianGroup').style.display = 'block';
            document.getElementById('customerGroup').style.display = 'block';
            
            // Show current photo if exists
            document.getElementById('itemPhoto').value = '';
            document.getElementById('photoPreview').style.display = 'none';
            if (repair.item_photo) {
                document.getElementById('currentPhoto').src = 'uploads/repairs/' + repair.item_photo;
                document.getElementById('photoPreviewContainer').style.display = 'block';
            } else {
                document.getElementById('photoPreviewContainer').style.display = 'none';
            }
            
            document.getElementById('repairModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('repairModal').classList.remove('active');
        }
        
        function updateStatus(id, status) {
            const messages = {
                'in_progress': 'Start working on this repair?',
                'completed': 'Mark this repair as completed?',
                'delivered': 'Mark this device as delivered to customer?'
            };
            
            document.getElementById('statusRepairId').value = id;
            document.getElementById('newStatus').value = status;
            document.getElementById('statusMessage').textContent = messages[status];
            document.getElementById('statusModal').classList.add('active');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }
        
        function deleteRepair(id, ticket) {
            if (confirm('Are you sure you want to delete repair ticket "' + ticket + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function searchRepairs() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.repair-card');
            cards.forEach(card => {
                const text = card.getAttribute('data-search');
                card.style.display = text.includes(input) ? '' : 'none';
            });
        }
        
        function filterByTechnician(technicianId) {
            const currentUrl = new URL(window.location.href);
            if (technicianId) {
                currentUrl.searchParams.set('technician', technicianId);
            } else {
                currentUrl.searchParams.delete('technician');
            }
            window.location.href = currentUrl.toString();
        }
        
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
        
        function viewPhoto(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('photoLightbox').style.display = 'flex';
        }
        
        function closeLightbox() {
            document.getElementById('photoLightbox').style.display = 'none';
        }
        
        // Close modals on overlay click
        document.getElementById('repairModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        document.getElementById('statusModal').addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });
    </script>
    
    <!-- Photo Lightbox -->
    <div id="photoLightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10000; justify-content: center; align-items: center; cursor: pointer;" onclick="closeLightbox()">
        <img id="lightboxImg" src="" alt="Item Photo" style="max-width: 90%; max-height: 90%; border-radius: 8px; box-shadow: 0 4px 30px rgba(0,0,0,0.5); object-fit: contain;">
        <button style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; font-size: 2rem; cursor: pointer; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center;" onclick="closeLightbox()">&times;</button>
    </div>
</body>
</html>
