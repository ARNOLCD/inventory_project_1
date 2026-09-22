<?php
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is customer
function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

// Check if user is employee or admin
function isStaff() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'employee');
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
    if (isCustomer()) {
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
}

// Destroy session
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
