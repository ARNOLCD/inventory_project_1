<?php
require_once 'config/database.php';
$conn = getDBConnection();

// Auto-run cleanup if requested
if (isset($_GET['run']) && $_GET['run'] === 'cleanup') {
    runCleanup($conn);
    exit;
}

function runCleanup($conn) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Cleanup Results</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:20px;max-width:900px;margin:20px auto;}h1{color:#1a365d;}h2{color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:5px;}.success{color:#48bb78;}.error{color:#e53e3e;}</style>";
    echo "</head><body>";
    
    echo "<h1>🔧 Database Cleanup Results</h1>";
    
    // Remove duplicate categories
    echo "<h2>Removing Duplicate Categories</h2>";
    $dup_cats = $conn->query("SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids FROM categories GROUP BY LOWER(name) HAVING cnt > 1");
    $cats_removed = 0;
    if ($dup_cats && $dup_cats->num_rows > 0) {
        while ($row = $dup_cats->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids);
            foreach ($ids as $id) {
                $conn->query("UPDATE products SET category_id = $keep_id WHERE category_id = " . intval($id));
                $conn->query("DELETE FROM categories WHERE id = " . intval($id));
                $cats_removed++;
            }
            echo "<p class='success'>✅ Removed " . count($ids) . " duplicate(s) for: <strong>" . htmlspecialchars($row['name']) . "</strong></p>";
        }
    }
    echo "<p><strong>Categories removed: $cats_removed</strong></p>";
    
    // Remove duplicate products
    echo "<h2>Removing Duplicate Products</h2>";
    $dup_prods = $conn->query("SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids FROM products GROUP BY LOWER(name) HAVING cnt > 1");
    $prods_removed = 0;
    if ($dup_prods && $dup_prods->num_rows > 0) {
        while ($row = $dup_prods->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids);
            foreach ($ids as $id) {
                $conn->query("DELETE FROM products WHERE id = " . intval($id));
                $prods_removed++;
            }
            echo "<p class='success'>✅ Removed " . count($ids) . " duplicate(s) for: <strong>" . htmlspecialchars($row['name']) . "</strong></p>";
        }
    }
    echo "<p><strong>Products removed: $prods_removed</strong></p>";
    
    // Remove duplicate services
    echo "<h2>Removing Duplicate Services</h2>";
    $dup_servs = $conn->query("SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids FROM services GROUP BY LOWER(name) HAVING cnt > 1");
    $servs_removed = 0;
    if ($dup_servs && $dup_servs->num_rows > 0) {
        while ($row = $dup_servs->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids);
            foreach ($ids as $id) {
                $conn->query("DELETE FROM services WHERE id = " . intval($id));
                $servs_removed++;
            }
            echo "<p class='success'>✅ Removed " . count($ids) . " duplicate(s) for: <strong>" . htmlspecialchars($row['name']) . "</strong></p>";
        }
    }
    echo "<p><strong>Services removed: $servs_removed</strong></p>";
    
    echo "<h2>Summary</h2>";
    echo "<div style='background:#f0fff4;border:2px solid #48bb78;padding:20px;border-radius:10px;'>";
    echo "<h3 style='color:#276749;margin-top:0;'>✅ Cleanup Complete!</h3>";
    echo "<ul><li>Categories removed: $cats_removed</li><li>Products removed: $prods_removed</li><li>Services removed: $servs_removed</li></ul>";
    echo "</div>";
    
    echo "<div style='text-align:center;margin:30px 0;'>";
    echo "<a href='index.php' style='padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;margin:5px;'>🏠 Homepage</a>";
    echo "<a href='products.php' style='padding:10px 20px;background:#48bb78;color:white;text-decoration:none;border-radius:5px;margin:5px;'>📦 Products</a>";
    echo "<a href='categories.php' style='padding:10px 20px;background:#ed8936;color:white;text-decoration:none;border-radius:5px;margin:5px;'>📁 Categories</a>";
    echo "</div></body></html>";
}

echo "<h1>🔧 Fixing All System Issues</h1>";

// ==========================================
// STEP 1: Remove Duplicate Products
// ==========================================
echo "<h2>Step 1: Removing Duplicate Products</h2>";

