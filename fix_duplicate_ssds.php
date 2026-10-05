<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();
$conn = getDBConnection();

echo "<h2>Finding and Replacing Duplicate SSDs</h2>";

// Find all SSD products
$ssd_result = $conn->query("SELECT id, name, category, serial_number, price, quantity FROM products WHERE name LIKE '%SSD%' OR name LIKE '%ssd%' ORDER BY name, id");

$ssd_products = [];
while ($row = $ssd_result->fetch_assoc()) {
    $ssd_products[] = $row;
}

echo "<h3>Current SSD Products (" . count($ssd_products) . " found):</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Serial Number</th><th>Price</th><th>Stock</th></tr>";

foreach ($ssd_products as $product) {
    echo "<tr>";
    echo "<td>" . $product['id'] . "</td>";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
    echo "<td>" . htmlspecialchars($product['category']) . "</td>";
    echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
    echo "<td>K" . number_format($product['price'], 2) . "</td>";
    echo "<td>" . $product['quantity'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Find duplicates by name
$name_counts = [];
foreach ($ssd_products as $product) {
    $name = strtolower($product['name']);
    if (!isset($name_counts[$name])) {
        $name_counts[$name] = [];
    }
    $name_counts[$name][] = $product;
}

$duplicates = [];
foreach ($name_counts as $name => $products) {
    if (count($products) > 1) {
        $duplicates[$name] = $products;
    }
}

if (empty($duplicates)) {
    echo "<p style='color: green;'>No duplicate SSDs found.</p>";
    exit;
}

echo "<h3>Duplicate SSDs Found:</h3>";
foreach ($duplicates as $name => $products) {
    echo "<h4>Duplicate: " . htmlspecialchars($name) . " (" . count($products) . " copies)</h4>";
    echo "<table border='1' style='border-collapse: collapse; padding: 10px; margin-bottom: 20px;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Serial Number</th><th>Price</th><th>Stock</th><th>Action</th></tr>";
    
    // Keep the first one, replace the rest with HDD
    $keep_first = true;
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>" . $product['id'] . "</td>";
        echo "<td>" . htmlspecialchars($product['name']) . "</td>";
        echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
        echo "<td>K" . number_format($product['price'], 2) . "</td>";
        echo "<td>" . $product['quantity'] . "</td>";
        
        if ($keep_first) {
            echo "<td style='color: green; font-weight: bold;'>KEEP (SSD)</td>";
            $keep_first = false;
        } else {
            echo "<td style='color: blue; font-weight: bold;'>REPLACE with HDD</td>";
            
            // Replace this duplicate with HDD
            $hdd_name = str_replace(['SSD', 'ssd'], ['HDD', 'hdd'], $product['name']);
            $hdd_serial = 'HDD-' . date('Y') . '-' . str_pad($product['id'], 4, '0', STR_PAD_LEFT);
            $hdd_price = $product['price'] * 0.7; // 30% cheaper
            
            $stmt = $conn->prepare("UPDATE products SET name = ?, serial_number = ?, price = ? WHERE id = ?");
            $stmt->bind_param("ssdi", $hdd_name, $hdd_serial, $hdd_price, $product['id']);
            
            if ($stmt->execute()) {
                echo "<tr><td colspan='6' style='color: green;'>✅ Replaced with: " . htmlspecialchars($hdd_name) . " (K" . number_format($hdd_price, 2) . ")</td></tr>";
            } else {
                echo "<tr><td colspan='6' style='color: red;'>❌ Error: " . $stmt->error . "</td></tr>";
            }
            $stmt->close();
        }
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Updated Storage Products:</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px; margin-top: 20px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th></tr>";

$result = $conn->query("SELECT id, name, category, price, quantity FROM products WHERE category LIKE '%Storage%' OR name LIKE '%SSD%' OR name LIKE '%HDD%' OR name LIKE '%ssd%' OR name LIKE '%hdd%' ORDER BY name");
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['category']) . "</td>";
    echo "<td>K" . number_format($row['price'], 2) . "</td>";
    echo "<td>" . $row['quantity'] . "</td>";
    echo "</tr>";
}

echo "</table>";

$conn->close();
?>
