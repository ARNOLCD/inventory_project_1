<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';
require_once 'config/receipts.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Create uploads directory for repair photos
$repair_upload_dir = 'uploads/repairs/';
if (!is_dir($repair_upload_dir)) {
    mkdir($repair_upload_dir, 0777, true);
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
            $stmt->bind_param("ssssissssssdsii", $ticket_number, $customer_name, $customer_phone, $customer_email, $customer_id, $device_type, $device_brand, $device_model, $serial_number, $item_photo, $problem_description, $estimated_cost, $priority, $user['id'], $technician_id);
            
            if ($stmt->execute()) {
                $message = "Repair ticket $ticket_number created successfully!";
                notifyRepairCustomer($conn, $conn->insert_id, 'booked');
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
        if ($new_status === 'in_transit_to_office') {
            $date_field = ", in_transit_to_office_date = NOW()";
        } elseif ($new_status === 'item_received') {
            $date_field = ", item_received_date = NOW()";
        } elseif ($new_status === 'in_progress') {
            $date_field = ", started_date = NOW()";
        } elseif ($new_status === 'completed') {
            $date_field = ", completed_date = NOW()";
        } elseif ($new_status === 'in_transit') {
            $date_field = ", in_transit_date = NOW()";
        } elseif ($new_status === 'delivered') {
            $date_field = ", delivered_date = NOW()";
        } elseif ($new_status === 'failed') {
            $date_field = ", failed_date = NOW()";
        }

        $sql = "UPDATE repairs SET status = ?, repair_notes = CONCAT(IFNULL(repair_notes, ''), '\n[" . date('Y-m-d H:i') . "] Status: $new_status - ', ?) $date_field WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_status, $notes, $id);
        
        if ($stmt->execute()) {
            $message = 'Repair status updated successfully!';
            
            // Send email notification to customer if customer email exists
            notifyRepairCustomer($conn, $id, $new_status, $notes);
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
        $parts_replaced = trim($_POST['parts_replaced'] ?? '');
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
            $stmt = $conn->prepare("UPDATE repairs SET customer_name=?, customer_phone=?, device_type=?, device_brand=?, device_model=?, problem_description=?, diagnosis=?, parts_replaced=?, estimated_cost=?, final_cost=?, priority=?, technician_id=?, customer_id=?" . $photo_sql . " WHERE id=?");
            $stmt->bind_param("ssssssssddsiii" . $photo_param . "i", $customer_name, $customer_phone, $device_type, $device_brand, $device_model, $problem_description, $diagnosis, $parts_replaced, $estimated_cost, $final_cost, $priority, $technician_id, $customer_id, $item_photo, $id);
        } else {
            $stmt = $conn->prepare("UPDATE repairs SET customer_name=?, customer_phone=?, device_type=?, device_brand=?, device_model=?, problem_description=?, diagnosis=?, parts_replaced=?, estimated_cost=?, final_cost=?, priority=?, technician_id=?, customer_id=? WHERE id=?");
            $stmt->bind_param("ssssssssddsiii", $customer_name, $customer_phone, $device_type, $device_brand, $device_model, $problem_description, $diagnosis, $parts_replaced, $estimated_cost, $final_cost, $priority, $technician_id, $customer_id, $id);
        }
        
        if ($stmt->execute()) {
            $message = 'Repair updated successfully!';
        } else {
            $error = 'Error updating repair.';
        }
    } elseif ($action === 'notify_transit') {
        // Staff explicitly emails the customer that their repaired item is on its way
        $id = intval($_POST['id']);
        if (notifyRepairCustomer($conn, $id, 'in_transit', 'Your repaired item is on its way to you.')) {
            $message = 'The customer has been emailed that their item is on its way to them.';
        } else {
            $error = 'No valid customer email is on file for this repair.';
        }
    } elseif ($action === 'record_payment') {
        // Counter payment taken by staff: mark paid once, then issue + email the receipt
        $id = intval($_POST['id']);
        $payment_method = $_POST['payment_method'] ?? '';
        if (!isset(PAYMENT_METHODS[$payment_method])) {
            $error = 'Please choose a payment method.';
        } else {
            $stmt = $conn->prepare("UPDATE repairs SET payment_method = ?, payment_status = 'paid', payment_date = NOW() WHERE id = ? AND payment_status <> 'paid' AND final_cost > 0");
            $stmt->bind_param("si", $payment_method, $id);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                $receipt_id = issueReceipt($conn, 'repair', $id);
                $message = 'Payment recorded and receipt issued. <a href="receipt.php?id=' . (int)$receipt_id . '&print=1" target="_blank"><strong>Print receipt</strong></a>';
            } else {
                $error = 'Payment could not be recorded: the repair is already paid or has no final cost set.';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        if (!isAdmin()) {
            $error = 'Only administrators can delete repair tickets.';
        } elseif ($conn->query("DELETE FROM repairs WHERE id = $id")) {
            $message = 'Repair ticket deleted successfully!';
        } else {
            $error = 'Error deleting repair ticket.';
        }
    }
}

// Get filter
$status_filter = isset(REPAIR_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$technician_filter = $_GET['technician'] ?? '';
$periods = ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'];
$period_filter = isset($periods[$_GET['period'] ?? '']) ? $_GET['period'] : '';

// Build query
$where = "WHERE 1=1";
if ($status_filter) {
    $where .= " AND r.status = '$status_filter'";
} else {
    // Pending customer requests are handled on repair_requests.php; declined/cancelled only show via their filter
    $where .= " AND r.status NOT IN ('pending_approval', 'rejected', 'cancelled')";
}
if ($period_filter === 'today') {
    $where .= " AND r.created_at >= CURDATE()";
} elseif ($period_filter === 'week') {
    $where .= " AND YEARWEEK(r.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($period_filter === 'month') {
    $where .= " AND YEAR(r.created_at) = YEAR(CURDATE()) AND MONTH(r.created_at) = MONTH(r.created_at)";
} elseif ($period_filter === 'year') {
    $where .= " AND YEAR(r.created_at) = YEAR(CURDATE())";
}
if ($technician_filter === 'none') {
    $where .= " AND (r.technician_id IS NULL OR r.technician_id = 0)";
} elseif ($technician_filter) {
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
        FIELD(r.status, 'booked', 'in_transit_to_office', 'item_received', 'in_progress', 'completed', 'in_transit', 'delivered', 'failed', 'pending_approval', 'rejected', 'cancelled'),
        CASE r.priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'low' THEN 4
        END,
        r.created_at DESC
");

// Get counts
$status_counts = repairStatusCounts($conn);

// Status counts per time period (day / week / month / year) for the tracking summary
$period_counts = [];
$period_totals = ['today' => 0, 'week' => 0, 'month' => 0, 'year' => 0, 'all_time' => 0];
foreach ($conn->query("
    SELECT status,
           SUM(created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY) AS today,
           SUM(YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)) AS week,
           SUM(YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())) AS month,
           SUM(YEAR(created_at) = YEAR(CURDATE())) AS year,
           COUNT(*) AS all_time
    FROM repairs
    GROUP BY status
") as $row) {
    $period_counts[$row['status']] = $row;
    foreach ($period_totals as $p => &$t) {
        $t += (int)$row[$p];
    }
}
unset($t);

// Repairs assigned to each technician, broken down per status
$tech_breakdown = [];
foreach ($conn->query("
    SELECT r.technician_id, COALESCE(u.full_name, 'Unassigned') AS tech, r.status, COUNT(*) AS c
    FROM repairs r LEFT JOIN users u ON r.technician_id = u.id
    GROUP BY r.technician_id, r.status
") as $row) {
    $name = $row['tech'];
    $tech_breakdown[$name]['id'] = $row['technician_id'];
    $tech_breakdown[$name][$row['status']] = (int)$row['c'];
    $tech_breakdown[$name]['total'] = ($tech_breakdown[$name]['total'] ?? 0) + (int)$row['c'];
}
uasort($tech_breakdown, fn($a, $b) => $b['total'] <=> $a['total']);

// Items repaired (completed) tracked per day / week / month / year
$repaired_totals = $conn->query("
    SELECT
        SUM(completed_date >= CURDATE() AND completed_date < CURDATE() + INTERVAL 1 DAY) AS today,
        SUM(YEARWEEK(completed_date, 1) = YEARWEEK(CURDATE(), 1)) AS week,
        SUM(YEAR(completed_date) = YEAR(CURDATE()) AND MONTH(completed_date) = MONTH(completed_date)) AS month,
        SUM(YEAR(completed_date) = YEAR(CURDATE())) AS year,
        COUNT(*) AS all_time
    FROM repairs WHERE completed_date IS NOT NULL
")->fetch_assoc();

// Per day within this week
$repaired_days = $conn->query("
    SELECT DATE(completed_date) AS d, COUNT(*) AS c
    FROM repairs
    WHERE completed_date IS NOT NULL
      AND YEARWEEK(completed_date, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY d ORDER BY d");

// Per week within this month
$repaired_weeks = $conn->query("
    SELECT YEARWEEK(completed_date, 1) AS wk, MIN(DATE(completed_date)) AS start_date, COUNT(*) AS c
    FROM repairs
    WHERE completed_date IS NOT NULL
      AND YEAR(completed_date) = YEAR(CURDATE()) AND MONTH(completed_date) = MONTH(CURDATE())
    GROUP BY wk ORDER BY wk");

// Per month within this year
$repaired_months = $conn->query("
    SELECT MONTH(completed_date) AS m, COUNT(*) AS c
    FROM repairs
    WHERE completed_date IS NOT NULL AND YEAR(completed_date) = YEAR(CURDATE())
    GROUP BY m ORDER BY m");

// Per year, all time
$repaired_years = $conn->query("
    SELECT YEAR(completed_date) AS y, COUNT(*) AS c
    FROM repairs WHERE completed_date IS NOT NULL
    GROUP BY y ORDER BY y DESC");

// Get technicians for dropdown
$technicians = $conn->query("SELECT id, full_name FROM users WHERE role IN ('" . implode("','", STAFF_ROLES) . "') ORDER BY full_name");

// Get customers for dropdown
$customers = $conn->query("SELECT id, full_name, email FROM users WHERE role = 'customer' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Tracking - <?php echo e(companyName()); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .status-booked { background: #3182ce; color: white; }
        .status-in_transit_to_office { background: #2b6cb0; color: white; }
        .status-item_received { background: #4299e1; color: white; }
        .status-in_progress { background: #dd6b20; color: white; }
        .status-completed { background: #38a169; color: white; }
        .status-in_transit { background: #9f7aea; color: white; }
        .status-delivered { background: #805ad5; color: white; }
        .status-failed { background: #b91c1c; color: white; }
        .status-cancelled { background: #e53e3e; color: white; }
        .repair-categories { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 15px; }
        .repair-category {
            background: #fff;
            border-radius: 12px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: #2d3748;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .repair-category:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .repair-category.active { background: var(--cat-color); }
        .repair-category .cat-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: var(--cat-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .repair-category.active .cat-icon { background: #fff; color: var(--cat-color); }
        .repair-category .cat-info { display: flex; flex-direction: column; }
        .repair-category strong { font-size: 1.6rem; line-height: 1.2; }
        .repair-category span { font-size: 0.85rem; color: #718096; }
        .repair-category.active strong { color: #fff; }
        .repair-category.active span { color: rgba(255,255,255,0.9); }
        .status-pending_approval { background: #718096; color: white; }
        .status-rejected { background: #c53030; color: white; }
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
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .status-btn:hover { opacity: 0.8; transform: translateY(-1px); }
        .status-btn i { font-size: 1rem; margin-right: 4px; }
        .btn-receive { background: #4299e1; color: white; }
        .btn-start { background: #dd6b20; color: white; }
        .btn-complete { background: #38a169; color: white; }
        .btn-transit { background: #9f7aea; color: white; }
        .btn-deliver { background: #805ad5; color: white; }

        /* Group headers that split the list by repair status */
        .status-group-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 22px 0 12px;
            font-size: 1.05rem;
            font-weight: 600;
        }
        .status-group-header .status-group-count {
            background: #edf2f7;
            color: #4a5568;
            border-radius: 12px;
            padding: 2px 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Per-repair workflow tracker: Received -> Working -> Done -> Transit -> Delivered */
        .repair-steps { display: flex; align-items: flex-start; margin: 16px 0 6px; }
        .repair-step { display: flex; flex-direction: column; align-items: center; width: 84px; text-align: center; }
        .repair-step i {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: #edf2f7;
            color: #cbd5e0;
        }
        .repair-step span { font-size: 0.75rem; color: #718096; margin-top: 5px; line-height: 1.1; font-weight: 500; }
        .repair-step.done i { background: #c6f6d5; color: #276749; }
        .repair-step.done span { color: #2f855a; }
        .repair-step.current i { background: var(--step-color); color: #fff; box-shadow: 0 0 0 3px #fff, 0 0 0 6px var(--step-color); }
        .repair-step.current span { color: var(--step-color); font-weight: 700; }
        .step-line { flex: 1; height: 3px; background: #edf2f7; margin-top: 22px; border-radius: 2px; min-width: 12px; }
        .step-line.done { background: #c6f6d5; }
        .repair-badge { padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }

        /* Tracking summary tables (status x period, technician x status) */
        .summary-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .summary-table th { background: #f7fafc; color: #4a5568; padding: 8px 10px; text-align: center; border-bottom: 2px solid #e2e8f0; white-space: nowrap; font-size: 0.78rem; }
        .summary-table th:first-child, .summary-table td:first-child { text-align: left; }
        .summary-table td { padding: 7px 10px; text-align: center; border-bottom: 1px solid #edf2f7; }
        .summary-table tbody tr:hover { background: #f7fafc; }
        .summary-table .summary-total td { border-top: 2px solid #cbd5e0; background: #f7fafc; }
        .summary-table a { text-decoration: none; font-weight: 600; color: #1a365d; }
        .summary-table a:hover { text-decoration: underline; }
        .summary-table .zero { color: #cbd5e0; }
        .rep-counter { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 18px; text-align: center; min-width: 90px; }
        .rep-counter .rep-num { font-size: 1.4rem; font-weight: 700; color: #38a169; }
        .rep-counter .rep-lbl { font-size: 0.75rem; color: #718096; }
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
                
                <!-- Repair categories (click to filter) -->
                <div class="repair-categories mb-4">
                    <?php foreach (REPAIR_STATUSES as $status_key => $status_label): ?>
                        <?php [$status_icon, $status_color] = REPAIR_STATUS_STYLES[$status_key]; ?>
                        <a href="<?php echo $status_key === 'pending_approval' ? 'repair_requests.php' : 'repairs.php?status=' . $status_key; ?>"
                           class="repair-category <?php echo $status_filter === $status_key ? 'active' : ''; ?>" style="--cat-color: <?php echo $status_color; ?>;">
                            <div class="cat-icon"><i class="fas <?php echo $status_icon; ?>"></i></div>
                            <div class="cat-info">
                                <strong><?php echo (int)($status_counts[$status_key] ?? 0); ?></strong>
                                <span><?php echo e($status_label); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Tracking summary: repairs per status for each time period -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 style="margin: 0 0 12px; font-size: 1.05rem;"><i class="fas fa-chart-bar"></i> Tracking Summary</h3>
                        <div style="overflow-x: auto;">
                            <table class="summary-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Today</th>
                                        <th>This Week</th>
                                        <th>This Month</th>
                                        <th>This Year</th>
                                        <th>All Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (REPAIR_STATUSES as $s_key => $s_label):
                                        [$s_icon, $s_color] = REPAIR_STATUS_STYLES[$s_key]; ?>
                                        <tr>
                                            <td><i class="fas <?php echo $s_icon; ?>" style="color: <?php echo $s_color; ?>;"></i> <?php echo e($s_label); ?></td>
                                            <?php foreach (['today', 'week', 'month', 'year', 'all_time'] as $p):
                                                $n = (int)($period_counts[$s_key][$p] ?? 0); ?>
                                                <td>
                                                    <?php if ($n > 0 && $p !== 'all_time'): ?>
                                                        <a href="repairs.php?status=<?php echo $s_key; ?>&period=<?php echo $p; ?>"><?php echo $n; ?></a>
                                                    <?php elseif ($n > 0): ?>
                                                        <a href="repairs.php?status=<?php echo $s_key; ?>"><?php echo $n; ?></a>
                                                    <?php else: ?>
                                                        <span class="zero">0</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="summary-total">
                                        <td><strong>Total</strong></td>
                                        <?php foreach (['today', 'week', 'month', 'year', 'all_time'] as $p): ?>
                                            <td><strong><?php echo $period_totals[$p]; ?></strong></td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h4 style="margin: 20px 0 10px; font-size: 0.95rem;"><i class="fas fa-user-cog"></i> Repairs by Technician</h4>
                        <div style="overflow-x: auto;">
                            <table class="summary-table">
                                <thead>
                                    <tr>
                                        <th>Technician</th>
                                        <?php foreach (['booked', 'in_transit_to_office', 'item_received', 'in_progress', 'completed', 'in_transit', 'delivered', 'failed'] as $s_key): ?>
                                            <th title="<?php echo e(REPAIR_STATUSES[$s_key]); ?>"><i class="fas <?php echo REPAIR_STATUS_STYLES[$s_key][0]; ?>" style="color: <?php echo REPAIR_STATUS_STYLES[$s_key][1]; ?>;"></i></th>
                                        <?php endforeach; ?>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($tech_breakdown): ?>
                                        <?php foreach ($tech_breakdown as $name => $data): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($data['id']): ?>
                                                        <a href="repairs.php?technician=<?php echo (int)$data['id']; ?>"><?php echo e($name); ?></a>
                                                    <?php else: ?>
                                                        <a href="repairs.php?technician=none" style="color: #718096; font-style: italic;"><?php echo e($name); ?></a>
                                                    <?php endif; ?>
                                                </td>
                                                <?php foreach (['booked', 'in_transit_to_office', 'item_received', 'in_progress', 'completed', 'in_transit', 'delivered', 'failed'] as $s_key):
                                                    $n = (int)($data[$s_key] ?? 0); ?>
                                                    <td><?php echo $n > 0 ? '<strong>' . $n . '</strong>' : '<span class="zero">0</span>'; ?></td>
                                                <?php endforeach; ?>
                                                <td><strong><?php echo (int)$data['total']; ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="10" style="text-align: center; color: #718096;">No repairs assigned yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <h4 style="margin: 20px 0 10px; font-size: 0.95rem;"><i class="fas fa-check-circle"></i> Items Repaired (by date completed)</h4>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 15px;">
                            <div class="rep-counter"><div class="rep-num"><?php echo (int)$repaired_totals['today']; ?></div><div class="rep-lbl">Today</div></div>
                            <div class="rep-counter"><div class="rep-num"><?php echo (int)$repaired_totals['week']; ?></div><div class="rep-lbl">This Week</div></div>
                            <div class="rep-counter"><div class="rep-num"><?php echo (int)$repaired_totals['month']; ?></div><div class="rep-lbl">This Month</div></div>
                            <div class="rep-counter"><div class="rep-num"><?php echo (int)$repaired_totals['year']; ?></div><div class="rep-lbl">This Year</div></div>
                            <div class="rep-counter"><div class="rep-num"><?php echo (int)$repaired_totals['all_time']; ?></div><div class="rep-lbl">All Time</div></div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px;">
                            <table class="summary-table">
                                <thead><tr><th colspan="2" style="text-align: left;">This Week - by day</th></tr></thead>
                                <tbody>
                                    <?php if ($repaired_days->num_rows): ?>
                                        <?php while ($r = $repaired_days->fetch_assoc()): ?>
                                            <tr><td><?php echo date('D j M', strtotime($r['d'])); ?></td><td><strong><?php echo (int)$r['c']; ?></strong></td></tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" style="text-align: left; color: #a0aec0;">None repaired this week</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <table class="summary-table">
                                <thead><tr><th colspan="2" style="text-align: left;">This Month - by week</th></tr></thead>
                                <tbody>
                                    <?php if ($repaired_weeks->num_rows): ?>
                                        <?php while ($r = $repaired_weeks->fetch_assoc()): ?>
                                            <tr><td>Week of <?php echo date('j M', strtotime($r['start_date'])); ?></td><td><strong><?php echo (int)$r['c']; ?></strong></td></tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" style="text-align: left; color: #a0aec0;">None repaired this month</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <table class="summary-table">
                                <thead><tr><th colspan="2" style="text-align: left;">This Year - by month</th></tr></thead>
                                <tbody>
                                    <?php if ($repaired_months->num_rows): ?>
                                        <?php while ($r = $repaired_months->fetch_assoc()): ?>
                                            <tr><td><?php echo date('F', mktime(0, 0, 0, (int)$r['m'], 1)); ?></td><td><strong><?php echo (int)$r['c']; ?></strong></td></tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" style="text-align: left; color: #a0aec0;">None repaired this year</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <table class="summary-table">
                                <thead><tr><th colspan="2" style="text-align: left;">All Time - by year</th></tr></thead>
                                <tbody>
                                    <?php if ($repaired_years->num_rows): ?>
                                        <?php while ($r = $repaired_years->fetch_assoc()): ?>
                                            <tr><td><?php echo (int)$r['y']; ?></td><td><strong><?php echo (int)$r['c']; ?></strong></td></tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" style="text-align: left; color: #a0aec0;">No repairs completed yet</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                        <a href="repairs.php" class="btn <?php echo !$status_filter && !$technician_filter && !$period_filter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">All active</a>
                        <?php foreach ($periods as $p_key => $p_label): ?>
                            <a href="repairs.php?period=<?php echo $p_key; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $technician_filter ? '&technician=' . urlencode($technician_filter) : ''; ?>"
                               class="btn <?php echo $period_filter === $p_key ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                                <i class="fas fa-calendar-alt"></i> <?php echo $p_label; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($status_filter || $period_filter): ?>
                            <span class="badge" style="background: <?php echo $status_filter ? REPAIR_STATUS_STYLES[$status_filter][1] : '#1a365d'; ?>; color: #fff; padding: 6px 12px;">
                                Showing: <?php echo e(implode(' / ', array_filter([$status_filter ? repairStatusLabel($status_filter) : '', $periods[$period_filter] ?? '']))); ?>
                            </span>
                        <?php endif; ?>

                        <div style="display: flex; align-items: center; gap: 8px; margin-left: 10px;">
                            <label style="font-size: 0.9rem; color: #4a5568;"><i class="fas fa-user-cog"></i> Technician:</label>
                            <select class="form-control" style="width: 180px; padding: 5px 10px; font-size: 0.85rem;" onchange="filterByTechnician(this.value)">
                                <option value="">All Technicians</option>
                                <option value="none" <?php echo $technician_filter === 'none' ? 'selected' : ''; ?>>Unassigned</option>
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
                
                <!-- Repairs List (grouped by status: booked, received, in progress, completed, in transit, delivered) -->
                <div id="repairsList">
                    <?php if ($repairs->num_rows > 0): ?>
                        <?php $last_status = null; ?>
                        <?php while ($repair = $repairs->fetch_assoc()): ?>
                            <?php if ($repair['status'] !== $last_status):
                                $last_status = $repair['status'];
                                [$g_icon, $g_color] = REPAIR_STATUS_STYLES[$repair['status']]; ?>
                                <div class="status-group-header" style="color: <?php echo $g_color; ?>;">
                                    <i class="fas <?php echo $g_icon; ?>"></i>
                                    <?php echo e(repairStatusLabel($repair['status'])); ?>
                                    <span class="status-group-count"><?php echo (int)($status_counts[$repair['status']] ?? 0); ?></span>
                                </div>
                            <?php endif; ?>
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
                                        <?php [$b_icon, $b_color] = REPAIR_STATUS_STYLES[$repair['status']]; ?>
                                        <span class="badge status-<?php echo $repair['status']; ?> repair-badge">
                                            <i class="fas <?php echo $b_icon; ?>"></i> <?php echo e(repairStatusLabel($repair['status'])); ?>
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

                                <?php if (!empty($repair['parts_replaced'])): ?>
                                    <div style="font-size: 0.85rem; color: #4a5568; margin-bottom: 10px; background: #fffaf0; border: 1px solid #fbd38d; border-radius: 6px; padding: 8px 10px;">
                                        <strong><i class="fas fa-cogs"></i> Parts Replaced:</strong> <?php echo nl2br(htmlspecialchars($repair['parts_replaced'])); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Workflow tracker: symbols show the stage clearly without clicking anything -->
                                <?php
                                $status_order = ['booked', 'in_transit_to_office', 'item_received', 'in_progress', 'completed', 'in_transit', 'delivered'];
                                $current_pos = array_search($repair['status'], $status_order, true);
                                $current_pos = $current_pos === false ? -1 : $current_pos;
                                $workflow_steps = ['in_transit_to_office' => 'To Office', 'item_received' => 'Received', 'in_progress' => 'Working', 'completed' => 'Done', 'in_transit' => 'To Client', 'delivered' => 'Delivered'];
                                ?>
                                <div class="repair-steps">
                                    <?php foreach ($workflow_steps as $step => $step_label):
                                        [$s_icon, $s_color] = REPAIR_STATUS_STYLES[$step];
                                        $step_pos = array_search($step, $status_order, true);
                                        $state = $step_pos < $current_pos ? 'done' : ($step_pos === $current_pos ? 'current' : '');
                                    ?>
                                        <div class="repair-step <?php echo $state; ?>" style="--step-color: <?php echo $s_color; ?>;" title="<?php echo e(repairStatusLabel($step)); ?>">
                                            <i class="fas <?php echo $s_icon; ?>"></i>
                                            <span><?php echo $step_label; ?></span>
                                        </div>
                                        <?php if ($step !== 'delivered'): ?>
                                            <div class="step-line <?php echo $step_pos < $current_pos ? 'done' : ''; ?>"></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div style="font-size: 0.85rem; color: #718096;">
                                        <?php if ($repair['estimated_cost']): ?>
                                            Est: K<?php echo number_format($repair['estimated_cost'], 2); ?>
                                        <?php endif; ?>
                                        <?php if ($repair['final_cost']): ?>
                                            | Final: <strong>K<?php echo number_format($repair['final_cost'], 2); ?></strong>
                                            <?php if ($repair['payment_status'] === 'paid'): ?>
                                                <span class="badge" style="background: #38a169; color: #fff; margin-left: 6px;">Paid</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #dd6b20; color: #fff; margin-left: 6px;">Unpaid<?php echo $repair['payment_method'] === 'cash' ? ' - pay on collection' : ''; ?></span>
                                            <?php endif; ?>
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
                                        <?php if (in_array($repair['status'], ['booked', 'in_transit_to_office'], true)): ?>
                                            <button class="status-btn btn-receive" title="Mark that the device has arrived at the workshop" onclick="updateStatus(<?php echo $repair['id']; ?>, 'item_received')">
                                                <i class="fas fa-box"></i> Item Received
                                            </button>
                                        <?php elseif ($repair['status'] === 'item_received'): ?>
                                            <button class="status-btn btn-start" title="Start working on this repair" onclick="updateStatus(<?php echo $repair['id']; ?>, 'in_progress')">
                                                <i class="fas fa-play"></i> Start Work
                                            </button>
                                        <?php elseif ($repair['status'] === 'in_progress'): ?>
                                            <button class="status-btn btn-complete" title="Repair work is finished" onclick="updateStatus(<?php echo $repair['id']; ?>, 'completed')">
                                                <i class="fas fa-check"></i> Mark Complete
                                            </button>
                                            <button class="status-btn" style="background: #b91c1c; color: #fff;" title="The device could not be fixed" onclick="updateStatus(<?php echo $repair['id']; ?>, 'failed')">
                                                <i class="fas fa-exclamation-triangle"></i> Repair Failed
                                            </button>
                                        <?php elseif ($repair['status'] === 'failed'): ?>
                                            <button class="status-btn btn-transit" title="Return the device to the client" onclick="updateStatus(<?php echo $repair['id']; ?>, 'in_transit')">
                                                <i class="fas fa-shipping-fast"></i> Return to Client
                                            </button>
                                        <?php elseif ($repair['status'] === 'completed'): ?>
                                            <button class="status-btn btn-transit" title="Mark as in transit to the client - the customer is emailed automatically" onclick="updateStatus(<?php echo $repair['id']; ?>, 'in_transit')">
                                                <i class="fas fa-shipping-fast"></i> Send to Client
                                            </button>
                                        <?php elseif ($repair['status'] === 'in_transit'): ?>
                                            <button class="status-btn btn-deliver" title="Customer has received the device" onclick="updateStatus(<?php echo $repair['id']; ?>, 'delivered')">
                                                <i class="fas fa-hand-holding"></i> Mark Delivered
                                            </button>
                                        <?php endif; ?>

                                        <?php if (in_array($repair['status'], ['completed', 'in_transit'], true)): ?>
                                            <button class="status-btn" style="background: #2b6cb0; color: #fff;" title="Email the customer that their item is on its way to them" onclick="notifyTransit(<?php echo $repair['id']; ?>, '<?php echo htmlspecialchars($repair['ticket_number'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-envelope"></i> Email: On Its Way
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($repair['final_cost'] > 0 && $repair['payment_status'] !== 'paid' && !in_array($repair['status'], ['pending_approval', 'rejected', 'cancelled'], true)): ?>
                                            <button class="status-btn" style="background: #38a169; color: #fff;" title="Mark as paid - a receipt is generated and emailed automatically" onclick="recordPayment(<?php echo $repair['id']; ?>, '<?php echo e($repair['ticket_number']); ?>', '<?php echo number_format($repair['final_cost'], 2); ?>')">
                                                <i class="fas fa-check-circle"></i> Mark Paid
                                            </button>
                                        <?php elseif ((!$repair['final_cost'] || $repair['final_cost'] <= 0) && $repair['payment_status'] !== 'paid' && !in_array($repair['status'], ['pending_approval', 'rejected', 'cancelled'], true)): ?>
                                            <button class="status-btn" style="background: #a0aec0; color: #fff;" title="Set a final cost first (Edit), then the Mark Paid button appears" onclick="alert('Set the final cost for this repair first - click Edit and enter a Final Cost. Then use Mark Paid to issue the receipt.')">
                                                <i class="fas fa-cash-register"></i> Paid
                                            </button>
                                        <?php elseif ($repair['payment_status'] === 'paid'): ?>
                                            <a class="action-btn" href="receipt.php?source=repair&amp;id=<?php echo $repair['id']; ?>" target="_blank" title="View / print receipt" style="background: #38a169; color: #fff;">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        <?php endif; ?>

                                        <button class="action-btn" onclick="viewSolutions(<?php echo $repair['id']; ?>)" title="View Solutions" style="background: #667eea;">
                                            <i class="fas fa-lightbulb"></i>
                                        </button>
                                        <button class="action-btn edit" onclick='editRepair(<?php echo htmlspecialchars(json_encode($repair), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (isAdmin()): ?>
                                        <button class="action-btn delete" onclick="deleteRepair(<?php echo $repair['id']; ?>, '<?php echo htmlspecialchars($repair['ticket_number'], ENT_QUOTES); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
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

                    <div class="form-group" id="partsReplacedGroup" style="display: none;">
                        <label><i class="fas fa-cogs"></i> Spare Parts Replaced</label>
                        <textarea name="parts_replaced" id="partsReplaced" class="form-control" rows="2" placeholder="e.g., Screen assembly, Battery, HDD 1TB (leave blank if none)"></textarea>
                        <small style="color: #718096;">Shown on the repair card so the team can see which parts were used.</small>
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
    
    <!-- Record Payment Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Mark as Paid</h3>
                <button class="modal-close" onclick="document.getElementById('paymentModal').classList.remove('active')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="id" id="paymentRepairId">
                    <p id="paymentMessage"></p>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <?php foreach (PAYMENT_METHODS as $method => $label): ?>
                                <option value="<?php echo $method; ?>"><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p style="font-size: 0.85rem; color: #718096;">A receipt is generated automatically and emailed to the customer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #38a169;"><i class="fas fa-check-circle"></i> Mark Paid</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <!-- Transit notification form (emails the customer that the item is on its way) -->
    <form id="notifyTransitForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="notify_transit">
        <input type="hidden" name="id" id="notifyTransitId">
    </form>
    
    <script src="assets/js/main.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tools"></i> New Repair Ticket';
            document.getElementById('formAction').value = 'add';
            document.getElementById('repairForm').reset();
            document.getElementById('diagnosisGroup').style.display = 'none';
            document.getElementById('partsReplacedGroup').style.display = 'none';
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
            document.getElementById('partsReplaced').value = repair.parts_replaced || '';
            document.getElementById('estimatedCost').value = repair.estimated_cost || '';
            document.getElementById('finalCost').value = repair.final_cost || '';
            document.getElementById('priority').value = repair.priority;
            document.getElementById('technicianId').value = repair.technician_id || '';
            document.getElementById('customerId').value = repair.customer_id || '';
            
            document.getElementById('diagnosisGroup').style.display = 'block';
            document.getElementById('partsReplacedGroup').style.display = 'block';
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
                'item_received': 'Mark this item as received at Sims-Tech?',
                'in_progress': 'Start working on this repair?',
                'completed': 'Mark this repair as completed?',
                'in_transit': 'Mark this item as in transit to the client? The customer will be emailed automatically.',
                'delivered': 'Mark this device as delivered to customer?',
                'failed': 'Mark this repair as FAILED - the device could not be fixed? The customer will be emailed.'
            };

            document.getElementById('statusRepairId').value = id;
            document.getElementById('newStatus').value = status;
            document.getElementById('statusMessage').textContent = messages[status];
            document.getElementById('statusModal').classList.add('active');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }
        
        function recordPayment(id, ticket, amount) {
            document.getElementById('paymentRepairId').value = id;
            document.getElementById('paymentMessage').textContent = 'Mark payment of K' + amount + ' as received for ticket ' + ticket + '? A receipt will be generated and emailed automatically.';
            document.getElementById('paymentModal').classList.add('active');
        }

        function deleteRepair(id, ticket) {
            if (confirm('Are you sure you want to delete repair ticket "' + ticket + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function notifyTransit(id, ticket) {
            if (confirm('Email the customer that the item for ticket "' + ticket + '" is on its way to them?')) {
                document.getElementById('notifyTransitId').value = id;
                document.getElementById('notifyTransitForm').submit();
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
        
        function viewSolutions(repairId) {
            window.location.href = 'repair_solutions.php?repair_id=' + repairId;
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
