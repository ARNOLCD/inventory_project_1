<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/receipts.php';
requireLogin();

$user = getCurrentUser();
$message = '';

// Staff can open (and generate, for older payments) the receipt for a sale or repair payment
if (isStaff() && isset($_GET['source'], $_GET['id']) && in_array($_GET['source'], ['sale', 'repair'], true)) {
    $receipt_id = createReceipt($conn, $_GET['source'], (int)$_GET['id']);
    if (!$receipt_id) {
        http_response_code(404);
        exit('No receipt: this payment has not been completed yet.');
    }
    header('Location: receipt.php?id=' . $receipt_id . (isset($_GET['print']) ? '&print=1' : ''));
    exit();
}

$receipt = getReceipt($conn, (int)($_GET['id'] ?? 0));

// Customers can only see their own receipts
if (!$receipt || (!isStaff() && (int)$receipt['customer_id'] !== (int)$user['id'])) {
    http_response_code(404);
    exit('Receipt not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isStaff() && isset($_POST['resend'])) {
    $message = emailReceipt($conn, $receipt['id'])
        ? 'Receipt is being emailed to ' . e($receipt['client_email']) . '.'
        : 'This receipt has no valid client email address.';
}

$company = companyInfo();
$back = isStaff() ? ($receipt['source_type'] === 'repair' ? 'repairs.php' : 'sales.php') : 'customer_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo e($receipt['document_number']); ?> - <?php echo e(companyName()); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #edf2f7; color: #2d3748; margin: 0; padding: 30px 15px; }
        .toolbar { max-width: 760px; margin: 0 auto 15px; display: flex; gap: 10px; flex-wrap: wrap; }
        .toolbar a, .toolbar button { background: #1a365d; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; text-decoration: none; cursor: pointer; font-size: 14px; }
        .toolbar .secondary { background: #718096; }
        .notice { max-width: 760px; margin: 0 auto 15px; background: #f0fff4; border: 1px solid #9ae6b4; padding: 10px 15px; border-radius: 6px; }
        .receipt { max-width: 760px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); position: relative; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a365d; padding-bottom: 20px; gap: 20px; }
        .head img { max-height: 80px; max-width: 200px; }
        .company h1 { margin: 0 0 5px; color: #1a365d; font-size: 22px; }
        .company p { margin: 2px 0; font-size: 13px; color: #4a5568; }
        .title { text-align: right; }
        .title h2 { margin: 0; font-size: 28px; letter-spacing: 3px; color: #1a365d; }
        .title p { margin: 4px 0; font-size: 13px; }
        .meta { display: flex; justify-content: space-between; margin: 25px 0; gap: 20px; font-size: 14px; }
        .meta h4 { margin: 0 0 6px; color: #718096; font-size: 12px; text-transform: uppercase; }
        .meta p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #1a365d; color: #fff; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; }
        .total td { font-size: 16px; font-weight: bold; border-bottom: none; }
        .stamp { position: absolute; right: 50px; top: 170px; border: 4px solid #38a169; color: #38a169; font-size: 30px; font-weight: bold; padding: 6px 18px; transform: rotate(-12deg); border-radius: 8px; opacity: 0.6; }
        .foot { margin-top: 30px; text-align: center; font-size: 12px; color: #718096; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar, .notice { display: none; }
            .receipt { box-shadow: none; border-radius: 0; padding: 20px; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()"><i class="fas fa-print"></i> Print</button>
        <a href="<?php echo e($back); ?>" class="secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <?php if (isStaff() && filter_var($receipt['client_email'], FILTER_VALIDATE_EMAIL)): ?>
            <form method="POST" style="margin: 0;"><button type="submit" name="resend" value="1" class="secondary"><i class="fas fa-envelope"></i> Email to client</button></form>
        <?php endif; ?>
    </div>
    <?php if ($message): ?><div class="notice"><?php echo $message; ?></div><?php endif; ?>

    <div class="receipt">
        <div class="stamp">PAID</div>
        <div class="head">
            <div class="company">
                <img src="<?php echo e(companyLogo()); ?>" alt="<?php echo e(companyName()); ?>" onerror="this.style.display='none'">
                <h1><?php echo e(companyName()); ?></h1>
                <?php if (!empty($company['address'])): ?><p><?php echo nl2br(e($company['address'])); ?></p><?php endif; ?>
                <?php if (!empty($company['phone']) || !empty($company['mobile'])): ?><p>Tel: <?php echo e(trim(($company['phone'] ?? '') . ' / ' . ($company['mobile'] ?? ''), ' /')); ?></p><?php endif; ?>
                <?php if (!empty($company['email'])): ?><p><?php echo e($company['email']); ?></p><?php endif; ?>
                <?php if (!empty($company['tpin'])): ?><p>TPIN: <?php echo e($company['tpin']); ?></p><?php endif; ?>
            </div>
            <div class="title">
                <h2>RECEIPT</h2>
                <p><strong>No:</strong> <?php echo e($receipt['document_number']); ?></p>
                <p><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($receipt['created_at'])); ?></p>
            </div>
        </div>

        <div class="meta">
            <div>
                <h4>Received from</h4>
                <p><strong><?php echo e($receipt['client_name']); ?></strong></p>
                <?php if ($receipt['client_phone']): ?><p><?php echo e($receipt['client_phone']); ?></p><?php endif; ?>
                <?php if ($receipt['client_email']): ?><p><?php echo e($receipt['client_email']); ?></p><?php endif; ?>
            </div>
            <div style="text-align: right;">
                <h4>Payment</h4>
                <p><?php echo e(paymentMethodLabel($receipt['payment_method'])); ?></p>
                <p><?php echo e($receipt['notes']); ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit Price</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                <?php foreach ($receipt['items'] as $item): ?>
                    <tr>
                        <td><?php echo e($item['description']); ?></td>
                        <td class="num"><?php echo (int)$item['quantity']; ?></td>
                        <td class="num">K<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="num">K<?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total">
                    <td colspan="3" class="num">Total Paid</td>
                    <td class="num">K<?php echo number_format($receipt['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="foot">
            <p>Thank you for your business!</p>
            <p>This receipt was generated automatically by the <?php echo e(companyName()); ?> system.</p>
        </div>
    </div>
    <?php if (isset($_GET['print'])): ?><script>window.addEventListener('load', () => window.print());</script><?php endif; ?>
</body>
</html>
