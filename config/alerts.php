<?php
// Staff alerts: low-stock emails and the notification bell.
require_once __DIR__ . '/email.php';

/**
 * Call after stock changes. Emails all internal users once when a product drops to (or below) its
 * minimum stock level; the alert re-arms automatically when the product is restocked.
 */
function checkLowStock($conn, array $product_ids = []) {
    $ids = array_filter(array_map('intval', $product_ids));
    $filter = $ids ? ' AND id IN (' . implode(',', $ids) . ')' : '';

    $conn->query("UPDATE products SET low_stock_alerted = 0 WHERE low_stock_alerted = 1 AND quantity > min_stock_level $filter");
    $low = $conn->query("SELECT id, name, serial_number, quantity, min_stock_level FROM products
                         WHERE status = 'active' AND low_stock_alerted = 0 AND quantity <= min_stock_level $filter
                         ORDER BY quantity ASC")->fetch_all(MYSQLI_ASSOC);
    if (!$low) {
        return;
    }
    $conn->query("UPDATE products SET low_stock_alerted = 1 WHERE id IN (" . implode(',', array_column($low, 'id')) . ")");

    if (getSetting('low_stock_email_alerts', '1') !== '1') {
        return;
    }
    $rows = '';
    foreach ($low as $product) {
        $color = (int)$product['quantity'] <= 0 ? '#e53e3e' : '#dd6b20';
        $rows .= "<tr>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'>" . e($product['name']) . ($product['serial_number'] ? '<br><small>' . e($product['serial_number']) . '</small>' : '') . "</td>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: center; color: $color; font-weight: bold;'>" . (int)$product['quantity'] . "</td>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: center;'>" . (int)$product['min_stock_level'] . "</td>
        </tr>";
    }
    $content = "
        <p>The following product" . (count($low) > 1 ? 's have' : ' has') . " reached the minimum stock level and should be restocked:</p>
        <table style='width: 100%; border-collapse: collapse; background: #fff;'>
            <thead><tr style='background: #1a365d; color: #fff;'><th style='padding: 8px; text-align: left;'>Product</th><th style='padding: 8px;'>In Stock</th><th style='padding: 8px;'>Minimum</th></tr></thead>
            <tbody>$rows</tbody>
        </table>
        <p style='text-align: center; margin-top: 20px;'>
            <a href='" . e(SYSTEM_URL . '/products.php?filter=low_stock') . "' style='display: inline-block; background: #dd6b20; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>View Low Stock Items</a>
        </p>";

    $subject = 'Low Stock Alert: ' . (count($low) === 1 ? $low[0]['name'] : count($low) . ' products') . ' - ' . companyName();
    queueEmailToStaff($subject, emailLayout('Low Stock Alert', 'Inventory Alert', $content));
}

/**
 * Data for the staff notification bell (one round trip for the counts)
 */
function getStaffNotifications($conn) {
    $counts = $conn->query("SELECT
        (SELECT COUNT(*) FROM products WHERE status = 'active' AND quantity <= min_stock_level) AS low_stock,
        (SELECT COUNT(*) FROM repairs WHERE status = 'pending_approval') AS pending_requests")->fetch_assoc();
    $items = [];
    if ($counts['pending_requests'] > 0) {
        $result = $conn->query("SELECT ticket_number, customer_name, device_type, created_at FROM repairs WHERE status = 'pending_approval' ORDER BY created_at ASC LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            $items[] = ['icon' => 'fa-inbox', 'color' => '#3182ce', 'link' => 'repair_requests.php',
                        'text' => 'Repair request ' . $row['ticket_number'] . ' from ' . $row['customer_name'] . ' (' . $row['device_type'] . ')',
                        'time' => $row['created_at']];
        }
    }
    if ($counts['low_stock'] > 0) {
        $result = $conn->query("SELECT name, quantity FROM products WHERE status = 'active' AND quantity <= min_stock_level ORDER BY quantity ASC LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            $items[] = ['icon' => 'fa-box-open', 'color' => (int)$row['quantity'] <= 0 ? '#e53e3e' : '#dd6b20', 'link' => 'products.php?filter=low_stock',
                        'text' => $row['name'] . ((int)$row['quantity'] <= 0 ? ' is out of stock' : ' - only ' . (int)$row['quantity'] . ' left'),
                        'time' => null];
        }
    }
    return ['low_stock' => (int)$counts['low_stock'], 'pending_requests' => (int)$counts['pending_requests'],
            'total' => (int)$counts['low_stock'] + (int)$counts['pending_requests'], 'items' => $items];
}
