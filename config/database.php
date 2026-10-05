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

// Buffer page output so queued emails can be sent after the page has been delivered
if (PHP_SAPI !== 'cli' && ob_get_level() <= 1) {
    ob_start();
}

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/migrations.php';
runMigrations($conn);

// Repair status categories in workflow order, with display labels
const REPAIR_STATUSES = [
    'pending_approval' => 'Pending Approval',
    'booked' => 'Booked',
    'item_received' => 'Item Received',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'in_transit' => 'In Transit',
    'delivered' => 'Delivered',
    'rejected' => 'Declined',
    'cancelled' => 'Cancelled',
];

// Icon and colour per repair category
const REPAIR_STATUS_STYLES = [
    'pending_approval' => ['fa-hourglass-half', '#718096'],
    'booked' => ['fa-clipboard-list', '#3182ce'],
    'item_received' => ['fa-box', '#4299e1'],
    'in_progress' => ['fa-wrench', '#dd6b20'],
    'completed' => ['fa-check-circle', '#38a169'],
    'in_transit' => ['fa-shipping-fast', '#9f7aea'],
    'delivered' => ['fa-hand-holding', '#805ad5'],
    'rejected' => ['fa-ban', '#c53030'],
    'cancelled' => ['fa-times-circle', '#e53e3e'],
];

// Number of repairs in each category (single query)
function repairStatusCounts($conn) {
    $counts = array_fill_keys(array_keys(REPAIR_STATUSES), 0);
    $result = $conn->query("SELECT status, COUNT(*) AS c FROM repairs GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $counts[$row['status']] = (int)$row['c'];
    }
    return $counts;
}

function repairStatusLabel($status) {
    return REPAIR_STATUSES[$status] ?? ucfirst(str_replace('_', ' ', (string)$status));
}

// Generate repair ticket number
function generateTicketNumber($conn) {
    $row = $conn->query("SELECT MAX(id) as max_id FROM repairs")->fetch_assoc();
    return 'REP' . date('Ymd') . str_pad(($row['max_id'] ?? 0) + 1, 4, '0', \STR_PAD_LEFT);
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
