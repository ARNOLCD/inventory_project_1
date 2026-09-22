<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle delete (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!isAdmin()) {
        $error = 'Only administrators can delete documents.';
    } else {
        $delete_id = intval($_POST['delete_id']);
        if ($conn->query("DELETE FROM documents WHERE id = $delete_id")) {
            $message = 'Document deleted successfully!';
        } else {
            $error = 'Error deleting document.';
        }
    }
}

// Get filter
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where = "WHERE 1=1";
if ($type_filter) {
    $where .= " AND d.document_type = '" . $conn->real_escape_string($type_filter) . "'";
}
if ($status_filter) {
    $where .= " AND d.status = '" . $conn->real_escape_string($status_filter) . "'";
}

// Get documents
$documents = $conn->query("
    SELECT d.*, u.full_name as created_by,
           (SELECT COUNT(*) FROM document_items WHERE document_id = d.id) as item_count
    FROM documents d 
    LEFT JOIN users u ON d.user_id = u.id 
    $where 
    ORDER BY d.created_at DESC
");

// Get counts
$invoice_count = $conn->query("SELECT COUNT(*) as c FROM documents WHERE document_type = 'invoice'")->fetch_assoc()['c'];
$quotation_count = $conn->query("SELECT COUNT(*) as c FROM documents WHERE document_type = 'quotation'")->fetch_assoc()['c'];
$receipt_count = $conn->query("SELECT COUNT(*) as c FROM documents WHERE document_type = 'receipt'")->fetch_assoc()['c'];

// Page titles and icons based on type filter
$page_config = [
    '' => ['title' => 'All Documents', 'subtitle' => 'Manage invoices, quotations, and receipts', 'icon' => 'fa-file-alt'],
    'invoice' => ['title' => 'Invoices', 'subtitle' => 'Manage your invoices', 'icon' => 'fa-file-invoice'],
    'quotation' => ['title' => 'Quotations', 'subtitle' => 'Manage your quotations', 'icon' => 'fa-file-invoice-dollar'],
    'receipt' => ['title' => 'Receipts', 'subtitle' => 'Manage your receipts', 'icon' => 'fa-receipt'],
];
$current_config = $page_config[$type_filter] ?? $page_config[''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_config['title']; ?> - AC-TECHNOLOGY</title>
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
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1><i class="fas <?php echo $current_config['icon']; ?>"></i> <?php echo $current_config['title']; ?></h1>
                        <p><?php echo $current_config['subtitle']; ?></p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($type_filter === 'invoice' || !$type_filter): ?>
                            <a href="create_document.php?type=invoice" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Invoice
                            </a>
                        <?php endif; ?>
                        <?php if ($type_filter === 'quotation' || !$type_filter): ?>
                            <a href="create_document.php?type=quotation" class="btn btn-success">
                                <i class="fas fa-plus"></i> New Quotation
                            </a>
                        <?php endif; ?>
                        <?php if ($type_filter === 'receipt' || !$type_filter): ?>
                            <a href="create_document.php?type=receipt" class="btn btn-warning">
                                <i class="fas fa-plus"></i> New Receipt
                            </a>
                        <?php endif; ?>
                    </div>
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
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $invoice_count; ?></h3>
                            <p>Invoices</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $quotation_count; ?></h3>
                            <p>Quotations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $receipt_count; ?></h3>
                            <p>Receipts</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                        <a href="documents.php" class="btn <?php echo !$type_filter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">All</a>
                        <a href="documents.php?type=invoice" class="btn <?php echo $type_filter === 'invoice' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-file-invoice"></i> Invoices
                        </a>
                        <a href="documents.php?type=quotation" class="btn <?php echo $type_filter === 'quotation' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-file-invoice-dollar"></i> Quotations
                        </a>
                        <a href="documents.php?type=receipt" class="btn <?php echo $type_filter === 'receipt' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-receipt"></i> Receipts
                        </a>
                        <span style="margin-left: auto;">
                            <select onchange="window.location.href='documents.php?type=<?php echo $type_filter; ?>&status='+this.value" class="form-control" style="width: auto; display: inline-block;">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </span>
                    </div>
                </div>
                
                <!-- Documents Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> <?php echo $current_config['title']; ?> List</h3>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search..." style="width: 200px;" onkeyup="searchTable()">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="documentsTable">
                                <thead>
                                    <tr>
                                        <th>Document #</th>
                                        <th>Type</th>
                                        <th>Client</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($documents->num_rows > 0): ?>
                                        <?php while ($doc = $documents->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($doc['document_number']); ?></strong></td>
                                                <td>
                                                    <?php
                                                    $type_badges = [
                                                        'invoice' => 'badge-info',
                                                        'quotation' => 'badge-success',
                                                        'receipt' => 'badge-warning'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $type_badges[$doc['document_type']]; ?>">
                                                        <?php echo ucfirst($doc['document_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($doc['client_name']); ?></strong>
                                                    <?php if ($doc['client_phone']): ?>
                                                        <br><small><?php echo htmlspecialchars($doc['client_phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge badge-info"><?php echo $doc['item_count']; ?> items</span></td>
                                                <td><strong>K<?php echo number_format($doc['total_amount'], 2); ?></strong></td>
                                                <td>
                                                    <?php
                                                    $status_badges = [
                                                        'draft' => 'badge-secondary',
                                                        'sent' => 'badge-info',
                                                        'paid' => 'badge-success',
                                                        'cancelled' => 'badge-danger'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $status_badges[$doc['status']] ?? 'badge-secondary'; ?>">
                                                        <?php echo ucfirst($doc['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                                <td>
                                                    <div class="action-btns">
                                                        <a href="view_document.php?id=<?php echo $doc['id']; ?>" class="action-btn view" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="generate_pdf.php?id=<?php echo $doc['id']; ?>" class="action-btn edit" title="Download PDF" target="_blank">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>
                                                        <?php if (isAdmin()): ?>
                                                        <a href="create_document.php?edit=<?php echo $doc['id']; ?>" class="action-btn edit" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="action-btn delete" onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['document_number']); ?>')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No documents found</td>
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
    
    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_id" id="deleteId">
    </form>
    
    <script src="assets/js/main.js"></script>
    <script>
        function deleteDocument(id, number) {
            if (confirm('Are you sure you want to delete document "' + number + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#documentsTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
