<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Only admin can add, edit, or delete products
    if (($action === 'add' || $action === 'edit' || $action === 'delete') && !isAdmin()) {
        $error = 'Access denied. Only administrators can modify products.';
    } elseif ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $serial_number = trim($_POST['serial_number']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $specifications = trim($_POST['specifications']);
        $category_id = $_POST['category_id'] ?: null;
        $price = floatval($_POST['price']);
        $cost_price = floatval($_POST['cost_price']);
        $quantity = intval($_POST['quantity']);
        $min_stock_level = intval($_POST['min_stock_level']);
        $status = $_POST['status'];
        
        // Handle image upload
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $image = uniqid() . '.' . $file_ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image);
            }
        }
        
        if ($action === 'add') {
            $sql = "INSERT INTO products (serial_number, name, description, specifications, category_id, price, cost_price, quantity, min_stock_level, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssiidisss", $serial_number, $name, $description, $specifications, $category_id, $price, $cost_price, $quantity, $min_stock_level, $image, $status);
            
            if ($stmt->execute()) {
                $message = 'Product added successfully!';
            } else {
                $error = 'Error adding product: ' . $conn->error;
            }
        } else {
            if ($image) {
                $sql = "UPDATE products SET serial_number=?, name=?, description=?, specifications=?, category_id=?, price=?, cost_price=?, quantity=?, min_stock_level=?, image=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssiidisssi", $serial_number, $name, $description, $specifications, $category_id, $price, $cost_price, $quantity, $min_stock_level, $image, $status, $id);
            } else {
                $sql = "UPDATE products SET serial_number=?, name=?, description=?, specifications=?, category_id=?, price=?, cost_price=?, quantity=?, min_stock_level=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssiidissi", $serial_number, $name, $description, $specifications, $category_id, $price, $cost_price, $quantity, $min_stock_level, $status, $id);
            }
            
            if ($stmt->execute()) {
                $message = 'Product updated successfully!';
            } else {
                $error = 'Error updating product: ' . $conn->error;
            }
        }
    } elseif ($action === 'delete' && isAdmin()) {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM products WHERE id = $id")) {
            $message = 'Product deleted successfully!';
        } else {
            $error = 'Error deleting product.';
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Build query
$where = "WHERE 1=1";
if ($filter === 'low_stock') {
    $where .= " AND p.quantity <= p.min_stock_level";
}
if ($category_filter) {
    $where .= " AND p.category_id = " . intval($category_filter);
}

// Get products
$products = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $where ORDER BY p.id DESC");

// Get unique categories for dropdown (remove duplicates)
$categories = $conn->query("
    SELECT c.* FROM categories c 
    WHERE c.id = (SELECT MIN(id) FROM categories c2 WHERE LOWER(TRIM(c2.name)) = LOWER(TRIM(c.name)))
    ORDER BY c.name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>Products</h1>
                        <p>Manage your inventory products</p>
                    </div>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-primary" data-modal="productModal" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                        <a href="products.php" class="btn <?php echo !$filter && !$category_filter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">All Products</a>
                        <a href="products.php?filter=low_stock" class="btn <?php echo $filter === 'low_stock' ? 'btn-danger' : 'btn-secondary'; ?> btn-sm">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                        </a>
                        <select onchange="window.location.href='products.php?category='+this.value" class="form-control" style="width: auto;">
                            <option value="">All Categories</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-box"></i> Products List</h3>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search products..." style="width: 250px;" onkeyup="searchTable()">
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="productsTable">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Serial No.</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($products->num_rows > 0): ?>
                                        <?php while ($product = $products->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($product['image']): ?>
                                                        <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="Product">
                                                    <?php else: ?>
                                                        <div style="width: 50px; height: 50px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-box" style="color: #a0aec0;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?php echo htmlspecialchars($product['serial_number']); ?></code></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                    <br><small class="text-secondary"><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                                <td><strong>K<?php echo number_format($product['price'], 2); ?></strong></td>
                                                <td>
                                                    <?php if ($product['quantity'] <= $product['min_stock_level']): ?>
                                                        <span class="badge badge-danger"><?php echo $product['quantity']; ?> (Low)</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success"><?php echo $product['quantity']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $product['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($product['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-btns">
                                                        <?php if (isAdmin()): ?>
                                                        <button class="action-btn edit" onclick='editProduct(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="action-btn delete" onclick="deleteProduct(<?php echo $product['id']; ?>, <?php echo htmlspecialchars(json_encode($product['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php else: ?>
                                                        <span class="text-secondary"><i class="fas fa-eye"></i> View Only</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No products found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Modal -->
    <div class="modal-overlay" id="productModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-box"></i> Add Product</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="productId">
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Serial Number *</label>
                            <input type="text" name="serial_number" id="serial_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Product Name *</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Specifications</label>
                        <textarea name="specifications" id="specifications" class="form-control" rows="2" placeholder="e.g., RAM: 8GB, Storage: 256GB SSD, Screen: 15.6 inch"></textarea>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" id="category_id" class="form-control">
                                <option value="">Select Category</option>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Selling Price (K) *</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Cost Price (K)</label>
                            <input type="number" name="cost_price" id="cost_price" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Min Stock Level</label>
                            <input type="number" name="min_stock_level" id="min_stock_level" class="form-control" min="0" value="5">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="file-upload">
                            <input type="file" name="image" id="image" accept="image/*">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload or drag and drop</p>
                            <div class="file-preview"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    
    <script src="assets/js/main.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-box"></i> Add Product';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productId').value = '';
            document.getElementById('serial_number').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('specifications').value = '';
            document.getElementById('category_id').value = '';
            document.getElementById('price').value = '';
            document.getElementById('cost_price').value = '';
            document.getElementById('quantity').value = '';
            document.getElementById('min_stock_level').value = '5';
            document.getElementById('status').value = 'active';
            document.querySelector('.file-preview').innerHTML = '';
            document.getElementById('productModal').classList.add('active');
        }
        
        function editProduct(product) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Product';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('productId').value = product.id;
            document.getElementById('serial_number').value = product.serial_number;
            document.getElementById('name').value = product.name;
            document.getElementById('description').value = product.description || '';
            document.getElementById('specifications').value = product.specifications || '';
            document.getElementById('category_id').value = product.category_id || '';
            document.getElementById('price').value = product.price;
            document.getElementById('cost_price').value = product.cost_price || '';
            document.getElementById('quantity').value = product.quantity;
            document.getElementById('min_stock_level').value = product.min_stock_level;
            document.getElementById('status').value = product.status;
            
            if (product.image) {
                document.querySelector('.file-preview').innerHTML = '<img src="uploads/products/' + product.image + '" alt="Current Image">';
            } else {
                document.querySelector('.file-preview').innerHTML = '';
            }
            
            document.getElementById('productModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }
        
        function deleteProduct(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        function searchTable() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#productsTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
        
        // Close modal on overlay click
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
