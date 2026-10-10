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

// Customer marks their booked item as being on its way to the office
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_to_office') {
    $rid = (int)($_POST['repair_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE repairs SET status = 'in_transit_to_office', in_transit_to_office_date = NOW(),
                            repair_notes = CONCAT(IFNULL(repair_notes, ''), '\n[" . date('Y-m-d H:i') . "] Customer marked the item as in transit to the office')
                            WHERE id = ? AND customer_id = ? AND status = 'booked'");
    $stmt->bind_param("ii", $rid, $user['id']);
    $stmt->execute();
    if ($stmt->affected_rows === 1) {
        notifyStaffItemInTransitToOffice($conn, $rid);
    }
    header('Location: customer_dashboard.php');
    exit();
}

// Get customer's repairs
$repairs = $conn->query("
    SELECT r.*, 
           u1.full_name as technician_name,
           d.id as receipt_id
    FROM repairs r 
    LEFT JOIN users u1 ON r.technician_id = u1.id 
    LEFT JOIN documents d ON d.source_type = 'repair' AND d.source_id = r.id 
    WHERE r.customer_id = {$user['id']}
    ORDER BY r.created_at DESC
");

// Get counts
$my_counts = array_fill_keys(array_keys(REPAIR_STATUSES), 0);
$count_result = $conn->query("SELECT status, COUNT(*) AS c FROM repairs WHERE customer_id = " . (int)$user['id'] . " GROUP BY status");
while ($row = $count_result->fetch_assoc()) {
    $my_counts[$row['status']] = (int)$row['c'];
}
$pending_approval_count = $my_counts['pending_approval'];
$booked_count = $my_counts['booked'];
$item_received_count = $my_counts['item_received'];
$in_progress_count = $my_counts['in_progress'];
$completed_count = $my_counts['completed'];
$in_transit_count = $my_counts['in_transit'];
$delivered_count = $my_counts['delivered'];

// Get customer's purchase orders
$orders = $conn->query("
    SELECT s.*, 
           COUNT(si.id) as item_count,
           MAX(d.id) as receipt_id
    FROM sales s 
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN documents d ON d.source_type = 'sale' AND d.source_id = s.id
    WHERE s.user_id = {$user['id']}
    GROUP BY s.id
    ORDER BY s.sale_date DESC
");

// Customer's payment receipts (generated automatically for every payment)
$receipts = $conn->query("SELECT id, document_number, total_amount, payment_method, notes, created_at FROM documents
                          WHERE document_type = 'receipt' AND customer_id = " . (int)$user['id'] . " ORDER BY created_at DESC");

// Get shopping cart count from localStorage (simulated)
$cart_count = 0; // This would be dynamic in a real implementation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - <?php echo e(companyName()); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f7fafc; }
        .customer-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
        }
        .customer-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .customer-info h1 {
            color: #1a365d;
            margin-bottom: 5px;
        }
        .customer-info p {
            color: #718096;
            margin: 0;
        }
        .customer-actions {
            display: flex;
            gap: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        .stat-icon.blue { background: #3182ce; }
        .stat-icon.orange { background: #dd6b20; }
        .stat-icon.green { background: #38a169; }
        .stat-icon.purple { background: #805ad5; }
        .stat-info h3 {
            margin: 0;
            font-size: 1.8rem;
            color: #2d3748;
        }
        .stat-info p {
            margin: 0;
            color: #718096;
            font-size: 0.9rem;
        }
        .repairs-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .repair-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .repair-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
            margin-top: 5px;
        }
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-booked { background: #3182ce; color: white; }
        .status-failed { background: #b91c1c; color: white; }
        .status-in_transit_to_office { background: #2b6cb0; color: white; }
        .status-item_received { background: #4299e1; color: white; }
        .status-in_progress { background: #dd6b20; color: white; }
        .status-completed { background: #38a169; color: white; }
        .status-in_transit { background: #9f7aea; color: white; }
        .status-delivered { background: #805ad5; color: white; }
        .status-cancelled { background: #e53e3e; color: white; }
        .status-pending_approval { background: #718096; color: white; }
        .status-rejected { background: #c53030; color: white; }
        .repair-details {
            color: #4a5568;
            font-size: 0.9rem;
        }
        .repair-details p {
            margin: 5px 0;
        }
        .progress-bar {
            background: #e2e8f0;
            border-radius: 10px;
            height: 8px;
            margin: 15px 0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        .progress-fill.pending_approval { width: 5%; background: #718096; }
        .progress-fill.rejected { width: 100%; background: #c53030; }
        .progress-fill.booked { width: 20%; background: #3182ce; }
        .progress-fill.in_transit_to_office { width: 30%; background: #2b6cb0; }
        .progress-fill.item_received { width: 40%; background: #4299e1; }
        .progress-fill.in_progress { width: 60%; background: #dd6b20; }
        .progress-fill.completed { width: 80%; background: #38a169; }
        .progress-fill.in_transit { width: 90%; background: #9f7aea; }
        .progress-fill.delivered { width: 100%; background: #805ad5; }
        .progress-fill.failed { width: 100%; background: #b91c1c; }
        .no-repairs {
            text-align: center;
            padding: 40px;
            color: #718096;
        }
        .no-repairs i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class="customer-container">
        <div class="customer-header">
            <div class="customer-info">
                <img src="<?php echo e(companyLogo()); ?>" alt="<?php echo e(companyName()); ?> Logo" onerror="this.style.display='none'" style="max-height: 40px; margin-bottom: 10px;">
                <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p><?php echo e(implode('  |  ', array_filter([$user['email'] ?? '', $user['phone'] ?? '']))); ?></p>
            </div>
            <div class="customer-actions">
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> View Our Products
                </a>
                <a href="index.php#services" class="btn btn-primary">
                    <i class="fas fa-cogs"></i> Our Services
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Back to Home Page
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-info">
                    <h3 id="cartCount"><?php echo $cart_count; ?></h3>
                    <p>Cart Items</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $orders->num_rows; ?></h3>
                    <p>My Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $pending_approval_count + $booked_count + $item_received_count + $in_progress_count; ?></h3>
                    <p>Active Repairs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #9f7aea;">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $in_transit_count; ?></h3>
                    <p>In Transit</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #805ad5;">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $delivered_count; ?></h3>
                    <p>Delivered</p>
                </div>
            </div>
        </div>

        <!-- Repairs Section -->
        <div class="repairs-section">
            <div class="section-header">
                <h2><i class="fas fa-tools"></i> My Repairs</h2>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="color: #718096; font-size: 0.9rem;">
                        Total: <?php echo $repairs->num_rows; ?> repairs
                    </span>
                    <a href="customer_book_repair.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Book New Repair
                    </a>
                </div>
            </div>

            <?php if ($repairs->num_rows > 0): ?>
                <?php while ($repair = $repairs->fetch_assoc()): ?>
                    <?php
                    $status_labels = ['pending_approval' => 'Awaiting approval', 'rejected' => 'Declined', 'failed' => 'Repair unsuccessful', 'in_transit_to_office' => 'On its way to our office', 'in_transit' => 'On its way to you'];
                    $is_request_open = !in_array($repair['status'], ['pending_approval', 'rejected'], true);
                    ?>
                    <div class="repair-card">
                        <div class="repair-header">
                            <div>
                                <div class="repair-ticket"><?php echo htmlspecialchars($repair['ticket_number']); ?></div>
                                <div class="repair-device">
                                    <i class="fas fa-<?php echo $repair['device_type'] === 'laptop' ? 'laptop' : ($repair['device_type'] === 'phone' ? 'mobile-alt' : 'desktop'); ?>"></i>
                                    <?php echo htmlspecialchars($repair['device_type']); ?>
                                    <?php if ($repair['device_brand']): ?> - <?php echo htmlspecialchars($repair['device_brand']); ?><?php endif; ?>
                                    <?php if ($repair['device_model']): ?> <?php echo htmlspecialchars($repair['device_model']); ?><?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $repair['status']; ?>">
                                <?php echo $status_labels[$repair['status']] ?? ucfirst(str_replace('_', ' ', $repair['status'])); ?>
                            </span>
                        </div>

                        <?php if ($repair['status'] === 'pending_approval'): ?>
                            <div style="background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin: 10px 0; color: #4a5568; font-size: 0.9rem;">
                                <i class="fas fa-hourglass-half"></i> Your request is awaiting review by our team. You will receive an email once it is accepted or declined.
                            </div>
                        <?php elseif ($repair['status'] === 'rejected'): ?>
                            <div style="background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 12px; margin: 10px 0; color: #c53030; font-size: 0.9rem;">
                                <i class="fas fa-ban"></i> <strong>Request declined.</strong>
                                <?php if (!empty($repair['rejection_reason'])): ?>
                                    Reason: <?php echo nl2br(htmlspecialchars($repair['rejection_reason'])); ?>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($repair['status'] === 'failed'): ?>
                            <div style="background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 12px; margin: 10px 0; color: #b91c1c; font-size: 0.9rem;">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Unfortunately we were unable to repair this device.</strong>
                                Please contact us to arrange collection or discuss options.
                            </div>
                        <?php endif; ?>

                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $repair['status']; ?>"></div>
                        </div>

                        <?php if ($repair['status'] === 'booked'): ?>
                            <div style="margin: 12px 0; background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 8px; padding: 12px;">
                                <p style="margin: 0 0 10px; font-size: 0.9rem; color: #2b6cb0;"><i class="fas fa-info-circle"></i> Repair approved. When you send or bring the device to our office, let us know it is on the way:</p>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirm the item is on its way to the office?');">
                                    <input type="hidden" name="action" value="send_to_office">
                                    <input type="hidden" name="repair_id" value="<?php echo (int)$repair['id']; ?>">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-truck"></i> My Item Is On Its Way to the Office</button>
                                </form>
                            </div>
                        <?php elseif ($repair['status'] === 'in_transit_to_office'): ?>
                            <div style="margin: 12px 0; background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 8px; padding: 12px; font-size: 0.9rem; color: #2b6cb0;">
                                <i class="fas fa-truck"></i> Your item is on its way to our office<?php echo $repair['in_transit_to_office_date'] ? ' (' . date('M d, Y H:i', strtotime($repair['in_transit_to_office_date'])) . ')' : ''; ?>. We will update you once it is received.
                            </div>
                        <?php endif; ?>

                        <div class="repair-details">
                            <p><strong>Problem:</strong> <?php echo htmlspecialchars($repair['problem_description']); ?></p>
                            <p><strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($repair['created_at'])); ?></p>
                            <?php if ($repair['technician_name']): ?>
                                <p><strong>Technician:</strong> <?php echo htmlspecialchars($repair['technician_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($repair['estimated_cost']): ?>
                                <p><strong>Estimated Cost:</strong> K<?php echo number_format($repair['estimated_cost'], 2); ?></p>
                            <?php endif; ?>
                            <?php if ($repair['final_cost']): ?>
                                <p><strong>Final Cost:</strong> K<?php echo number_format($repair['final_cost'], 2); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($repair['parts_replaced'])): ?>
                                <p><strong><i class="fas fa-cogs"></i> Parts Replaced:</strong> <?php echo nl2br(htmlspecialchars($repair['parts_replaced'])); ?></p>
                            <?php endif; ?>
                            <?php if ($repair['payment_status'] && $is_request_open): ?>
                                <p><strong>Payment Status:</strong>
                                    <span class="status-badge <?php echo $repair['payment_status'] === 'paid' ? 'status-completed' : 'status-booked'; ?>">
                                        <?php echo ucfirst($repair['payment_status']); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Delivery Status Timeline -->
                            <?php if ($is_request_open): ?>
                            <div style="margin-top: 15px; padding: 15px; background: #f7fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <strong><i class="fas fa-truck"></i> Delivery Status:</strong>
                                <div style="margin-top: 10px; font-size: 0.85rem; color: #4a5568;">
                                    <?php if ($repair['item_received_date']): ?>
                                        <div style="margin-bottom: 5px;">
                                            <i class="fas fa-check-circle" style="color: #38a169;"></i>
                                            Item received at Sims-Tech: <?php echo date('M d, Y H:i', strtotime($repair['item_received_date'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-bottom: 5px; color: #718096;">
                                            <i class="fas fa-clock" style="color: #cbd5e0;"></i>
                                            Waiting for item to be received at Sims-Tech
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($repair['in_transit_to_office_date']): ?>
                                        <div style="margin-bottom: 5px;">
                                            <i class="fas fa-truck" style="color: #2b6cb0;"></i>
                                            Item on its way to our office: <?php echo date('M d, Y H:i', strtotime($repair['in_transit_to_office_date'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($repair['completed_date']): ?>
                                        <div style="margin-bottom: 5px;">
                                            <i class="fas fa-check-circle" style="color: #38a169;"></i>
                                            Repair completed: <?php echo date('M d, Y H:i', strtotime($repair['completed_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($repair['in_transit_date']): ?>
                                        <div style="margin-bottom: 5px;">
                                            <i class="fas fa-shipping-fast" style="color: #9f7aea;"></i>
                                            Item in transit to you: <?php echo date('M d, Y H:i', strtotime($repair['in_transit_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($repair['delivered_date']): ?>
                                        <div style="margin-bottom: 5px;">
                                            <i class="fas fa-hand-holding" style="color: #805ad5;"></i>
                                            Delivered to you: <?php echo date('M d, Y H:i', strtotime($repair['delivered_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($repair['final_cost'] && $repair['final_cost'] > 0 && (!$repair['payment_status'] || $repair['payment_status'] === 'pending')): ?>
                                <div style="margin-top: 15px;">
                                    <a href="repair_payment.php?id=<?php echo $repair['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </a>
                                </div>
                            <?php elseif ($repair['receipt_id']): ?>
                                <div style="margin-top: 15px;">
                                    <a href="receipt.php?id=<?php echo (int)$repair['receipt_id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-receipt"></i> View Receipt
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-repairs">
                    <i class="fas fa-tools"></i>
                    <p>No repair requests yet</p>
                    <a href="customer_book_repair.php" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Book Your First Repair
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Orders Section -->
        <div class="repairs-section" style="margin-top: 30px;">
            <div class="section-header">
                <h2><i class="fas fa-shopping-bag"></i> My Orders</h2>
                <span style="color: #718096; font-size: 0.9rem;">
                    Total: <?php echo $orders->num_rows; ?> orders
                </span>
            </div>
            
            <?php if ($orders->num_rows > 0): ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <div class="repair-card">
                        <div class="repair-header">
                            <div>
                                <div class="repair-ticket"><?php echo htmlspecialchars($order['invoice_number']); ?></div>
                                <div class="repair-device">
                                    <i class="fas fa-shopping-cart"></i>
                                    Order #<?php echo $order['id']; ?>
                                </div>
                            </div>
                            <?php if (($order['payment_status'] ?? 'paid') === 'pending'): ?>
                                <span class="status-badge status-booked">Pay on collection</span>
                            <?php else: ?>
                                <span class="status-badge status-completed">Paid - <?php echo e(ucfirst(str_replace('_', ' ', $order['payment_method']))); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="repair-details">
                            <p><strong>Items:</strong> <?php echo $order['item_count']; ?> items</p>
                            <p><strong>Total:</strong> K<?php echo number_format($order['total_amount'], 2); ?></p>
                            <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['sale_date'])); ?></p>
                            <?php if ($order['receipt_id']): ?>
                                <a href="receipt.php?id=<?php echo (int)$order['receipt_id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-receipt"></i> Receipt</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-repairs">
                    <i class="fas fa-shopping-bag"></i>
                    <p>No orders yet</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-shopping-cart"></i> Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Receipts Section -->
        <div class="repairs-section" style="margin-top: 30px;">
            <div class="section-header">
                <h2><i class="fas fa-receipt"></i> My Receipts</h2>
                <span style="color: #718096; font-size: 0.9rem;">Total: <?php echo $receipts->num_rows; ?></span>
            </div>
            <?php if ($receipts->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Receipt No</th><th>For</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                            <?php while ($receipt = $receipts->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo e($receipt['document_number']); ?></strong></td>
                                    <td><?php echo e($receipt['notes']); ?></td>
                                    <td>K<?php echo number_format($receipt['total_amount'], 2); ?></td>
                                    <td><?php echo e(ucfirst(str_replace('_', ' ', $receipt['payment_method']))); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($receipt['created_at'])); ?></td>
                                    <td>
                                        <a href="receipt.php?id=<?php echo (int)$receipt['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View</a>
                                        <a href="receipt.php?id=<?php echo (int)$receipt['id']; ?>&amp;print=1" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-repairs">
                    <i class="fas fa-receipt"></i>
                    <p>No receipts yet. A receipt is created and emailed to you automatically every time you pay.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Update cart count from localStorage
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('customer_cart')) || [];
            document.getElementById('cartCount').textContent = cart.length;
        }

        // Update cart count on page load
        updateCartCount();

        // Update cart count periodically (in case it changes in other tabs)
        setInterval(updateCartCount, 1000);
    </script>
    <?php echo customerLogoutFooter(); ?>
    <?php echo idleLogoutScript(); ?>
</body>
</html>