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
            // Build date condition based on period type
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
                    // Count sales to be deleted
                    $count_result = $conn->query("SELECT COUNT(*) as cnt FROM sales WHERE $date_condition");
                    $sale_count = $count_result->fetch_assoc()['cnt'];
                    
                    if ($sale_count == 0) {
                        $error = "No sales found for $period_label.";
                        $conn->rollback();
                    } else {
                        // Get all sale IDs for this period
                        $sale_ids_result = $conn->query("SELECT id FROM sales WHERE $date_condition");
                        $sale_ids = [];
                        while ($row = $sale_ids_result->fetch_assoc()) {
                            $sale_ids[] = $row['id'];
                        }
                        $ids_str = implode(',', $sale_ids);
                        
                        // Restore product stock for all affected sales
                        $items = $conn->query("SELECT product_id, SUM(quantity) as total_qty FROM sale_items WHERE sale_id IN ($ids_str) AND item_type = 'product' GROUP BY product_id");
                        while ($item = $items->fetch_assoc()) {
                            $conn->query("UPDATE products SET quantity = quantity + {$item['total_qty']} WHERE id = {$item['product_id']}");
                        }
                        
                        // Delete sale items then sales
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

// Handle delete transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
    if (!isAdmin()) {
        $error = 'Access denied. Only administrators can delete transactions.';
    } else {
        $sale_id = intval($_POST['sale_id']);
        
        // Start transaction for safe deletion
        $conn->begin_transaction();
        
        try {
            // Get sale details for stock restoration
            $sale_items = $conn->query("SELECT * FROM sale_items WHERE sale_id = $sale_id");
            
            // Restore product stock
            while ($item = $sale_items->fetch_assoc()) {
                if ($item['item_type'] === 'product') {
                    $conn->query("UPDATE products SET quantity = quantity + {$item['quantity']} WHERE id = {$item['product_id']}");
                }
            }
            
            // Delete sale items
            $conn->query("DELETE FROM sale_items WHERE sale_id = $sale_id");
            
            // Delete sale record
            $conn->query("DELETE FROM sales WHERE id = $sale_id");
            
            $conn->commit();
            $message = "Transaction deleted successfully! Stock has been restored.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error deleting transaction: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$employee_filter = $_GET['employee'] ?? '';

// Build query
$where = "WHERE DATE(s.sale_date) BETWEEN '$date_from' AND '$date_to'";
if ($employee_filter) {
    $where .= " AND s.user_id = " . intval($employee_filter);
}

// Get sales
$sales = $conn->query("
    SELECT s.*, u.full_name as employee_name,
           (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as item_count
    FROM sales s 
    LEFT JOIN users u ON s.user_id = u.id 
    $where 
    ORDER BY s.sale_date DESC
");

// Get totals
$totals = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count FROM sales s $where")->fetch_assoc();

// Get employees for filter
$employees = $conn->query("SELECT id, full_name FROM users ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Sims-Tech Zambia</title>
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
                    <h1><i class="fas fa-receipt"></i> Sales History</h1>
                    <p>View and manage sales records</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Summary Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $totals['count']; ?></h3>
                            <p>Total Transactions</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3>K<?php echo number_format($totals['total'], 2); ?></h3>
                            <p>Total Revenue</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                            <div class="form-group" style="margin: 0;">
                                <label>From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label>To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label>Employee</label>
                                <select name="employee" class="form-control">
                                    <option value="">All Employees</option>
                                    <?php while ($emp = $employees->fetch_assoc()): ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo $employee_filter == $emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="sales.php" class="btn btn-secondary">Reset</a>
                        </form>
                    </div>
                </div>
                
                <!-- Sales Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Sales Records</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-success btn-sm" onclick="exportToCSV('salesTable', 'sales_report.csv')">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                            <?php if (isAdmin()): ?>
                            <button class="btn btn-danger btn-sm" onclick="openClearModal()">
                                <i class="fas fa-trash-alt"></i> Clear History
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="salesTable">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Customer</th>
                                        <th>Employee</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Date & Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($sales->num_rows > 0): ?>
                                        <?php while ($sale = $sales->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                                                <td>
                                                    <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?>
                                                    <?php if ($sale['customer_phone']): ?>
                                                        <br><small><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($sale['employee_name']); ?></td>
                                                <td><span class="badge badge-info"><?php echo $sale['item_count']; ?> items</span></td>
                                                <td><strong>K<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                                <td><span class="badge badge-success"><?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?></span></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                                <td>
                                                    <button class="action-btn view" onclick="viewSale(<?php echo $sale['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (isAdmin()): ?>
                                                    <button class="action-btn delete" onclick="deleteSale(<?php echo $sale['id']; ?>, '<?php echo htmlspecialchars($sale['invoice_number']); ?>')" title="Delete Transaction">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No sales found for the selected period</td>
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
    
    <!-- Sale Details Modal -->
    <div class="modal-overlay" id="saleModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Sale Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="saleDetails">
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                <button class="btn btn-primary" onclick="printReceipt()"><i class="fas fa-print"></i> Print</button>
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
                    
                    <div class="alert alert-danger" style="margin-bottom: 15px; padding: 12px; border-radius: 8px; background: #fff5f5; border: 1px solid #feb2b2; color: #c53030;">
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
                    
                    <div id="clearPreview" style="display: none; background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 8px; padding: 12px; margin-top: 10px;">
                        <p style="margin: 0; color: #2b6cb0; font-size: 0.9rem;"><i class="fas fa-info-circle"></i> <span id="clearPreviewText"></span></p>
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
        function viewSale(saleId) {
            document.getElementById('saleModal').classList.add('active');
            
            fetch('ajax/get_sale.php?id=' + saleId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('saleDetails').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('saleDetails').innerHTML = '<p class="text-danger">Error loading sale details</p>';
                });
        }
        
        function closeModal() {
            document.getElementById('saleModal').classList.remove('active');
        }
        
        function printReceipt() {
            const content = document.getElementById('saleDetails').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; max-width: 400px; margin: 0 auto; }
                        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                        .text-right { text-align: right; }
                        .total { font-weight: bold; font-size: 1.2em; }
                        h2, h3 { text-align: center; margin: 10px 0; }
                        .company-info { text-align: center; margin-bottom: 20px; }
                    </style>
                </head>
                <body>
                    <div class="company-info">
                        <h2>AC-TECHNOLOGY</h2>
                        <p>Your Trusted Technology Partner</p>
                    </div>
                    ${content}
                    <p style="text-align: center; margin-top: 30px;">Thank you for your business!</p>
                    <script>window.onload = function() { window.print(); window.close(); }<\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
        
        function exportToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach((col, index) => {
                    if (index < cols.length - 1) { // Skip actions column
                        let text = col.textContent.replace(/"/g, '""').trim();
                        rowData.push('"' + text + '"');
                    }
                });
                csv.push(rowData.join(','));
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
        }
        
        document.getElementById('saleModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        <?php if (isAdmin()): ?>
        function openClearModal() {
            document.getElementById('clearPeriod').value = '';
            document.getElementById('clearDateGroup').style.display = 'none';
            document.getElementById('clearPreview').style.display = 'none';
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
            
            // Hide and disable all
            dayInput.style.display = 'none';
            monthInput.style.display = 'none';
            yearInput.style.display = 'none';
            dayInput.disabled = true;
            monthInput.disabled = true;
            yearInput.disabled = true;
            document.getElementById('clearPreview').style.display = 'none';
            
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
            
            return confirm('Are you sure you want to DELETE ALL sales for ' + label + '?\n\nThis will:\n• Permanently remove all sales records for this period\n• Restore all product stock quantities\n• This action CANNOT be undone');
        }
        <?php endif; ?>
        
        function deleteSale(saleId, invoiceNumber) {
            if (confirm(`Are you sure you want to delete transaction ${invoiceNumber}?\n\nThis will:\n• Remove the sale record\n• Restore all product quantities\n• This action cannot be undone`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_sale';
                input.value = '1';
                
                const saleIdInput = document.createElement('input');
                saleIdInput.type = 'hidden';
                saleIdInput.name = 'sale_id';
                saleIdInput.value = saleId;
                
                form.appendChild(input);
                form.appendChild(saleIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
