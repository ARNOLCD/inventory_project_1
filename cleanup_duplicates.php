<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();
$conn = getDBConnection();

echo "<h1>Database Cleanup - Removing Duplicates</h1>";

// Handle specific removal requests
if (isset($_GET['product'])) {
    $product_name = $_GET['product'];
    removeProductDuplicates($conn, $product_name);
    return;
}

if (isset($_GET['serial'])) {
    $serial_number = $_GET['serial'];
    fixSerialDuplicates($conn, $serial_number);
    return;
}

if (isset($_GET['all'])) {
    removeAllDuplicates($conn);
    return;
}

// ==========================================
// STEP 1: Remove Duplicate Categories
// ==========================================
echo "<h2>Step 1: Removing Duplicate Categories</h2>";

function removeProductDuplicates($conn, $product_name) {
    echo "<h1>Removing Duplicates for: " . htmlspecialchars($product_name) . "</h1>";
    
    // Get all products with this name (case-insensitive)
    $stmt = $conn->prepare("SELECT id, name, serial_number FROM products WHERE LOWER(name) = LOWER(?) ORDER BY id");
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $products = $stmt->get_result();
    
    if ($products->num_rows <= 1) {
        echo "<p style='color: green;'>✅ No duplicates found for this product.</p>";
        return;
    }
    
    $first_id = null;
    $removed_count = 0;
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Serial Number</th><th>Action</th></tr>";
    
    while ($product = $products->fetch_assoc()) {
        if ($first_id === null) {
            $first_id = $product['id'];
            echo "<tr style='color: green;'>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
            echo "<td>✅ Kept (Original)</td>";
            echo "</tr>";
        } else {
            // Delete duplicate
            $conn->query("DELETE FROM products WHERE id = {$product['id']}");
            echo "<tr style='color: red;'>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
            echo "<td>❌ Deleted</td>";
            echo "</tr>";
            $removed_count++;
        }
    }
    
    echo "</table>";
    echo "<p style='color: green;'><strong>✅ Removed $removed_count duplicate products.</strong></p>";
    
    echo "<div style='text-align: center; margin: 20px 0;'>";
    echo "<a href='check_duplicates.php' class='btn btn-primary'>← Back to Duplicate Analysis</a>";
    echo "</div>";
}

function fixSerialDuplicates($conn, $serial_number) {
    echo "<h1>Fixing Duplicate Serial Number: " . htmlspecialchars($serial_number) . "</h1>";
    
    // Get all products with this serial number
    $stmt = $conn->prepare("SELECT id, name, serial_number FROM products WHERE serial_number = ? ORDER BY id");
    $stmt->bind_param("s", $serial_number);
    $stmt->execute();
    $products = $stmt->get_result();
    
    if ($products->num_rows <= 1) {
        echo "<p style='color: green;'>✅ No duplicates found for this serial number.</p>";
        return;
    }
    
    $first_id = null;
    $fixed_count = 0;
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Old Serial</th><th>New Serial</th><th>Action</th></tr>";
    
    while ($product = $products->fetch_assoc()) {
        if ($first_id === null) {
            $first_id = $product['id'];
            echo "<tr style='color: green;'>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
            echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
            echo "<td>✅ Kept (Original)</td>";
            echo "</tr>";
        } else {
            // Generate new serial number
            $new_serial = $product['serial_number'] . '_' . $product['id'];
            $conn->query("UPDATE products SET serial_number = ? WHERE id = ?");
            $stmt_update = $conn->prepare("UPDATE products SET serial_number = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_serial, $product['id']);
            $stmt_update->execute();
            
            echo "<tr style='color: orange;'>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
            echo "<td>" . htmlspecialchars($new_serial) . "</td>";
            echo "<td>🔄 Updated</td>";
            echo "</tr>";
            $fixed_count++;
        }
    }
    
    echo "</table>";
    echo "<p style='color: orange;'><strong>🔄 Fixed $fixed_count duplicate serial numbers.</strong></p>";
    
    echo "<div style='text-align: center; margin: 20px 0;'>";
    echo "<a href='check_duplicates.php' class='btn btn-primary'>← Back to Duplicate Analysis</a>";
    echo "</div>";
}

function removeAllDuplicates($conn) {
    echo "<h1>Removing All Duplicate Products</h1>";
    
    // Get all duplicate products
    $duplicate_query = "
        SELECT name, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as ids
        FROM products 
        GROUP BY LOWER(name) 
        HAVING COUNT(*) > 1
        ORDER BY count DESC, name
    ";
    
    $result = $conn->query($duplicate_query);
    $total_removed = 0;
    
    if ($result && $result->num_rows > 0) {
        echo "<h2>Removing duplicates for " . $result->num_rows . " product types...</h2>";
        
        while ($row = $result->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids); // Keep first ID
            $remove_ids = $ids;
            
            echo "<p><strong>" . htmlspecialchars($row['name']) . ":</strong> Keep ID $keep_id, remove " . count($remove_ids) . " duplicates</p>";
            
            // Remove duplicates
            foreach ($remove_ids as $remove_id) {
                $conn->query("DELETE FROM products WHERE id = $remove_id");
                $total_removed++;
            }
        }
        
        echo "<h2 style='color: green;'>✅ Successfully removed $total_removed duplicate products!</h2>";
    } else {
        echo "<p style='color: green;'>✅ No duplicate products found!</p>";
    }
    
    echo "<div style='text-align: center; margin: 20px 0;'>";
    echo "<a href='check_duplicates.php' class='btn btn-primary'>← Back to Duplicate Analysis</a>";
    echo "</div>";
}

