<?php
// Additional access control to prevent unauthorized access
// This file provides extra security layers beyond the basic session checks

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Block customer access to staff pages - additional protection
$customer_blocked_pages = [
    'dashboard.php',
    'categories.php',
    'services.php',
    'pos.php',
    'sales.php',
    'reports.php',
    'repairs.php',
    'documents.php',
    'document_templates.php',
    'create_document.php',
    'view_document.php',
    'users.php',
    'settings.php',
    'backup.php',
    'email_settings.php'
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
    'customer_book_repair.php'
];

if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'employee')) {
    if (in_array($current_page, $staff_blocked_pages)) {
        header('Location: dashboard.php');
        exit();
    }
}
?>