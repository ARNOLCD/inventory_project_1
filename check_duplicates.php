<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();
$conn = getDBConnection();

echo "<h1>🔍 Duplicate Products Analysis</h1>";
echo "<p>Checking for duplicate products in the database...</p>";

// Check for duplicate products by name (case-insensitive)
echo "<h2>1. Duplicate Products by Name</h2>";

$duplicate_query = "
    SELECT name, COUNT(*) as count, GROUP_CONCAT(id) as ids, GROUP_CONCAT(serial_number) as serials
    FROM products 
    GROUP BY LOWER(name) 
    HAVING COUNT(*) > 1
    ORDER BY count DESC, name
";

$result = $conn->query($duplicate_query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>Product Name</th><th>Count</th><th>IDs</th><th>Serial Numbers</th><th>Action</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . $row['ids'] . "</td>";
        echo "<td>" . $row['serials'] . "</td>";
        echo "<td><button onclick='removeDuplicates(\"" . htmlspecialchars($row['name']) . "\")' class='btn btn-danger btn-sm'>Remove Duplicates</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✅ No duplicate products found by name!</p>";
}

// Check for exact duplicate serial numbers
echo "<h2>2. Duplicate Serial Numbers</h2>";

$serial_duplicate_query = "
    SELECT serial_number, COUNT(*) as count, GROUP_CONCAT(name) as names, GROUP_CONCAT(id) as ids
    FROM products 
    WHERE serial_number != ''
    GROUP BY serial_number 
    HAVING COUNT(*) > 1
    ORDER BY count DESC, serial_number
";

$result = $conn->query($serial_duplicate_query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>Serial Number</th><th>Count</th><th>Product Names</th><th>IDs</th><th>Action</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['serial_number']) . "</strong></td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . $row['names'] . "</td>";
        echo "<td>" . $row['ids'] . "</td>";
        echo "<td><button onclick='fixSerialDuplicates(\"" . htmlspecialchars($row['serial_number']) . "\")' class='btn btn-warning btn-sm'>Fix Serial</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'>✅ No duplicate serial numbers found!</p>";
}

// Show total products count
echo "<h2>3. Current Products Summary</h2>";

$summary_query = "
    SELECT 
        COUNT(*) as total_products,
        COUNT(DISTINCT LOWER(name)) as unique_names,
        COUNT(DISTINCT serial_number) as unique_serials
    FROM products
";

$result = $conn->query($summary_query);
if ($row = $result->fetch_assoc()) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Metric</th><th>Count</th></tr>";
    echo "<tr><td>Total Products</td><td>" . $row['total_products'] . "</td></tr>";
    echo "<tr><td>Unique Product Names</td><td>" . $row['unique_names'] . "</td></tr>";
    echo "<tr><td>Unique Serial Numbers</td><td>" . $row['unique_serials'] . "</td></tr>";
    echo "</table>";
    
    $duplicates_count = $row['total_products'] - $row['unique_names'];
    if ($duplicates_count > 0) {
        echo "<p style='color: orange;'><strong>⚠️ Found $duplicates_count duplicate products by name</strong></p>";
    }
}

echo "<h2>4. Quick Actions</h2>";
echo "<div style='margin: 20px 0;'>";
echo "<button onclick='removeAllDuplicates()' class='btn btn-danger' style='margin: 5px;'>🗑️ Remove All Duplicates</button>";
echo "<button onclick='location.reload()' class='btn btn-secondary' style='margin: 5px;'>🔄 Refresh</button>";
echo "<button onclick='window.location.href=\"cleanup_duplicates.php\"' class='btn btn-primary' style='margin: 5px;'>🧹 Run Full Cleanup</button>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='products.php' class='btn btn-secondary'>← Back to Products</a>";
echo "</div>";
?>

<script>
function removeDuplicates(productName) {
    if (confirm('Are you sure you want to remove duplicates for "' + productName + '"?\n\nThis will keep the first product and delete all others with the same name.')) {
        window.location.href = 'cleanup_duplicates.php?product=' + encodeURIComponent(productName);
    }
}

function fixSerialDuplicates(serialNumber) {
    if (confirm('Are you sure you want to fix duplicate serial number "' + serialNumber + '"?\n\nThis will generate new serial numbers for duplicates.')) {
        window.location.href = 'cleanup_duplicates.php?serial=' + encodeURIComponent(serialNumber);
    }
}

function removeAllDuplicates() {
    if (confirm('Are you sure you want to remove ALL duplicate products?\n\nThis will keep the first product of each name and delete all duplicates.\n\nThis action cannot be undone!')) {
        window.location.href = 'cleanup_duplicates.php?all=true';
    }
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #1a365d; }
h2 { color: #2d3748; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
table { width: 100%; margin: 10px 0; }
th, td { padding: 8px 12px; text-align: left; border: 1px solid #e2e8f0; }
th { background: #f7fafc; font-weight: bold; }
.btn { padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 2px; }
.btn-danger { background: #e53e3e; color: white; }
.btn-warning { background: #d69e2e; color: white; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #718096; color: white; }
.btn-sm { padding: 4px 8px; font-size: 12px; }
</style>
