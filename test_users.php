<?php
require_once 'config/database.php';
$conn = getDBConnection();

echo "<h3>User Accounts in Database:</h3>";
echo "<table border='1' style='border-collapse: collapse; padding: 10px;'>";
echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th></tr>";

$result = $conn->query("SELECT id, username, full_name, email, role FROM users ORDER BY id");
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email'] ?: 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($row['role']) . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test if admin user has email
$admin_check = $conn->query("SELECT email FROM users WHERE LOWER(username) = 'admin'");
$admin = $admin_check->fetch_assoc();

echo "<h3>Admin User Email Status:</h3>";
if ($admin && $admin['email']) {
    echo "<p style='color: green;'>✅ Admin has email: " . htmlspecialchars($admin['email']) . "</p>";
} else {
    echo "<p style='color: red;'>❌ Admin user has no email address - this is why password reset fails!</p>";
    echo "<p><strong>Solution:</strong> Update admin user to have an email address.</p>";
}

$conn->close();
?>
