<?php
// Additional access control to prevent unauthorized access
// This file provides extra security layers beyond the basic session checks

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

// Customers may ONLY access the pages below (allowlist). Every other page - including any
// inventory, sales, document, admin, diagnostic or AJAX page added in the future - is denied.
// Customers can: book repairs, shop online/checkout, pay for repairs and track their repairs.
$customer_allowed_pages = [
    'customer_dashboard.php',   // Own repairs and orders
    'customer_book_repair.php', // Book a repair
    'repair_payment.php',       // Pay for own repairs
    'products.php',             // Online shop (customer view only)
    'checkout.php',             // Complete online purchase
    'receipt.php',              // View/print own payment receipts
    'change_password.php',
    'login.php',
    'logout.php',
    'signup.php',
    'reset_password.php',
    'index.php',
    'contact_submit.php'
];

$current_page = basename($_SERVER['PHP_SELF']);

// Users created by an admin must choose their own password before using the system
if (!empty($_SESSION['user_id']) && !empty($_SESSION['must_change_password']) && !in_array($current_page, ['change_password.php', 'logout.php'], true)) {
    if (basename(dirname($_SERVER['PHP_SELF'])) === 'ajax') {
        header('HTTP/1.0 403 Forbidden');
        exit('Password change required');
    }
    header('Location: change_password.php');
    exit();
}

$is_staff_role = isset($_SESSION['role']) && in_array($_SESSION['role'], STAFF_ROLES, true);

// Any logged-in user without a staff role is restricted to the customer pages
if (isset($_SESSION['user_id']) && !$is_staff_role) {
    if (!in_array($current_page, $customer_allowed_pages, true)) {
        $in_subdir = basename(dirname($_SERVER['PHP_SELF'])) === 'ajax';
        if ($in_subdir) {
            header('HTTP/1.0 403 Forbidden');
            exit('Access denied');
        }
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

if ($is_staff_role) {
    if (in_array($current_page, $staff_blocked_pages)) {
        header('Location: dashboard.php');
        exit();
    }
}
?>