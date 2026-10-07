<?php
session_start();

// Idle timeout: users are logged out after 5 minutes of inactivity. This server-side check
// runs on every page that loads session.php; idleLogoutScript() provides the client-side part.
define('IDLE_TIMEOUT', 300);

if (isset($_SESSION['user_id'], $_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > IDLE_TIMEOUT) {
    session_unset();
    session_destroy();
    if (basename(dirname($_SERVER['PHP_SELF'] ?? '')) === 'ajax') {
        header('HTTP/1.0 401 Unauthorized');
        exit('Session expired');
    }
    header('Location: ' . (function_exists('appUrl') ? appUrl() . '/login.php' : 'login.php') . '?timeout=1');
    exit();
}
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

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

// Client-side half of the idle timeout: redirects to logout.php after 5 minutes without
// mouse/keyboard activity. Renders nothing for visitors who are not logged in.
function idleLogoutScript() {
    if (!isLoggedIn()) {
        return '';
    }
    $ms = IDLE_TIMEOUT * 1000;
    return "<script>(function(){if(window.__idleLogout)return;window.__idleLogout=1;var t;"
        . "function go(){window.location.href='logout.php?idle=1';}"
        . "function reset(){clearTimeout(t);t=setTimeout(go,$ms);}"
        . "['mousemove','mousedown','keydown','scroll','touchstart'].forEach(function(e){window.addEventListener(e,reset,{passive:true});});"
        . "reset();})();</script>";
}

// Prominent logout button rendered at the bottom of customer-facing pages.
// Renders nothing for staff or logged-out visitors.
function customerLogoutFooter() {
    if (!isLoggedIn() || !isCustomer()) {
        return '';
    }
    return '<div style="text-align: center; margin: 35px 0 15px;">'
        . '<a href="logout.php" class="btn" style="background: #e53e3e; color: #fff; padding: 14px 36px; '
        . 'font-size: 1rem; font-weight: 600; border-radius: 8px; text-decoration: none; display: inline-block; '
        . 'box-shadow: 0 3px 8px rgba(229,62,62,0.35);">'
        . '<i class="fas fa-sign-out-alt"></i> Logout</a></div>';
}

// Destroy session
function logout($idle = false) {
    session_unset();
    session_destroy();
    header('Location: login.php' . ($idle ? '?timeout=1' : ''));
    exit();
}
?>
