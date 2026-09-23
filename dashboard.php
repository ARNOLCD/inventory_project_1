<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle clear sales history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_sales'])) {
    if (!isAdmin()) {
        $error = 'Access denied. Only administrators can clear sales history.';
    } else {
        $clear_period = $_POST['clear_period'] ?? '';
        $clear_date = $_POST['clear_date'] ?? '';
        
        if (empty($clear_period) || empty($clear_date)) {
            $error = 'Please select a period and date.';
        } else {
            switch ($clear_period) {
                case 'day':
                    $date_condition = "DATE(sale_date) = '" . $conn->real_escape_string($clear_date) . "'";
                    $period_label = date('M d, Y', strtotime($clear_date));
                    break;
                case 'month':
                    $date_condition = "DATE_FORMAT(sale_date, '%Y-%m') = '" . $conn->real_escape_string($clear_date) . "'";
                    $period_label = date('F Y', strtotime($clear_date . '-01'));
                    break;
                case 'year':
                    $date_condition = "YEAR(sale_date) = '" . intval($clear_date) . "'";
                    $period_label = intval($clear_date);
                    break;
                default:
                    $error = 'Invalid period type.';
                    $date_condition = null;
            }
            
            if ($date_condition && empty($error)) {
                $conn->begin_transaction();
                try {
                    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM sales WHERE $date_condition");
                    $sale_count = $count_result->fetch_assoc()['cnt'];
                    
                    if ($sale_count == 0) {
                        $error = "No sales found for $period_label.";
                        $conn->rollback();
                    } else {
                        $sale_ids_result = $conn->query("SELECT id FROM sales WHERE $date_condition");
                        $sale_ids = [];
                        while ($row = $sale_ids_result->fetch_assoc()) {
                            $sale_ids[] = $row['id'];
                        }
                        $ids_str = implode(',', $sale_ids);
                        
                        $items = $conn->query("SELECT product_id, SUM(quantity) as total_qty FROM sale_items WHERE sale_id IN ($ids_str) AND item_type = 'product' GROUP BY product_id");
                        while ($item = $items->fetch_assoc()) {
                            $conn->query("UPDATE products SET quantity = quantity + {$item['total_qty']} WHERE id = {$item['product_id']}");
                        }
                        
                        $conn->query("DELETE FROM sale_items WHERE sale_id IN ($ids_str)");
                        $conn->query("DELETE FROM sales WHERE id IN ($ids_str)");
                        
                        $conn->commit();
                        $message = "Successfully cleared $sale_count sale(s) for <strong>$period_label</strong>. Stock has been restored.";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Error clearing sales: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'")->fetch_assoc()['count'];
$total_services = $conn->query("SELECT COUNT(*) as count FROM services WHERE status = 'active'")->fetch_assoc()['count'];
$low_stock = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level AND status = 'active'")->fetch_assoc()['count'];

// Sales statistics
$today_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['total'];
$week_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['total'];
$month_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())")->fetch_assoc()['total'];
$year_sales = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE YEAR(sale_date) = YEAR(CURDATE())")->fetch_assoc()['total'];

// Items sold statistics
$today_items = $conn->query("SELECT COALESCE(SUM(si.quantity), 0) as count FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE DATE(s.sale_date) = CURDATE()")->fetch_assoc()['count'];
$week_items = $conn->query("SELECT COALESCE(SUM(si.quantity), 0) as count FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
$month_items = $conn->query("SELECT COALESCE(SUM(si.quantity), 0) as count FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())")->fetch_assoc()['count'];

// Get low stock products
$low_stock_products = getLowStockProducts($conn);

// Recent sales
$recent_sales = $conn->query("SELECT s.*, u.full_name as employee_name FROM sales s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.sale_date DESC LIMIT 5");

// Monthly sales data for chart
$monthly_sales_data = $conn->query("
    SELECT 
        MONTH(sale_date) as month,
        COALESCE(SUM(total_amount), 0) as total
    FROM sales 
    WHERE YEAR(sale_date) = YEAR(CURDATE())
    GROUP BY MONTH(sale_date)
    ORDER BY month
");

$chart_labels = [];
$chart_data = [];
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthly_totals = array_fill(1, 12, 0);

while ($row = $monthly_sales_data->fetch_assoc()) {
    $monthly_totals[$row['month']] = floatval($row['total']);
}

for ($i = 1; $i <= 12; $i++) {
    $chart_labels[] = $months[$i - 1];
    $chart_data[] = $monthly_totals[$i];
}

// Top selling products
$top_products = $conn->query("
    SELECT p.name, SUM(si.quantity) as total_sold, SUM(si.total_price) as revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.item_type = 'product'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="page-header">
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success" style="margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Low Stock Alert -->
                <?php if ($low_stock > 0): ?>
                <div class="low-stock-alert">
                    <h4><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h4>
                    <div class="low-stock-list">
                        <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                            <div class="low-stock-item">
                                <?php echo htmlspecialchars($product['name']); ?>
                                <span class="qty"><?php echo $product['quantity']; ?> left</span>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($today_sales, 2); ?></h3>
                            <p>Today's Sales</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($week_sales, 2); ?></h3>
                            <p>This Week</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($month_sales, 2); ?></h3>
                            <p>This Month</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($year_sales, 2); ?></h3>
                            <p>This Year</p>
                        </div>
                    </div>
                </div>
                
                <!-- Second Row Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_products; ?></h3>
                            <p>Total Products</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_services; ?></h3>
                            <p>Active Services</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $today_items; ?></h3>
                            <p>Items Sold Today</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon <?php echo $low_stock > 0 ? 'red' : 'green'; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $low_stock; ?></h3>
                            <p>Low Stock Items</p>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid-2 mt-4">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Monthly Sales (<?php echo date('Y'); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-trophy"></i> Top Selling Products</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Items Sold Summary -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Items Sold Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon blue">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $today_items; ?></h3>
                                    <p>Today</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon green">
                                    <i class="fas fa-calendar-week"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $week_items; ?></h3>
                                    <p>This Week</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon purple">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $month_items; ?></h3>
                                    <p>This Month</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Sales -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Sales</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if (isAdmin()): ?>
                            <button class="btn btn-danger btn-sm" onclick="openClearModal()">
                                <i class="fas fa-trash-alt"></i> Clear History
                            </button>
                            <?php endif; ?>
                            <a href="sales.php" class="btn btn-primary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Customer</th>
                                        <th>Employee</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_sales->num_rows > 0): ?>
                                        <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?></td>
                                                <td><?php echo htmlspecialchars($sale['employee_name']); ?></td>
                                                <td><strong>K<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                <td><span class="badge badge-success"><?php echo ucfirst($sale['payment_method']); ?></span></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No sales recorded yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clear Sales History Modal -->
    <?php if (isAdmin()): ?>
    <div class="modal-overlay" id="clearModal">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-header">
                <h3><i class="fas fa-trash-alt" style="color: #e53e3e;"></i> Clear Sales History</h3>
                <button class="modal-close" onclick="closeClearModal()">&times;</button>
            </div>
            <form method="POST" id="clearForm" onsubmit="return confirmClear()">
                <div class="modal-body">
                    <input type="hidden" name="clear_sales" value="1">
                    
                    <div style="margin-bottom: 15px; padding: 12px; border-radius: 8px; background: #fff5f5; border: 1px solid #feb2b2; color: #c53030;">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This will permanently delete all sales records for the selected period. Product stock will be restored.
                    </div>
                    
                    <div class="form-group">
                        <label><strong>Clear by</strong></label>
                        <select name="clear_period" id="clearPeriod" class="form-control" required onchange="updateClearDateInput()">
                            <option value="">-- Select Period --</option>
                            <option value="day">Specific Day</option>
                            <option value="month">Entire Month</option>
                            <option value="year">Entire Year</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="clearDateGroup" style="display: none;">
                        <label id="clearDateLabel"><strong>Select Date</strong></label>
                        <input type="date" name="clear_date" id="clearDateDay" class="form-control" style="display: none;">
                        <input type="month" name="clear_date" id="clearDateMonth" class="form-control" style="display: none;">
                        <select name="clear_date" id="clearDateYear" class="form-control" style="display: none;">
                            <option value="">-- Select Year --</option>
                            <?php for ($y = intval(date('Y')); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeClearModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Clear Sales</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="assets/js/main.js"></script>
    <script>
        <?php if (isAdmin()): ?>
        function openClearModal() {
            document.getElementById('clearPeriod').value = '';
            document.getElementById('clearDateGroup').style.display = 'none';
            document.getElementById('clearDateDay').style.display = 'none';
            document.getElementById('clearDateMonth').style.display = 'none';
            document.getElementById('clearDateYear').style.display = 'none';
            document.getElementById('clearDateDay').disabled = true;
            document.getElementById('clearDateMonth').disabled = true;
            document.getElementById('clearDateYear').disabled = true;
            document.getElementById('clearModal').classList.add('active');
        }
        
        function closeClearModal() {
            document.getElementById('clearModal').classList.remove('active');
        }
        
        document.getElementById('clearModal').addEventListener('click', function(e) {
            if (e.target === this) closeClearModal();
        });
        
        function updateClearDateInput() {
            const period = document.getElementById('clearPeriod').value;
            const dayInput = document.getElementById('clearDateDay');
            const monthInput = document.getElementById('clearDateMonth');
            const yearInput = document.getElementById('clearDateYear');
            const label = document.getElementById('clearDateLabel');
            
            dayInput.style.display = 'none';
            monthInput.style.display = 'none';
            yearInput.style.display = 'none';
            dayInput.disabled = true;
            monthInput.disabled = true;
            yearInput.disabled = true;
            
            if (!period) {
                document.getElementById('clearDateGroup').style.display = 'none';
                return;
            }
            
            document.getElementById('clearDateGroup').style.display = 'block';
            
            if (period === 'day') {
                label.innerHTML = '<strong>Select Day</strong>';
                dayInput.style.display = 'block';
                dayInput.disabled = false;
                dayInput.required = true;
                monthInput.required = false;
                yearInput.required = false;
            } else if (period === 'month') {
                label.innerHTML = '<strong>Select Month</strong>';
                monthInput.style.display = 'block';
                monthInput.disabled = false;
                monthInput.required = true;
                dayInput.required = false;
                yearInput.required = false;
            } else if (period === 'year') {
                label.innerHTML = '<strong>Select Year</strong>';
                yearInput.style.display = 'block';
                yearInput.disabled = false;
                yearInput.required = true;
                dayInput.required = false;
                monthInput.required = false;
            }
        }
        
        function confirmClear() {
            const period = document.getElementById('clearPeriod').value;
            let dateVal = '';
            let label = '';
            
            if (period === 'day') {
                dateVal = document.getElementById('clearDateDay').value;
                if (!dateVal) { alert('Please select a date.'); return false; }
                label = new Date(dateVal + 'T00:00:00').toLocaleDateString('en-US', {year:'numeric', month:'long', day:'numeric'});
            } else if (period === 'month') {
                dateVal = document.getElementById('clearDateMonth').value;
                if (!dateVal) { alert('Please select a month.'); return false; }
                const parts = dateVal.split('-');
                label = new Date(parts[0], parts[1]-1).toLocaleDateString('en-US', {year:'numeric', month:'long'});
            } else if (period === 'year') {
                dateVal = document.getElementById('clearDateYear').value;
                if (!dateVal) { alert('Please select a year.'); return false; }
                label = dateVal;
            } else {
                alert('Please select a period.'); return false;
            }
            
            return confirm('Are you sure you want to DELETE ALL sales for ' + label + '?\n\nThis will:\n\u2022 Permanently remove all sales records\n\u2022 Restore all product stock quantities\n\u2022 This action CANNOT be undone');
        }
        <?php endif; ?>
        
        // Monthly Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Sales (K)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(49, 130, 206, 0.8)',
                    borderColor: 'rgba(49, 130, 206, 1)',
                    borderWidth: 1,
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'K' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Top Products Chart
        <?php
        $product_names = [];
        $product_sales = [];
        $top_products->data_seek(0);
        while ($row = $top_products->fetch_assoc()) {
            $product_names[] = $row['name'];
            $product_sales[] = floatval($row['total_sold']);
        }
        ?>
        
        const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
        new Chart(topProductsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($product_names); ?>,
                datasets: [{
                    data: <?php echo json_encode($product_sales); ?>,
                    backgroundColor: [
                        'rgba(49, 130, 206, 0.8)',
                        'rgba(56, 161, 105, 0.8)',
                        'rgba(214, 158, 46, 0.8)',
                        'rgba(128, 90, 213, 0.8)',
                        'rgba(229, 62, 62, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
