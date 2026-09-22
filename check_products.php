<?php
require_once 'config/database.php';
$conn = getDBConnection();

echo "<h3>Current SSD Products:</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Serial Number</th><th>Price</th><th>Stock</th></tr>";

$result = $conn->query("SELECT id, name, category, serial_number, price, quantity FROM products WHERE name LIKE '%SSD%' OR name LIKE '%ssd%' ORDER BY id");
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['category']) . "</td>";
    echo "<td>" . htmlspecialchars($row['serial_number']) . "</td>";
    echo "<td>K" . number_format($row['price'], 2) . "</td>";
    echo "<td>" . $row['quantity'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>All Storage Products:</h3>";
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

$conn->close();
?>
