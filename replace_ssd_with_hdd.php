<?php
require_once 'config/database.php';
$conn = getDBConnection();

echo "<h2>Replacing SSD with HDD</h2>";

// Find SSD products
$ssd_result = $conn->query("SELECT id, name, category, serial_number, price, quantity FROM products WHERE name LIKE '%SSD%' OR name LIKE '%ssd%' ORDER BY id LIMIT 2");

$ssd_products = [];
while ($row = $ssd_result->fetch_assoc()) {
    $ssd_products[] = $row;
}

if (count($ssd_products) < 2) {
    echo "<p style='color: red;'>Error: Less than 2 SSD products found. Found: " . count($ssd_products) . "</p>";
    exit;
}

// Replace the second SSD with HDD
$ssd_to_replace = $ssd_products[1];
echo "<h3>Replacing this SSD:</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th></tr>";
echo "<tr>";
echo "<td>" . $ssd_to_replace['id'] . "</td>";
echo "<td>" . htmlspecialchars($ssd_to_replace['name']) . "</td>";
echo "<td>" . htmlspecialchars($ssd_to_replace['category']) . "</td>";
echo "<td>K" . number_format($ssd_to_replace['price'], 2) . "</td>";
echo "<td>" . $ssd_to_replace['quantity'] . "</td>";
echo "</tr>";
echo "</table>";

// Create HDD replacement
$hdd_name = str_replace(['SSD', 'ssd'], ['HDD', 'hdd'], $ssd_to_replace['name']);
$hdd_serial = 'HDD-' . date('Y') . '-' . str_pad($ssd_to_replace['id'], 4, '0', STR_PAD_LEFT);

// HDD is typically cheaper than SSD, let's reduce price by 30%
$hdd_price = $ssd_to_replace['price'] * 0.7;

echo "<h3>New HDD Product:</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>Name</th><th>Category</th><th>Serial Number</th><th>Price</th><th>Stock</th></tr>";
echo "<tr>";
echo "<td>" . htmlspecialchars($hdd_name) . "</td>";
echo "<td>" . htmlspecialchars($ssd_to_replace['category']) . "</td>";
echo "<td>" . htmlspecialchars($hdd_serial) . "</td>";
echo "<td>K" . number_format($hdd_price, 2) . "</td>";
echo "<td>" . $ssd_to_replace['quantity'] . "</td>";
echo "</tr>";
echo "</table>";

// Update the product
$stmt = $conn->prepare("UPDATE products SET name = ?, serial_number = ?, price = ? WHERE id = ?");
$stmt->bind_param("ssdi", $hdd_name, $hdd_serial, $hdd_price, $ssd_to_replace['id']);

if ($stmt->execute()) {
    echo "<p style='color: green; font-weight: bold;'>✅ Successfully replaced SSD with HDD!</p>";
    echo "<p>Updated product ID: " . $ssd_to_replace['id'] . "</p>";
    echo "<p>New name: " . htmlspecialchars($hdd_name) . "</p>";
    echo "<p>New price: K" . number_format($hdd_price, 2) . " (30% cheaper than SSD)</p>";
} else {
    echo "<p style='color: red;'>❌ Error updating product: " . $stmt->error . "</p>";
}

echo "<h3>Updated Storage Products:</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px; margin-top: 20px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th></tr>";

$result = $conn->query("SELECT id, name, category, price, quantity FROM products WHERE category LIKE '%Storage%' OR name LIKE '%SSD%' OR name LIKE '%HDD%' OR name LIKE '%ssd%' OR name LIKE '%hdd%' ORDER BY id");
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

$stmt->close();
$conn->close();
?>
