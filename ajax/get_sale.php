<?php
require_once '../config/database.php';
require_once '../config/session.php';
requireStaff();

$sale_id = intval($_GET['id'] ?? 0);

if (!$sale_id) {
    echo '<p class="text-danger">Invalid sale ID</p>';
    exit;
}

// Get sale details
$sale = $conn->query("
    SELECT s.*, u.full_name as employee_name 
    FROM sales s 
    LEFT JOIN users u ON s.user_id = u.id 
    WHERE s.id = $sale_id
")->fetch_assoc();

if (!$sale) {
    echo '<p class="text-danger">Sale not found</p>';
    exit;
}

// Get sale items
$items = $conn->query("
    SELECT si.*, 
           p.name as product_name, p.serial_number,
           sv.name as service_name
    FROM sale_items si
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN services sv ON si.service_id = sv.id
    WHERE si.sale_id = $sale_id
");
?>

<div id="receiptContent">
    <div style="text-align: center; margin-bottom: 1rem;">
        <h3 style="margin: 0;">Invoice: <?php echo htmlspecialchars($sale['invoice_number']); ?></h3>
        <p style="color: #718096; margin: 0.5rem 0;"><?php echo date('F d, Y h:i A', strtotime($sale['sale_date'])); ?></p>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: #f7fafc; border-radius: 8px;">
        <div>
            <strong>Customer:</strong><br>
            <?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer'); ?>
            <?php if ($sale['customer_phone']): ?>
                <br><?php echo htmlspecialchars($sale['customer_phone']); ?>
            <?php endif; ?>
        </div>
        <div>
            <strong>Served by:</strong><br>
            <?php echo htmlspecialchars($sale['employee_name']); ?>
            <br><strong>Payment:</strong> <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?>
        </div>
    </div>
    
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #e2e8f0;">
                <th style="padding: 0.75rem; text-align: left;">Item</th>
                <th style="padding: 0.75rem; text-align: center;">Qty</th>
                <th style="padding: 0.75rem; text-align: right;">Price</th>
                <th style="padding: 0.75rem; text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 0.75rem;">
                        <?php 
                        if ($item['item_type'] === 'product') {
                            echo htmlspecialchars($item['product_name']);
                            if ($item['serial_number']) {
                                echo '<br><small style="color: #718096;">' . htmlspecialchars($item['serial_number']) . '</small>';
                            }
                        } else {
                            echo htmlspecialchars($item['service_name']) . ' <span class="badge badge-info">Service</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 0.75rem; text-align: center;"><?php echo $item['quantity']; ?></td>
                    <td style="padding: 0.75rem; text-align: right;">K<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td style="padding: 0.75rem; text-align: right;">K<?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr style="background: #1a365d; color: #fff;">
                <td colspan="3" style="padding: 1rem; text-align: right; font-weight: bold;">TOTAL:</td>
                <td style="padding: 1rem; text-align: right; font-weight: bold; font-size: 1.2em;">K<?php echo number_format($sale['total_amount'], 2); ?></td>
            </tr>
        </tfoot>
    </table>
</div>
