<?php
session_start();

// Internal (staff) roles - all of these can use the inventory system and handle repair requests
define('STAFF_ROLES', ['admin', 'employee', 'technician', 'sales']);
define('USER_ROLES', array_merge(STAFF_ROLES, ['customer']));
define('ROLE_LABELS', [
    'admin' => 'Admin',
    'employee' => 'Employee',
    'technician' => 'Technician',
    'sales' => 'Sales Person',
    'customer' => 'Customer',
]);

// Include additional access control
require_once __DIR__ . '/access_control.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user has an internal staff role (admin, employee, technician, sales)
function isStaff() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], STAFF_ROLES, true);
}

// Check if user is customer (fail-closed: any logged-in user who is not staff is a customer)
function isCustomer() {
    return isLoggedIn() && !isStaff();
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect if customer (for staff/admin only pages)
function requireStaff() {
    requireLogin();
    if (!isStaff()) {
        header('Location: customer_dashboard.php');
        exit();
    }
}

// Redirect if not admin (for admin only pages)
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

// Get current user info
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}

// Set user session
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['must_change_password'] = !empty($user['must_change_password']);
}

// Landing page for the current user
function homePage() {
    return isStaff() ? 'dashboard.php' : 'customer_dashboard.php';
}

function roleLabel($role) {
    return ROLE_LABELS[$role] ?? ucfirst((string)$role);
}

// Destroy session
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
