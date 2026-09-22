<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <img src="assets/images/logo.png" alt="AC-TECHNOLOGY Logo" onerror="this.style.display='none'">
        <h2>AC-TECHNOLOGY</h2>
    </div>
    <nav class="sidebar-menu">
        <?php if (isCustomer()): ?>
            <!-- Customer Menu -->
            <a href="customer_dashboard.php" class="<?php echo $current_page === 'customer_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> My Dashboard
            </a>
            
            <div class="menu-section">
                <span class="menu-section-title">Repairs</span>
            </div>
            <a href="customer_book_repair.php" class="<?php echo $current_page === 'customer_book_repair.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> Book Repair
            </a>
            <a href="customer_dashboard.php" class="<?php echo $current_page === 'customer_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i> My Repairs
            </a>
            
            <div class="menu-section">
                <span class="menu-section-title">Account</span>
            </div>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php else: ?>
            <!-- Staff/Admin Menu -->
            <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            
            <div class="menu-section">
                <span class="menu-section-title">Inventory</span>
            </div>
            <a href="products.php" class="<?php echo $current_page === 'products.php' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i> Products
            </a>
            <a href="categories.php" class="<?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
            <a href="services.php" class="<?php echo $current_page === 'services.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i> Services
            </a>
            
            <div class="menu-section">
                <span class="menu-section-title">Sales</span>
            </div>
            <a href="pos.php" class="<?php echo $current_page === 'pos.php' ? 'active' : ''; ?>">
                <i class="fas fa-cash-register"></i> Point of Sale
            </a>
            <a href="sales.php" class="<?php echo $current_page === 'sales.php' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Sales History
            </a>
            <a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            
            <div class="menu-section">
                <span class="menu-section-title">Repairs</span>
            </div>
            <a href="repairs.php" class="<?php echo $current_page === 'repairs.php' ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i> Repair Tracking
            </a>
            
            <div class="menu-section">
                <span class="menu-section-title">Documents</span>
            </div>
            <?php
            // Determine active document type for sidebar highlighting
            $sidebar_doc_type = $_GET['type'] ?? '';
            // When viewing/creating a document, detect the type for sidebar highlighting
            if ($current_page === 'view_document.php' || $current_page === 'create_document.php') {
                if (isset($document['document_type'])) {
                    $sidebar_doc_type = $document['document_type'];
                } elseif (isset($_GET['type'])) {
                    $sidebar_doc_type = $_GET['type'];
                }
            }
            $is_doc_page = in_array($current_page, ['documents.php', 'view_document.php', 'create_document.php']);
            ?>
            <a href="documents.php" class="<?php echo $current_page === 'documents.php' && empty($_GET['type']) ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> All Documents
            </a>
            <a href="documents.php?type=invoice" class="<?php echo ($is_doc_page && $sidebar_doc_type === 'invoice') ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
            <a href="documents.php?type=quotation" class="<?php echo ($is_doc_page && $sidebar_doc_type === 'quotation') ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice-dollar"></i> Quotations
            </a>
            <a href="documents.php?type=receipt" class="<?php echo ($is_doc_page && $sidebar_doc_type === 'receipt') ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Receipts
            </a>
            
            <?php if (isAdmin()): ?>
            <div class="menu-section">
                <span class="menu-section-title">Administration</span>
            </div>
            <a href="users.php" class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="backup.php" class="<?php echo $current_page === 'backup.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i> Backup
            </a>
            <a href="email_settings.php" class="<?php echo $current_page === 'email_settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Email Settings
            </a>
            <?php endif; ?>
            
            <div class="menu-section">
                <span class="menu-section-title">Account</span>
            </div>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php endif; ?>
    </nav>
</aside>
