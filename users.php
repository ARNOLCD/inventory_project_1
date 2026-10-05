<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/email.php';
requireAdmin();

$user = getCurrentUser();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (!in_array($role, USER_ROLES, true)) {
            $error = 'Please choose a valid role.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'The password must be at least 8 characters long.';
        } elseif ($action === 'add') {
            // Validate required fields
            if (empty($username)) {
                $error = 'Username is required.';
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'A valid email is required for new users.';
            } elseif (empty($full_name)) {
                $error = 'Full name is required.';
            } elseif ($password === '') {
                $error = 'Please set a default password for the new user.';
            } else {
                // Admin-chosen default password; the user must change it at first login
                $default_password = $password;
                $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                
                // Check if username already exists
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                // Check if email already exists
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check_email->bind_param("s", $email);
                $check_email->execute();
                $check_email_result = $check_email->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error = 'Username "' . htmlspecialchars($username) . '" already exists. Please choose a different username.';
                } elseif ($check_email_result->num_rows > 0) {
                    $error = 'Email "' . htmlspecialchars($email) . '" is already registered to another user.';
                } else {
                    $must_change = $role === 'customer' ? 0 : 1;
                    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssi", $username, $hashed_password, $full_name, $email, $phone, $role, $must_change);
                    
                    if ($stmt->execute()) {
                        // Send welcome email with credentials (delivered in the background)
                        sendNewUserEmail($email, $username, $default_password, $full_name);
                        $message = 'User <strong>' . e($username) . '</strong> added as <strong>' . e(roleLabel($role)) . '</strong>. Login details are being emailed to <strong>' . e($email) . '</strong>'
                                 . ($must_change ? '; they will be asked to change the password at first login.' : '.');
                    } else {
                        $error = 'Error adding user. Please try again.';
                    }
                }
            }
        } else {
            if ($id == $user['id'] && $role !== 'admin') {
                $error = 'You cannot remove the admin role from your own account.';
            } elseif (!empty($password)) {
                // Admin reset: staff must choose their own password at next login
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $must_change = ($role !== 'customer' && $id != $user['id']) ? 1 : 0;
                $stmt = $conn->prepare("UPDATE users SET username=?, password=?, full_name=?, email=?, phone=?, role=?, must_change_password=? WHERE id=?");
                $stmt->bind_param("ssssssii", $username, $hashed_password, $full_name, $email, $phone, $role, $must_change, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, email=?, phone=?, role=? WHERE id=?");
                $stmt->bind_param("sssssi", $username, $full_name, $email, $phone, $role, $id);
            }
            
            if (!$error) {
                if ($stmt->execute()) {
                    $message = 'User updated successfully!' . (!empty($must_change) ? ' The user will be asked to choose a new password at next login.' : '');
                } else {
                    $error = 'Error updating user.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id == $user['id']) {
            $error = 'You cannot delete your own account.';
        } else {
            if ($conn->query("DELETE FROM users WHERE id = $id")) {
                $message = 'User deleted successfully!';
            } else {
                $error = 'Error deleting user.';
            }
        }
    }
}

// Get users
$users = $conn->query("SELECT id, username, full_name, email, phone, role, must_change_password, created_at FROM users ORDER BY role, full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - <?php echo e(companyName()); ?></title>
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
                        <h1><i class="fas fa-users"></i> User Management</h1>
                        <p>Manage system users and permissions</p>
                    </div>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Users List</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($u = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="user-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                                            <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $u['role'] === 'admin' ? 'success' : ($u['role'] === 'customer' || $u['role'] === '' ? 'warning' : 'info'); ?>">
                                                    <?php echo e($u['role'] === '' ? 'No role' : roleLabel($u['role'])); ?>
                                                </span>
                                                <?php if (!empty($u['must_change_password'])): ?>
                                                    <div style="font-size: 0.75rem; color: #718096;"><i class="fas fa-key"></i> Must change password</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="action-btn edit" onclick='editUser(<?php echo json_encode($u); ?>)' title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($u['id'] != $user['id']): ?>
                                                    <button class="action-btn delete" onclick="deleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['full_name']); ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user"></i> Add User</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Username *</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span id="emailHint" style="color: #e53e3e;">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="phone" class="form-control">
                    </div>
                    
                    <div class="form-group" id="passwordGroup">
                        <label><span id="passwordLabel">Default Password *</span> <span id="passwordHint" style="color: #718096; font-weight: normal;"></span></label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="password" id="password" class="form-control" minlength="8" autocomplete="new-password">
                            <button type="button" class="btn btn-secondary" onclick="generatePassword()" title="Generate a strong password"><i class="fas fa-random"></i></button>
                        </div>
                    </div>
                    
                    <div id="defaultPasswordInfo" style="display: none; background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                        <p style="margin: 0; color: #2b6cb0; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> This password is emailed to the user. Internal users must choose their own password the first time they log in.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" id="role" class="form-control" required>
                            <?php foreach (ROLE_LABELS as $role_value => $role_label): ?>
                                <option value="<?php echo $role_value; ?>"><?php echo e($role_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size: 0.8rem; color: #718096; margin-top: 4px;">Admin: full access incl. System Information and deleting repairs. Employee / Technician / Sales: inventory, POS, repairs and repair requests. Customer: shop and book repairs only.</div>
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
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add User';
            document.getElementById('formAction').value = 'add';
            document.getElementById('userId').value = '';
            document.getElementById('full_name').value = '';
            document.getElementById('username').value = '';
            document.getElementById('email').value = '';
            document.getElementById('email').required = true;
            document.getElementById('emailHint').style.display = '';
            document.getElementById('phone').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password').required = true;
            document.getElementById('passwordLabel').textContent = 'Default Password *';
            document.getElementById('passwordHint').textContent = '(min. 8 characters)';
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('defaultPasswordInfo').style.display = 'block';
            generatePassword();
            document.getElementById('role').value = 'employee';
            document.getElementById('userModal').classList.add('active');
        }
        
        function editUser(user) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('userId').value = user.id;
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email || '';
            document.getElementById('email').required = false;
            document.getElementById('emailHint').style.display = 'none';
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('passwordLabel').textContent = 'Reset Password';
            document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
            document.getElementById('passwordGroup').style.display = 'block';
            document.getElementById('defaultPasswordInfo').style.display = 'none';
            document.getElementById('role').value = user.role;
            document.getElementById('userModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        function generatePassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%';
            const bytes = crypto.getRandomValues(new Uint32Array(12));
            document.getElementById('password').value = Array.from(bytes, b => chars[b % chars.length]).join('');
        }
        
        function deleteUser(id, name) {
            if (confirm('Are you sure you want to delete user "' + name + '"?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
