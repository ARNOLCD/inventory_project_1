<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();
$conn = getDBConnection();

echo "<h2>Removing Duplicate Products</h2>";

// Get all products
$all_products_result = $conn->query("SELECT id, name, category, serial_number, price, quantity FROM products ORDER BY name, id");

$all_products = [];
while ($row = $all_products_result->fetch_assoc()) {
    $all_products[] = $row;
}

echo "<h3>All Products (" . count($all_products) . " total):</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Serial Number</th><th>Price</th><th>Stock</th></tr>";

foreach ($all_products as $product) {
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

// Find duplicates by name (case-insensitive)
$name_counts = [];
foreach ($all_products as $product) {
    $name = strtolower(trim($product['name']));
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
    echo "<p style='color: green; font-weight: bold;'>✅ No duplicate products found!</p>";
    exit;
}

echo "<h3>Duplicate Products Found:</h3>";
$total_removed = 0;
$removed_ids = [];

foreach ($duplicates as $name => $products) {
    echo "<h4>Duplicate: " . htmlspecialchars($products[0]['name']) . " (" . count($products) . " copies)</h4>";
    echo "<table border='1' style='border-collapse: collapse; padding: 10px; margin-bottom: 20px;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Serial Number</th><th>Price</th><th>Stock</th><th>Action</th></tr>";
    
    // Keep the first one, remove the rest
    $keep_first = true;
    foreach ($products as $product) {
        echo "<tr>";
        echo "<td>" . $product['id'] . "</td>";
        echo "<td>" . htmlspecialchars($product['name']) . "</td>";
        echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
        echo "<td>K" . number_format($product['price'], 2) . "</td>";
        echo "<td>" . $product['quantity'] . "</td>";
        
        if ($keep_first) {
            echo "<td style='color: green; font-weight: bold;'>KEEP</td>";
            $keep_first = false;
        } else {
            echo "<td style='color: red; font-weight: bold;'>REMOVE</td>";
            
            // Remove the duplicate product
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $product['id']);
            
            if ($stmt->execute()) {
                $total_removed++;
                $removed_ids[] = $product['id'];
                echo "<tr><td colspan='6' style='color: green;'>✅ Removed product ID: " . $product['id'] . "</td></tr>";
            } else {
                echo "<tr><td colspan='6' style='color: red;'>❌ Error removing product: " . $stmt->error . "</td></tr>";
            }
            $stmt->close();
        }
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Summary:</h3>";
echo "<p><strong>Total duplicates found:</strong> " . count($duplicates) . " product names</p>";
echo "<p><strong>Total products removed:</strong> " . $total_removed . "</p>";
echo "<p><strong>Removed IDs:</strong> " . implode(', ', $removed_ids) . "</p>";

echo "<h3>Updated Products List:</h3>";
$updated_result = $conn->query("SELECT id, name, category, price, quantity FROM products ORDER BY name");

echo "<table border='1' style='border-collapse: collapse; padding: 10px; margin-top: 20px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th></tr>";

$product_count = 0;
while ($row = $updated_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['category']) . "</td>";
    echo "<td>K" . number_format($row['price'], 2) . "</td>";
    echo "<td>" . $row['quantity'] . "</td>";
    echo "</tr>";
    $product_count++;
}

echo "</table>";
echo "<p><strong>Total products remaining:</strong> " . $product_count . "</p>";

if ($total_removed > 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ Successfully removed " . $total_removed . " duplicate products!</p>";
} else {
    echo "<p style='color: orange;'>No products were removed.</p>";
}

$conn->close();
?>
