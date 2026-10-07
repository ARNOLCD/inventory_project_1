<?php
$user = getCurrentUser();

// Staff notifications: low stock + pending repair requests (computed once, shared with the sidebar)
if (isStaff()) {
    require_once __DIR__ . '/../config/alerts.php';
    $staff_notifications = $staff_notifications ?? getStaffNotifications($conn);
}
echo idleLogoutScript();
?>
<header class="top-header">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search...">
    </div>
    <div class="header-actions">
        <?php if (isStaff()): ?>
        <div class="notification-wrapper" style="position: relative;">
            <button class="notification-btn" type="button" onclick="document.getElementById('notificationMenu').classList.toggle('open')" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($staff_notifications['total'] > 0): ?>
                    <span class="notification-badge"><?php echo $staff_notifications['total']; ?></span>
                <?php endif; ?>
            </button>
            <div id="notificationMenu" class="notification-menu">
                <div class="notification-menu-header">
                    <strong>Notifications</strong>
                    <span><?php echo $staff_notifications['pending_requests']; ?> requests &middot; <?php echo $staff_notifications['low_stock']; ?> low stock</span>
                </div>
                <?php if ($staff_notifications['items']): ?>
                    <?php foreach ($staff_notifications['items'] as $item): ?>
                        <a href="<?php echo e($item['link']); ?>" class="notification-item">
                            <i class="fas <?php echo $item['icon']; ?>" style="color: <?php echo $item['color']; ?>;"></i>
                            <span>
                                <?php echo e($item['text']); ?>
                                <?php if ($item['time']): ?><small><?php echo date('M d, H:i', strtotime($item['time'])); ?></small><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                    <div class="notification-menu-footer">
                        <a href="repair_requests.php">All requests</a>
                        <a href="products.php?filter=low_stock">All low stock</a>
                    </div>
                <?php else: ?>
                    <div class="notification-empty"><i class="fas fa-check-circle"></i> You're all caught up</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="user-dropdown">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <span><?php echo e(roleLabel($user['role'])); ?></span>
            </div>
            <a href="change_password.php" title="Change password" style="margin-left: 10px; color: #718096;"><i class="fas fa-key"></i></a>
        </div>
    </div>
</header>
<style>
    .notification-menu { display: none; position: absolute; right: 0; top: 42px; width: 340px; background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden; }
    .notification-menu.open { display: block; }
    .notification-menu-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
    .notification-menu-header span { color: #718096; font-size: 0.75rem; }
    .notification-item { display: flex; gap: 12px; padding: 10px 15px; border-bottom: 1px solid #f1f5f9; color: #2d3748; text-decoration: none; font-size: 0.85rem; }
    .notification-item:hover { background: #f7fafc; }
    .notification-item i { margin-top: 3px; }
    .notification-item small { display: block; color: #a0aec0; font-size: 0.7rem; }
    .notification-menu-footer { display: flex; justify-content: space-between; padding: 10px 15px; font-size: 0.8rem; }
    .notification-empty { padding: 25px; text-align: center; color: #718096; font-size: 0.9rem; }
</style>
<script>
    document.addEventListener('click', function (e) {
        const menu = document.getElementById('notificationMenu');
        if (menu && !e.target.closest('.notification-wrapper')) menu.classList.remove('open');
    });
</script>
