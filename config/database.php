<?php
// Database configuration for AC-TECHNOLOGY Inventory Management System
// Schema is defined in mysql/database.sql — import it via phpMyAdmin or CLI before first use.
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'actech_inventory');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
    return $prefix . str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
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
    return $prefix . str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
}
?>
