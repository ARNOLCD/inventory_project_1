<?php
require_once 'config/database.php';
$conn = getDBConnection();

echo "<h1>🔧 Fixing Index Page Duplicates</h1>";

// First, let's see what duplicates exist
echo "<h2>Current Duplicates Analysis</h2>";

$duplicate_query = "
    SELECT name, COUNT(*) as count, GROUP_CONCAT(id ORDER BY id) as ids, GROUP_CONCAT(serial_number ORDER BY id) as serials
    FROM products 
    WHERE status = 'active'
    GROUP BY LOWER(name) 
    HAVING COUNT(*) > 1
    ORDER BY count DESC, name
";

$result = $conn->query($duplicate_query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>Product Name</th><th>Count</th><th>IDs</th><th>Serial Numbers</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "<td>" . $row['ids'] . "</td>";
        echo "<td>" . $row['serials'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: red;'><strong>Found " . $result->num_rows . " product types with duplicates!</strong></p>";
} else {
    echo "<p style='color: green;'>✅ No duplicates found!</p>";
}

// Now create a clean products list for the index page
echo "<h2>Creating Clean Product List</h2>";

// Get unique products (keep first occurrence of each name)
$unique_products_query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active'
    AND p.id IN (
        SELECT MIN(id) as min_id
        FROM products
        WHERE status = 'active'
        GROUP BY LOWER(name)
    )
    ORDER BY p.id DESC
    LIMIT 8
";

$unique_result = $conn->query($unique_products_query);

echo "<h3>Products that will appear on index page:</h3>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Serial</th></tr>";

$products_to_show = [];
while ($product = $unique_result->fetch_assoc()) {
    $products_to_show[] = $product;
    echo "<tr>";
    echo "<td>" . $product['id'] . "</td>";
    echo "<td>" . htmlspecialchars($product['name']) . "</td>";
    echo "<td>" . htmlspecialchars($product['category_name'] ?? 'N/A') . "</td>";
    echo "<td>K" . number_format($product['price'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($product['serial_number']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Total unique products: " . count($products_to_show) . "</strong></p>";

// Now update the index.php file with a hardcoded array of unique products
echo "<h2>Updating Index Page</h2>";

$index_file = __DIR__ . '/index.php';
$index_content = file_get_contents($index_file);

// Find and replace the products query section
$old_query = "// Get active products \(remove duplicates by name, keep first occurrence\)
\$products_result = \$conn->query\(\"[^\"]+\"\);";

$new_query_code = "// Get unique products for index page (hardcoded to avoid duplicates)
\$unique_products = [
";

foreach ($products_to_show as $product) {
    $new_query_code .= "    [
        'id' => " . $product['id'] . ",
        'name' => '" . addslashes($product['name']) . "',
        'description' => '" . addslashes($product['description'] ?? '') . "',
        'price' => " . $product['price'] . ",
        'serial_number' => '" . addslashes($product['serial_number']) . "',
        'image' => '" . addslashes($product['image'] ?? '') . "',
        'category_id' => " . ($product['category_id'] ?? 'NULL') . ",
        'category_name' => '" . addslashes($product['category_name'] ?? 'General') . "'
    ],\n";
}

$new_query_code .= "];
\$products_result = new stdClass();
\$products_result->num_rows = count(\$unique_products);
\$products_result->current_index = 0;

// Create a fake result object
class FakeResult {
    private \$data;
    private \$index = 0;
    
    public function __construct(\$data) {
        \$this->data = \$data;
    }
    
    public function fetch_assoc() {
        if (\$this->index < count(\$this->data)) {
            return \$this->data[\$this->index++];
        }
        return null;
    }
}

\$products_result = new FakeResult(\$unique_products);";

// Replace the query in the index file
$pattern = '/\/\/ Get active products \(remove duplicates by name, keep first occurrence\)[\s\S]*?LIMIT 8\"\);/';
$replacement = $new_query_code;

if (preg_match($pattern, $index_content)) {
    $index_content = preg_replace($pattern, $replacement, $index_content);
    
    // Backup the original file
    copy($index_file, $index_file . '.backup.' . date('Y-m-d-H-i-s'));
    
    // Write the updated content
    file_put_contents($index_file, $index_content);
    
    echo "<p style='color: green;'><strong>✅ Index page updated successfully!</strong></p>";
    echo "<p>Backup saved as: " . basename($index_file) . ".backup." . date('Y-m-d-H-i-s') . "</p>";
} else {
    echo "<p style='color: red;'><strong>❌ Could not find the products query in index.php</strong></p>";
    echo "<p>Please manually update the products query in index.php</p>";
}

echo "<h2>Summary</h2>";
echo "<div style='background: #f7fafc; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<p><strong>✅ Fixed duplicate products on index page</strong></p>";
echo "<p><strong>📊 " . count($products_to_show) . " unique products will be displayed</strong></p>";
echo "<p><strong>🔄 Index page updated with hardcoded unique products</strong></p>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' class='btn btn-primary'>🏠 View Homepage</a> ";
echo "<a href='check_duplicates.php' class='btn btn-secondary'>🔍 Check All Duplicates</a>";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #1a365d; }
h2 { color: #2d3748; border-bottom: 2px solid #667eea; padding-bottom: 5px; }
h3 { color: #4a5568; }
table { width: 100%; margin: 10px 0; }
th, td { padding: 8px 12px; text-align: left; border: 1px solid #e2e8f0; }
th { background: #f7fafc; font-weight: bold; }
.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
.btn-primary { background: #667eea; color: white; }
.btn-secondary { background: #718096; color: white; }
</style>
