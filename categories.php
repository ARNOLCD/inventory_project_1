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
    
    // Only admin can add, edit, or delete categories
    if (($action === 'add' || $action === 'edit' || $action === 'delete') && !isAdmin()) {
        $error = 'Access denied. Only administrators can modify categories.';
    } elseif ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                $message = 'Category added successfully!';
            } else {
                $error = 'Error adding category: ' . $conn->error;
            }
        } else {
            $stmt = $conn->prepare("UPDATE categories SET name=?, description=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $description, $id);
            
            if ($stmt->execute()) {
                $message = 'Category updated successfully!';
            } else {
                $error = 'Error updating category: ' . $conn->error;
            }
        }
    } elseif ($action === 'delete' && isAdmin()) {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM categories WHERE id = $id")) {
            $message = 'Category deleted successfully!';
        } else {
            $error = 'Error deleting category. Make sure no products are using this category.';
        }
    }
}

// Get categories with product count
$categories = $conn->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - AC-TECHNOLOGY</title>
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
                        <h1>Categories</h1>
                        <p>Manage product categories</p>
                    </div>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Category
                    </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-tags"></i> Categories List</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Products</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($categories->num_rows > 0): ?>
                                        <?php $i = 1; while ($category = $categories->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($category['description'] ?? '-'); ?></td>
                                                <td><span class="badge badge-info"><?php echo $category['product_count']; ?> products</span></td>
                                                <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                                <td>
                                                    <div class="action-btns">
                                                        <?php if (isAdmin()): ?>
                                                        <button class="action-btn edit" onclick='editCategory(<?php echo htmlspecialchars(json_encode($category), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="action-btn delete" onclick="deleteCategory(<?php echo $category['id']; ?>, <?php echo htmlspecialchars(json_encode($category['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete">
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
                                            <td colspan="6" class="text-center">No categories found</td>
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
    
    <!-- Category Modal -->
    <div class="modal-overlay" id="categoryModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-tags"></i> Add Category</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="categoryId">
                    
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tags"></i> Add Category';
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryId').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('categoryModal').classList.add('active');
        }
        
        function editCategory(category) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Category';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('name').value = category.name;
            document.getElementById('description').value = category.description || '';
            document.getElementById('categoryModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('categoryModal').classList.remove('active');
        }
        
        function deleteCategory(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        document.getElementById('categoryModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
