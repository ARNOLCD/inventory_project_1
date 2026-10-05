<?php
$user = getCurrentUser();

// Get low stock count for notification (only for staff)
$low_stock_count = 0;
if (!isCustomer()) {
    $low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level AND status = 'active'")->fetch_assoc()['count'];
}
?>
<header class="top-header">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search...">
    </div>
    <div class="header-actions">
        <?php if (!isCustomer()): ?>
        <button class="notification-btn" onclick="window.location.href='products.php?filter=low_stock'">
            <i class="fas fa-bell"></i>
            <?php if ($low_stock_count > 0): ?>
                <span class="notification-badge"><?php echo $low_stock_count; ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
        <div class="user-dropdown">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <span><?php echo ucfirst($user['role']); ?></span>
            </div>
        </div>
    </div>
</header>