$duplicate_products = $conn->query("
    SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids 
    FROM products 
    GROUP BY LOWER(name) 
    HAVING cnt > 1
");

$products_removed = 0;
if ($duplicate_products && $duplicate_products->num_rows > 0) {
    while ($row = $duplicate_products->fetch_assoc()) {
        $ids = explode(',', $row['ids']);
        $keep_id = array_shift($ids); // Keep first one
        
        foreach ($ids as $id) {
            $conn->query("DELETE FROM products WHERE id = " . intval($id));
            $products_removed++;
        }
        echo "<p>✅ Removed " . count($ids) . " duplicate(s) for: <strong>" . htmlspecialchars($row['name']) . "</strong> (kept ID: $keep_id)</p>";
    }
} else {
    echo "<p style='color: green;'>✅ No duplicate products found!</p>";
}
echo "<p><strong>Total products removed: $products_removed</strong></p>";

// ==========================================
// STEP 2: Remove Duplicate Categories
// ==========================================
echo "<h2>Step 2: Removing Duplicate Categories</h2>";

$duplicate_categories = $conn->query("
    SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids 
    FROM categories 
    GROUP BY LOWER(name) 
    HAVING cnt > 1
");

$categories_removed = 0;
if ($duplicate_categories && $duplicate_categories->num_rows > 0) {
    while ($row = $duplicate_categories->fetch_assoc()) {
        $ids = explode(',', $row['ids']);
        $keep_id = array_shift($ids); // Keep first one
        
        foreach ($ids as $id) {
            // Update products to use the kept category
            $conn->query("UPDATE products SET category_id = $keep_id WHERE category_id = " . intval($id));
            // Delete duplicate category
            $conn->query("DELETE FROM categories WHERE id = " . intval($id));
            $categories_removed++;
        }
        echo "<p>✅ Removed " . count($ids) . " duplicate(s) for: <strong>" . htmlspecialchars($row['name']) . "</strong> (kept ID: $keep_id)</p>";
    }
} else {
    echo "<p style='color: green;'>✅ No duplicate categories found!</p>";
}
echo "<p><strong>Total categories removed: $categories_removed</strong></p>";

// ==========================================
// STEP 3: Remove Duplicate Services
// ==========================================
echo "<h2>Step 3: Removing Duplicate Services</h2>";

$duplicate_services = $conn->query("
    SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids 
    FROM services 
    GROUP BY LOWER(name) 
    HAVING cnt > 1
");

$services_removed = 0;
if ($duplicate_services && $duplicate_services->num_rows > 0) {
    while ($row = $duplicate_services->fetch_assoc()) {
        $ids = explode(',', $row['ids']);
        $keep_id = array_shift($ids); // Keep first one
        
        foreach ($ids as $id) {
            $conn->query("DELETE FROM services WHERE id = " . intval($id));
            $services_removed++;
        }
        echo "<p>✅ Removed " . count($ids) . " duplicate(s) for: <strong>" . htmlspecialchars($row['name']) . "</strong> (kept ID: $keep_id)</p>";
    }
} else {
    echo "<p style='color: green;'>✅ No duplicate services found!</p>";
}
echo "<p><strong>Total services removed: $services_removed</strong></p>";

// ==========================================
// SUMMARY
// ==========================================
echo "<h2>Summary</h2>";
echo "<div style='background: #f0fff4; border: 2px solid #48bb78; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3 style='color: #276749; margin-top: 0;'>✅ Cleanup Complete!</h3>";
echo "<ul>";
echo "<li><strong>Products removed:</strong> $products_removed</li>";
echo "<li><strong>Categories removed:</strong> $categories_removed</li>";
echo "<li><strong>Services removed:</strong> $services_removed</li>";
echo "</ul>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>🏠 View Homepage</a>";
echo "<a href='products.php' style='padding: 10px 20px; background: #48bb78; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>📦 View Products</a>";
echo "<a href='categories.php' style='padding: 10px 20px; background: #ed8936; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>📁 View Categories</a>";
echo "</div>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; max-width: 900px; margin: 20px auto; }
h1 { color: #1a365d; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
h2 { color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin-top: 30px; }
p { margin: 8px 0; }
</style>