$cat_result = $conn->query("SELECT id, name FROM categories ORDER BY name, id");
$categories = [];
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row;
}

// Find duplicate categories by name (case-insensitive)
$cat_names = [];
$cat_duplicates = [];
foreach ($categories as $cat) {
    $name_lower = strtolower(trim($cat['name']));
    if (!isset($cat_names[$name_lower])) {
        $cat_names[$name_lower] = $cat; // Keep first one
    } else {
        $cat_duplicates[] = $cat; // Mark as duplicate
    }
}

echo "<p>Found " . count($cat_duplicates) . " duplicate categories to remove.</p>";

if (!empty($cat_duplicates)) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Action</th></tr>";
    
    foreach ($cat_duplicates as $dup) {
        // First, update products that use this duplicate category to use the original
        $original_name = strtolower(trim($dup['name']));
        $original_id = $cat_names[$original_name]['id'];
        
        // Update products to use original category
        $conn->query("UPDATE products SET category_id = $original_id WHERE category_id = {$dup['id']}");
        
        // Delete duplicate category
        $conn->query("DELETE FROM categories WHERE id = {$dup['id']}");
        
        echo "<tr>";
        echo "<td>{$dup['id']}</td>";
        echo "<td>" . htmlspecialchars($dup['name']) . "</td>";
        echo "<td style='color: green;'>✅ Removed (products moved to ID: $original_id)</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ==========================================
// STEP 2: Remove Duplicate Products
// ==========================================
echo "<h2>Step 2: Removing Duplicate Products</h2>";

$prod_result = $conn->query("SELECT id, name, category_id, price, quantity FROM products ORDER BY name, id");
$products = [];
while ($row = $prod_result->fetch_assoc()) {
    $products[] = $row;
}

// Find duplicate products by name (case-insensitive)
$prod_names = [];
$prod_duplicates = [];
foreach ($products as $prod) {
    $name_lower = strtolower(trim($prod['name']));
    if (!isset($prod_names[$name_lower])) {
        $prod_names[$name_lower] = $prod; // Keep first one
    } else {
        $prod_duplicates[] = $prod; // Mark as duplicate
    }
}

echo "<p>Found " . count($prod_duplicates) . " duplicate products to remove.</p>";

if (!empty($prod_duplicates)) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Price</th><th>Action</th></tr>";
    
    foreach ($prod_duplicates as $dup) {
        // Delete duplicate product
        $conn->query("DELETE FROM products WHERE id = {$dup['id']}");
        
        echo "<tr>";
        echo "<td>{$dup['id']}</td>";
        echo "<td>" . htmlspecialchars($dup['name']) . "</td>";
        echo "<td>K" . number_format($dup['price'], 2) . "</td>";
        echo "<td style='color: green;'>✅ Removed</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ==========================================
// STEP 3: Show Final Clean Data
// ==========================================
echo "<h2>Step 3: Final Clean Data</h2>";

echo "<h3>Categories (After Cleanup):</h3>";
$final_cats = $conn->query("SELECT id, name FROM categories ORDER BY name");
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>ID</th><th>Name</th></tr>";
while ($row = $final_cats->fetch_assoc()) {
    echo "<tr><td>{$row['id']}</td><td>" . htmlspecialchars($row['name']) . "</td></tr>";
}
echo "</table>";

echo "<h3>Products (After Cleanup):</h3>";
$final_prods = $conn->query("SELECT p.id, p.name, c.name as category, p.price FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name");
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th></tr>";
while ($row = $final_prods->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['category'] ?? 'N/A') . "</td>";
    echo "<td>K" . number_format($row['price'], 2) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2 style='color: green;'>✅ Cleanup Complete!</h2>";
echo "<p><a href='products.php'>Go to Products Page</a> | <a href='index.php'>Go to Homepage</a></p>";

$conn->close();
?>
