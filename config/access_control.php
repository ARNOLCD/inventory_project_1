<?php
// Additional access control to prevent unauthorized access
// This file provides extra security layers beyond the basic session checks

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Block customer access to staff pages - additional protection
// Customers CAN access: signup.php (create account), products.php (shopping), customer_book_repair.php (booking), customer_dashboard.php, checkout.php, repair_payment.php
// Customers CANNOT access: sales reports, invoices, inventory management, POS, repair solutions, etc.
$customer_blocked_pages = [
    'dashboard.php',        // Main staff dashboard
    'categories.php',       // Category management
    'services.php',         // Service management
    'pos.php',             // Point of sale (staff only)
    'sales.php',           // Sales history and reports (staff only)
    'reports.php',         // Business reports and analytics (staff only)
    'repairs.php',         // Staff repair management (staff only)
    'repair_solutions.php', // Technician solution documentation
    'documents.php',       // Document management (invoices, etc.)
    'document_templates.php', // Document templates
    'create_document.php', // Create documents (invoices, etc.)
    'view_document.php',   // View documents (invoices, etc.)
    'users.php',           // User management
    'settings.php',        // System settings
    'backup.php',          // Database backup
    'email_settings.php'   // Email configuration
];

$current_page = basename($_SERVER['PHP_SELF']);

if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
    if (in_array($current_page, $customer_blocked_pages)) {
        header('Location: customer_dashboard.php');
        exit();
    }
}

// Block staff/admin access to customer-only pages
$staff_blocked_pages = [
    'customer_dashboard.php',
    'customer_book_repair.php',
    'checkout.php',
    'repair_payment.php'
];

// Repair solutions is staff-only (no customer access needed)

if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'employee')) {
    if (in_array($current_page, $staff_blocked_pages)) {
        header('Location: dashboard.php');
        exit();
    }
}
?>