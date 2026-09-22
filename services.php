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
    
    // Only admin can add, edit, or delete services
    if (($action === 'add' || $action === 'edit' || $action === 'delete') && !isAdmin()) {
        $error = 'Access denied. Only administrators can modify services.';
    } elseif ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = trim($_POST['duration']);
        $status = $_POST['status'];
        
        // Handle image upload
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/services/';
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
            $sql = "INSERT INTO services (name, description, price, duration, image, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsss", $name, $description, $price, $duration, $image, $status);
            
            if ($stmt->execute()) {
                $message = 'Service added successfully!';
            } else {
                $error = 'Error adding service: ' . $conn->error;
            }
        } else {
            if ($image) {
                $sql = "UPDATE services SET name=?, description=?, price=?, duration=?, image=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdsssi", $name, $description, $price, $duration, $image, $status, $id);
            } else {
                $sql = "UPDATE services SET name=?, description=?, price=?, duration=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdssi", $name, $description, $price, $duration, $status, $id);
            }
            
            if ($stmt->execute()) {
                $message = 'Service updated successfully!';
            } else {
                $error = 'Error updating service: ' . $conn->error;
            }
        }
    } elseif ($action === 'delete' && isAdmin()) {
        $id = intval($_POST['id']);
        if ($conn->query("DELETE FROM services WHERE id = $id")) {
            $message = 'Service deleted successfully!';
        } else {
            $error = 'Error deleting service.';
        }
    }
}

// Get services
$services = $conn->query("SELECT * FROM services ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - AC-TECHNOLOGY</title>
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
                        <h1>Services</h1>
                        <p>Manage your service offerings</p>
                    </div>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add Service
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
                        <h3><i class="fas fa-cogs"></i> Services List</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($services->num_rows > 0): ?>
                                        <?php while ($service = $services->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($service['image']): ?>
                                                        <img src="uploads/services/<?php echo htmlspecialchars($service['image']); ?>" alt="Service">
                                                    <?php else: ?>
                                                        <div style="width: 50px; height: 50px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                            <i class="fas fa-cogs" style="color: #a0aec0;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($service['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars(substr($service['description'] ?? '', 0, 60)); ?>...</td>
                                                <td><strong>K<?php echo number_format($service['price'], 2); ?></strong></td>
                                                <td><?php echo htmlspecialchars($service['duration'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $service['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($service['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-btns">
                                                        <?php if (isAdmin()): ?>
                                                        <button class="action-btn edit" onclick='editService(<?php echo htmlspecialchars(json_encode($service), ENT_QUOTES, "UTF-8"); ?>)' title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="action-btn delete" onclick="deleteService(<?php echo $service['id']; ?>, <?php echo htmlspecialchars(json_encode($service['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="Delete">
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
                                            <td colspan="7" class="text-center">No services found</td>
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
    
    <!-- Service Modal -->
    <div class="modal-overlay" id="serviceModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-cogs"></i> Add Service</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="serviceId">
                    
                    <div class="form-group">
                        <label>Service Name *</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Price (K) *</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Duration</label>
                            <input type="text" name="duration" id="duration" class="form-control" placeholder="e.g., 1-2 days">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Service Image</label>
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-cogs"></i> Add Service';
            document.getElementById('formAction').value = 'add';
            document.getElementById('serviceId').value = '';
            document.getElementById('name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('price').value = '';
            document.getElementById('duration').value = '';
            document.getElementById('status').value = 'active';
            document.querySelector('.file-preview').innerHTML = '';
            document.getElementById('serviceModal').classList.add('active');
        }
        
        function editService(service) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Service';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('serviceId').value = service.id;
            document.getElementById('name').value = service.name;
            document.getElementById('description').value = service.description || '';
            document.getElementById('price').value = service.price;
            document.getElementById('duration').value = service.duration || '';
            document.getElementById('status').value = service.status;
            
            if (service.image) {
                document.querySelector('.file-preview').innerHTML = '<img src="uploads/services/' + service.image + '" alt="Current Image">';
            } else {
                document.querySelector('.file-preview').innerHTML = '';
            }
            
            document.getElementById('serviceModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('serviceModal').classList.remove('active');
        }
        
        function deleteService(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        document.getElementById('serviceModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
