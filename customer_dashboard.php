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

// Get customer's repairs
$repairs = $conn->query("
    SELECT r.*, 
           u1.full_name as technician_name
    FROM repairs r 
    LEFT JOIN users u1 ON r.technician_id = u1.id 
    WHERE r.customer_id = {$user['id']}
    ORDER BY r.created_at DESC
");

// Get counts
$booked_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE customer_id = {$user['id']} AND status = 'booked'")->fetch_assoc()['c'];
$in_progress_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE customer_id = {$user['id']} AND status = 'in_progress'")->fetch_assoc()['c'];
$completed_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE customer_id = {$user['id']} AND status = 'completed'")->fetch_assoc()['c'];
$delivered_count = $conn->query("SELECT COUNT(*) as c FROM repairs WHERE customer_id = {$user['id']} AND status = 'delivered'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - AC-TECHNOLOGY</title>
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
        .status-in_progress { background: #dd6b20; color: white; }
        .status-completed { background: #38a169; color: white; }
        .status-delivered { background: #805ad5; color: white; }
        .status-cancelled { background: #e53e3e; color: white; }
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
        .progress-fill.booked { width: 25%; background: #3182ce; }
        .progress-fill.in_progress { width: 50%; background: #dd6b20; }
        .progress-fill.completed { width: 75%; background: #38a169; }
        .progress-fill.delivered { width: 100%; background: #805ad5; }
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
                <img src="assets/images/logo.png" alt="AC-TECHNOLOGY Logo" onerror="this.style.display='none'" style="max-height: 40px; margin-bottom: 10px;">
                <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p><?php echo htmlspecialchars($user['email'] ?? ''); ?> | <?php echo htmlspecialchars($user['phone'] ?? ''); ?></p>
            </div>
            <div class="customer-actions">
                <a href="customer_book_repair.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Book New Repair
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
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
                <div class="stat-icon purple">
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
                <span style="color: #718096; font-size: 0.9rem;">
                    Total: <?php echo $repairs->num_rows; ?> repairs
                </span>
            </div>
            
            <?php if ($repairs->num_rows > 0): ?>
                <?php while ($repair = $repairs->fetch_assoc()): ?>
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
                                <?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $repair['status']; ?>"></div>
                        </div>
                        
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
    </div>
</body>
</html>