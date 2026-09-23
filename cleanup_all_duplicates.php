<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();
$conn = getDBConnection();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Cleanup - Remove All Duplicates</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; max-width: 900px; margin: 20px auto; background: #f7fafc; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a365d; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        h2 { color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; margin-top: 30px; }
        .success { color: #48bb78; }
        .error { color: #e53e3e; }
        .info { color: #4299e1; }
        .summary { background: #f0fff4; border: 2px solid #48bb78; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; font-size: 14px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #48bb78; color: white; }
        .btn-warning { background: #ed8936; color: white; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #e2e8f0; }
        th { background: #f7fafc; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Database Cleanup - Remove All Duplicates</h1>
    
    <?php
    $cats_removed = 0;
    $prods_removed = 0;
    $servs_removed = 0;
    
    // ==========================================
    // STEP 1: Remove Duplicate Categories
    // ==========================================
    echo "<h2>Step 1: Removing Duplicate Categories</h2>";
    
    $dup_cats = $conn->query("
        SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids 
        FROM categories 
        GROUP BY LOWER(TRIM(name)) 
        HAVING cnt > 1
    ");
    
    if ($dup_cats && $dup_cats->num_rows > 0) {
        echo "<table><tr><th>Category Name</th><th>Duplicates Found</th><th>Action</th></tr>";
        while ($row = $dup_cats->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids);
            $removed_count = count($ids);
            
            foreach ($ids as $id) {
                // Update products to use the kept category
                $conn->query("UPDATE products SET category_id = $keep_id WHERE category_id = " . intval($id));
                // Delete duplicate category
                $conn->query("DELETE FROM categories WHERE id = " . intval($id));
                $cats_removed++;
            }
            echo "<tr><td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
            echo "<td>" . $row['cnt'] . " (removed $removed_count)</td>";
            echo "<td class='success'>✅ Kept ID: $keep_id</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✅ No duplicate categories found!</p>";
    }
    
    // ==========================================
    // STEP 2: Remove Duplicate Products
    // ==========================================
    echo "<h2>Step 2: Removing Duplicate Products</h2>";
    
    $dup_prods = $conn->query("
        SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids 
        FROM products 
        GROUP BY LOWER(TRIM(name)) 
        HAVING cnt > 1
    ");
    
    if ($dup_prods && $dup_prods->num_rows > 0) {
        echo "<table><tr><th>Product Name</th><th>Duplicates Found</th><th>Action</th></tr>";
        while ($row = $dup_prods->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids);
            $removed_count = count($ids);
            
            foreach ($ids as $id) {
                $conn->query("DELETE FROM products WHERE id = " . intval($id));
                $prods_removed++;
            }
            echo "<tr><td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
            echo "<td>" . $row['cnt'] . " (removed $removed_count)</td>";
            echo "<td class='success'>✅ Kept ID: $keep_id</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✅ No duplicate products found!</p>";
    }
    
    // ==========================================
    // STEP 3: Remove Duplicate Services
    // ==========================================
    echo "<h2>Step 3: Removing Duplicate Services</h2>";
    
    $dup_servs = $conn->query("
        SELECT name, COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids 
        FROM services 
        GROUP BY LOWER(TRIM(name)) 
        HAVING cnt > 1
    ");
    
    if ($dup_servs && $dup_servs->num_rows > 0) {
        echo "<table><tr><th>Service Name</th><th>Duplicates Found</th><th>Action</th></tr>";
        while ($row = $dup_servs->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keep_id = array_shift($ids);
            $removed_count = count($ids);
            
            foreach ($ids as $id) {
                $conn->query("DELETE FROM services WHERE id = " . intval($id));
                $servs_removed++;
            }
            echo "<tr><td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
            echo "<td>" . $row['cnt'] . " (removed $removed_count)</td>";
            echo "<td class='success'>✅ Kept ID: $keep_id</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='success'>✅ No duplicate services found!</p>";
    }
    
    // ==========================================
    // SUMMARY
    // ==========================================
    ?>
    
    <div class="summary">
        <h2 style="margin-top: 0; color: #276749;">✅ Cleanup Complete!</h2>
        <ul>
            <li><strong>Categories removed:</strong> <?php echo $cats_removed; ?></li>
            <li><strong>Products removed:</strong> <?php echo $prods_removed; ?></li>
            <li><strong>Services removed:</strong> <?php echo $servs_removed; ?></li>
            <li><strong>Total duplicates removed:</strong> <?php echo $cats_removed + $prods_removed + $servs_removed; ?></li>
        </ul>
    </div>
    
    <h2>Current Database Status</h2>
    <?php
    $cat_count = $conn->query("SELECT COUNT(*) as cnt FROM categories")->fetch_assoc()['cnt'];
    $prod_count = $conn->query("SELECT COUNT(*) as cnt FROM products")->fetch_assoc()['cnt'];
    $serv_count = $conn->query("SELECT COUNT(*) as cnt FROM services")->fetch_assoc()['cnt'];
    ?>
    <table>
        <tr><th>Table</th><th>Total Records</th><th>Status</th></tr>
        <tr><td>Categories</td><td><?php echo $cat_count; ?></td><td class="success">✅ Clean</td></tr>
        <tr><td>Products</td><td><?php echo $prod_count; ?></td><td class="success">✅ Clean</td></tr>
        <tr><td>Services</td><td><?php echo $serv_count; ?></td><td class="success">✅ Clean</td></tr>
    </table>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="index.php" class="btn btn-primary">🏠 Homepage</a>
        <a href="products.php" class="btn btn-success">📦 Products</a>
        <a href="categories.php" class="btn btn-warning">📁 Categories</a>
        <a href="services.php" class="btn btn-primary">⚙️ Services</a>
    </div>
</div>
</body>
</html>
