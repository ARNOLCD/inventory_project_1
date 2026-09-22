<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();

// Get date range
$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');

// Calculate date ranges
switch ($period) {
    case 'today':
        $date_from = date('Y-m-d');
        $date_to = date('Y-m-d');
        break;
    case 'week':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        $date_to = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
        break;
    case 'year':
        $date_from = "$year-01-01";
        $date_to = "$year-12-31";
        break;
    default:
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
}

// Get sales summary
$sales_summary = $conn->query("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_transaction
    FROM sales 
    WHERE DATE(sale_date) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc();

// Get items sold
$items_sold = $conn->query("
    SELECT COALESCE(SUM(si.quantity), 0) as total_items
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'
")->fetch_assoc();

// Get top selling products
$top_products = $conn->query("
    SELECT p.name, p.serial_number, SUM(si.quantity) as qty_sold, SUM(si.total_price) as revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN sales s ON si.sale_id = s.id
    WHERE si.item_type = 'product' AND DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'
    GROUP BY p.id
    ORDER BY qty_sold DESC
    LIMIT 10
");

// Get sales by category
$sales_by_category = $conn->query("
    SELECT c.name, SUM(si.quantity) as qty_sold, SUM(si.total_price) as revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN sales s ON si.sale_id = s.id
    WHERE si.item_type = 'product' AND DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'
    GROUP BY c.id
    ORDER BY revenue DESC
");

// Get sales by employee
$sales_by_employee = $conn->query("
    SELECT u.full_name, COUNT(s.id) as transactions, SUM(s.total_amount) as revenue
    FROM sales s
    JOIN users u ON s.user_id = u.id
    WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'
    GROUP BY u.id
    ORDER BY revenue DESC
");

// Get daily sales for chart
$daily_sales = $conn->query("
    SELECT DATE(sale_date) as date, SUM(total_amount) as total
    FROM sales
    WHERE DATE(sale_date) BETWEEN '$date_from' AND '$date_to'
    GROUP BY DATE(sale_date)
    ORDER BY date
");

$chart_dates = [];
$chart_amounts = [];
while ($row = $daily_sales->fetch_assoc()) {
    $chart_dates[] = date('M d', strtotime($row['date']));
    $chart_amounts[] = floatval($row['total']);
}

// Get payment method breakdown
$payment_methods = $conn->query("
    SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
    FROM sales
    WHERE DATE(sale_date) BETWEEN '$date_from' AND '$date_to'
    GROUP BY payment_method
");

$payment_labels = [];
$payment_data = [];
while ($row = $payment_methods->fetch_assoc()) {
    $payment_labels[] = ucfirst(str_replace('_', ' ', $row['payment_method']));
    $payment_data[] = floatval($row['total']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AC-TECHNOLOGY</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>body { font-family: 'Poppins', sans-serif; }
    
    /* Brown Report Buttons */
    .btn-brown {
        background: linear-gradient(135deg, #8b4513 0%, #a0522d 100%);
        color: #fff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 3px 10px rgba(139, 69, 19, 0.4);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
    }
    
    .btn-brown:hover {
        background: linear-gradient(135deg, #a0522d 0%, #cd853f 100%);
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 5px 15px rgba(139, 69, 19, 0.6);
        color: #fff;
    }
    
    .btn-brown-active {
        background: linear-gradient(135deg, #cd853f 0%, #daa520 100%);
        color: #fff;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(205, 133, 63, 0.6);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.85rem;
        animation: pulse 2s infinite;
    }
    
    .btn-brown-active:hover {
        background: linear-gradient(135deg, #daa520 0%, #f4a460 100%);
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 20px rgba(205, 133, 63, 0.8);
        color: #fff;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 4px 15px rgba(205, 133, 63, 0.6); }
        50% { box-shadow: 0 4px 20px rgba(205, 133, 63, 0.8); }
        100% { box-shadow: 0 4px 15px rgba(205, 133, 63, 0.6); }
    }
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
                        <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
                        <p>Sales performance and inventory insights</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="?period=today" class="btn <?php echo $period === 'today' ? 'btn-brown-active' : 'btn-brown'; ?> btn-sm">Today</a>
                        <a href="?period=week" class="btn <?php echo $period === 'week' ? 'btn-brown-active' : 'btn-brown'; ?> btn-sm">This Week</a>
                        <a href="?period=month" class="btn <?php echo $period === 'month' ? 'btn-brown-active' : 'btn-brown'; ?> btn-sm">This Month</a>
                        <a href="?period=year" class="btn <?php echo $period === 'year' ? 'btn-brown-active' : 'btn-brown'; ?> btn-sm">This Year</a>
                    </div>
                </div>
                
                <p class="mb-4"><strong>Report Period:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
                
                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($sales_summary['total_revenue'], 2); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $sales_summary['total_transactions']; ?></h3>
                            <p>Transactions</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $items_sold['total_items']; ?></h3>
                            <p>Items Sold</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($sales_summary['avg_transaction'], 2); ?></h3>
                            <p>Avg. Transaction</p>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid-2 mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Sales Trend</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tables Row -->
                <div class="grid-2 mt-4">
                    <!-- Top Products -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-trophy"></i> Top Selling Products</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Qty Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $i = 1; while ($product = $top_products->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                    <br><small><?php echo htmlspecialchars($product['serial_number']); ?></small>
                                                </td>
                                                <td><span class="badge badge-info"><?php echo $product['qty_sold']; ?></span></td>
                                                <td><strong>K<?php echo number_format($product['revenue'], 2); ?></strong></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <?php if ($top_products->num_rows === 0): ?>
                                            <tr><td colspan="4" class="text-center">No data available</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sales by Category -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-tags"></i> Sales by Category</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Qty Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($cat = $sales_by_category->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                                <td><span class="badge badge-info"><?php echo $cat['qty_sold']; ?></span></td>
                                                <td><strong>K<?php echo number_format($cat['revenue'], 2); ?></strong></td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <?php if ($sales_by_category->num_rows === 0): ?>
                                            <tr><td colspan="3" class="text-center">No data available</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Employee Performance -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-users"></i> Employee Performance</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Employee</th>
                                        <th>Transactions</th>
                                        <th>Total Revenue</th>
                                        <th>Avg. per Transaction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; while ($emp = $sales_by_employee->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                            <td><span class="badge badge-info"><?php echo $emp['transactions']; ?></span></td>
                                            <td><strong>K<?php echo number_format($emp['revenue'], 2); ?></strong></td>
                                            <td>K<?php echo number_format($emp['revenue'] / max($emp['transactions'], 1), 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if ($sales_by_employee->num_rows === 0): ?>
                                        <tr><td colspan="5" class="text-center">No data available</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Sales Trend Chart
        new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_dates); ?>,
                datasets: [{
                    label: 'Sales (K)',
                    data: <?php echo json_encode($chart_amounts); ?>,
                    borderColor: 'rgba(49, 130, 206, 1)',
                    backgroundColor: 'rgba(49, 130, 206, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return 'K' + value.toLocaleString(); }
                        }
                    }
                }
            }
        });
        
        // Payment Methods Chart
        new Chart(document.getElementById('paymentChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($payment_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($payment_data); ?>,
                    backgroundColor: [
                        'rgba(56, 161, 105, 0.8)',
                        'rgba(49, 130, 206, 0.8)',
                        'rgba(214, 158, 46, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>
