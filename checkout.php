<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

if (!isCustomer()) {
    header('Location: dashboard.php');
    exit();
}

$user = getCurrentUser();
$error = '';
$cart_items = json_decode($_POST['cart_items'] ?? '[]', true);

if (!is_array($cart_items) || empty($cart_items)) {
    header('Location: products.php');
    exit();
}

$products = [];
$total_amount = 0;

foreach ($cart_items as $item) {
    $product_id = intval($item['id'] ?? 0);
    $quantity = intval($item['quantity'] ?? 0);

    if ($product_id <= 0 || $quantity <= 0) {
        $error = 'Your cart contains an invalid item.';
        break;
    }

    $stmt = $conn->prepare("SELECT id, name, price, quantity FROM products WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();

    if (!$product || $product['quantity'] < $quantity) {
        $error = 'One or more products are no longer available in the requested quantity.';
        break;
    }

    $product['requested_quantity'] = $quantity;
    $products[] = $product;
    $total_amount += $product['price'] * $quantity;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && !$error) {
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $allowed_payment_methods = ['cash', 'card', 'mobile_money'];

    if (!in_array($payment_method, $allowed_payment_methods, true)) {
        $error = 'Please choose a valid payment method.';
    } else {
        $invoice_number = generateInvoiceNumber($conn);
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO sales (invoice_number, user_id, customer_name, customer_phone, total_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
            $customer_phone = '';
            $stmt->bind_param('sissds', $invoice_number, $user['id'], $user['full_name'], $customer_phone, $total_amount, $payment_method);
            $stmt->execute();
            $sale_id = $conn->insert_id;

            foreach ($products as $product) {
                $quantity = $product['requested_quantity'];
                $item_total = $product['price'] * $quantity;
                $item_type = 'product';
                $service_id = null;
                $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, service_id, item_type, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iiisidd', $sale_id, $product['id'], $service_id, $item_type, $quantity, $product['price'], $item_total);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                $stmt->bind_param('iii', $quantity, $product['id'], $quantity);
                $stmt->execute();
                if ($stmt->affected_rows !== 1) {
                    throw new Exception('Stock changed while placing the order.');
                }
            }

            $conn->commit();
            $order_number = $invoice_number;
            $cart_items = [];
        } catch (Exception $exception) {
            $conn->rollback();
            $error = 'Unable to place the order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="customer-container" style="max-width: 800px; margin: 40px auto; padding: 30px;">
        <h1><i class="fas fa-shopping-bag"></i> Checkout</h1>
        <?php if ($order_number ?? false): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Your order has been placed. Order number: <strong><?php echo htmlspecialchars($order_number); ?></strong>
            </div>
            <a href="customer_dashboard.php" class="btn btn-primary">Return to Dashboard</a>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <div class="card" style="margin: 20px 0;">
                <div class="card-body">
                    <?php foreach ($products as $product): ?>
                        <p><strong><?php echo htmlspecialchars($product['name']); ?></strong> x <?php echo $product['requested_quantity']; ?>: K<?php echo number_format($product['price'] * $product['requested_quantity'], 2); ?></p>
                    <?php endforeach; ?>
                    <hr>
                    <h2>Total: K<?php echo number_format($total_amount, 2); ?></h2>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="cart_items" value="<?php echo htmlspecialchars(json_encode($cart_items), \ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" class="form-control" required>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="card">Card</option>
                        <option value="cash">Pay on collection</option>
                    </select>
                </div>
                <button type="submit" name="place_order" class="btn btn-primary"><i class="fas fa-check"></i> Place Order</button>
                <a href="products.php" class="btn btn-secondary">Back to Shop</a>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($order_number ?? false): ?><script>localStorage.removeItem('customer_cart');</script><?php endif; ?>
</body>
</html>