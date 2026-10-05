<?php
// Database configuration for Sims-Tech Zambia Inventory Management System
// Schema is defined in mysql/database.sql — import it via phpMyAdmin or CLI before first use.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'actech_inventory');

// Create connection
$conn = new mysqli(\DB_HOST, \DB_USER, \DB_PASS, \DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure users.role accepts all roles (otherwise MySQL silently stores an empty role)
function ensureUserRoles($conn) {
    $role_column = $conn->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch_assoc();
    foreach (['customer', 'technician', 'sales'] as $role) {
        if ($role_column && strpos($role_column['Type'], "'$role'") === false) {
            $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'employee', 'technician', 'sales', 'customer') DEFAULT 'employee'");
            return;
        }
    }
}

// Ensure repairs table supports customer repair requests (pending approval / rejected)
function ensureRepairRequestSchema($conn) {
    $status_column = $conn->query("SHOW COLUMNS FROM repairs LIKE 'status'")->fetch_assoc();
    if ($status_column && strpos($status_column['Type'], "'pending_approval'") === false) {
        $conn->query("ALTER TABLE repairs MODIFY COLUMN status ENUM('pending_approval', 'booked', 'item_received', 'in_progress', 'completed', 'in_transit', 'delivered', 'cancelled', 'rejected') DEFAULT 'booked'");
    }
    $conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS reviewed_by INT DEFAULT NULL");
    $conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS reviewed_date DATETIME DEFAULT NULL");
}

// Function to get low stock products
function getLowStockProducts($conn) {
    $sql = "SELECT * FROM products WHERE quantity <= min_stock_level AND status = 'active'";
    return $conn->query($sql);
}

// Function to generate invoice number
function generateInvoiceNumber($conn) {
    $prefix = 'INV-' . date('Ymd') . '-';
    $result = $conn->query("SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = CURDATE()");
    $row = $result->fetch_assoc();
    return $prefix . str_pad($row['count'] + 1, 4, '0', \STR_PAD_LEFT);
}

// Function to generate document number
function generateDocumentNumber($conn, $type) {
    $prefixes = [
        'invoice' => 'INV',
        'quotation' => 'QUO',
        'receipt' => 'REC'
    ];
    $prefix = $prefixes[$type] . '-' . date('Ymd') . '-';
    $result = $conn->query("SELECT COUNT(*) as count FROM documents WHERE document_type = '$type' AND DATE(created_at) = CURDATE()");
    $row = $result->fetch_assoc();
    return $prefix . str_pad($row['count'] + 1, 4, '0', \STR_PAD_LEFT);
}
?>
