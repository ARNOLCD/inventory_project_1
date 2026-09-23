<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';


// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_sale'])) {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $payment_method = $_POST['payment_method'];
    $cart_items = json_decode($_POST['cart_items'], true);
    $total_amount = floatval($_POST['total_amount']);
    
    if (empty($cart_items)) {
        $error = 'Cart is empty!';
    } else {
        // Generate invoice number
        $invoice_number = generateInvoiceNumber($conn);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert sale
            $stmt = $conn->prepare("INSERT INTO sales (invoice_number, user_id, customer_name, customer_phone, total_amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissds", $invoice_number, $user['id'], $customer_name, $customer_phone, $total_amount, $payment_method);
            $stmt->execute();
            $sale_id = $conn->insert_id;
            
            // Insert sale items and update stock
            foreach ($cart_items as $item) {
                $item_type = $item['type'];
                $product_id = $item_type === 'product' ? $item['id'] : null;
                $service_id = $item_type === 'service' ? $item['id'] : null;
                $quantity = intval($item['quantity']);
                $unit_price = floatval($item['price']);
                $item_total = $unit_price * $quantity;
                
                $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, service_id, item_type, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisidd", $sale_id, $product_id, $service_id, $item_type, $quantity, $unit_price, $item_total);
                $stmt->execute();
                
                // Update product stock
                if ($item_type === 'product') {
                    $conn->query("UPDATE products SET quantity = quantity - $quantity WHERE id = $product_id");
                }
            }
            
            $conn->commit();
            $message = "✅ Sale completed successfully! Invoice: $invoice_number | Total: K" . number_format($total_amount, 2);
            
            // Clear cart via JavaScript
            echo "<script>localStorage.removeItem('pos_cart');</script>";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = '❌ Error processing sale: ' . $e->getMessage();
        }
    }
}

// Get products
$products = $conn->query("SELECT * FROM products WHERE status = 'active' AND quantity > 0 ORDER BY name ASC");

// Get services
$services = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY name ASC");

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 1.5rem;
            height: calc(100vh - 180px);
        }
        
        .pos-products {
            overflow-y: auto;
        }
        
        .pos-cart {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
        }
        
        .cart-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--light-bg);
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
        }
        
        .cart-item-info h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .cart-item-info span {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .qty-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 50%;
            background: var(--primary-color);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .qty-btn:hover {
            background: var(--secondary-color);
        }
        
        .cart-item-qty {
            font-weight: 600;
            min-width: 30px;
            text-align: center;
        }
        
        .remove-item {
            color: var(--danger-color);
            cursor: pointer;
            padding: 0.25rem;
        }
        
        .cart-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1rem;
        }
        
        .pos-product-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        
        .pos-product-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .pos-product-card.out-of-stock {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .pos-product-card img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
        }
        
        .pos-product-card h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }
        
        .pos-product-card .price {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .pos-product-card .stock {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }
        
        .pos-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .pos-tab {
            padding: 0.5rem 1rem;
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 500;
            transition: all var(--transition-fast);
        }
        
        .pos-tab.active {
            background: var(--primary-color);
            color: #fff;
            border-color: var(--primary-color);
        }
        
        .empty-cart {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }
        
        .empty-cart i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .qty-input, .price-input {
            width: 60px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 4px;
            font-size: 14px;
            margin: 0 5px;
        }
        
        .price-input {
            width: 80px;
        }
        
        .qty-input:focus, .price-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 54, 93, 0.1);
        }
        
        .cart-item-pricing {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
            font-size: 13px;
        }
        
        .cart-item-pricing label {
            color: var(--text-secondary);
        }
        
        .item-total {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        @media (max-width: 1024px) {
            .pos-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .pos-cart {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                max-height: 50vh;
                z-index: 100;
                border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="page-header">
                    <h1><i class="fas fa-cash-register"></i> Point of Sale</h1>
                    <p>Process sales transactions</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="pos-container">
                    <!-- Products Section -->
                    <div class="pos-products">
                        <div class="pos-tabs">
                            <span class="pos-tab active" onclick="showTab('products')">Products</span>
                            <span class="pos-tab" onclick="showTab('services')">Services</span>
                        </div>
                        
                        <input type="text" id="searchInput" class="form-control mb-2" placeholder="Search products or services..." onkeyup="searchItems()">
                        
                        <!-- Products Grid -->
                        <div id="productsGrid" class="product-grid">
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <div class="pos-product-card <?php echo $product['quantity'] <= 0 ? 'out-of-stock' : ''; ?>" 
                                     onclick='addToCart(<?php echo json_encode([
                                         "id" => $product["id"],
                                         "name" => $product["name"],
                                         "price" => floatval($product["price"]),
                                         "stock" => intval($product["quantity"]),
                                         "type" => "product",
                                         "image" => $product["image"]
                                     ]); ?>)'>
                                    <?php if ($product['image']): ?>
                                        <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div style="width: 80px; height: 80px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem;">
                                            <i class="fas fa-box" style="font-size: 2rem; color: #a0aec0;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p class="price">K<?php echo number_format($product['price'], 2); ?></p>
                                    <p class="stock"><?php echo $product['quantity']; ?> in stock</p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Services Grid -->
                        <div id="servicesGrid" class="product-grid" style="display: none;">
                            <?php while ($service = $services->fetch_assoc()): ?>
                                <div class="pos-product-card" 
                                     onclick='addToCart(<?php echo json_encode([
                                         "id" => $service["id"],
                                         "name" => $service["name"],
                                         "price" => floatval($service["price"]),
                                         "stock" => 999,
                                         "type" => "service",
                                         "image" => $service["image"]
                                     ]); ?>)'>
                                    <?php if ($service['image']): ?>
                                        <img src="uploads/services/<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>">
                                    <?php else: ?>
                                        <div style="width: 80px; height: 80px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem;">
                                            <i class="fas fa-cogs" style="font-size: 2rem; color: #a0aec0;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                    <p class="price">K<?php echo number_format($service['price'], 2); ?></p>
                                    <p class="stock"><?php echo htmlspecialchars($service['duration'] ?? 'Service'); ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- Cart Section -->
                    <div class="pos-cart">
                        <div class="cart-header">
                            <h3><i class="fas fa-shopping-cart"></i> Cart</h3>
                            <button class="btn btn-danger btn-sm" onclick="clearCart()">
                                <i class="fas fa-trash"></i> Clear
                            </button>
                        </div>
                        
                        <div class="cart-items" id="cartItems">
                            <div class="empty-cart">
                                <i class="fas fa-shopping-basket"></i>
                                <p>Cart is empty</p>
                            </div>
                        </div>
                        
                        <div class="cart-footer">
                            <div class="cart-total">
                                <span>Total:</span>
                                <span id="cartTotal">K0.00</span>
                            </div>
                            
                            <form method="POST" id="checkoutForm">
                                <input type="hidden" name="complete_sale" value="1">
                                <input type="hidden" name="cart_items" id="cartItemsInput">
                                <input type="hidden" name="total_amount" id="totalAmountInput">
                                
                                <div class="form-group">
                                    <input type="text" name="customer_name" class="form-control" placeholder="Customer Name (optional)">
                                </div>
                                <div class="form-group">
                                    <input type="text" name="customer_phone" class="form-control" placeholder="Phone (optional)">
                                </div>
                                <div class="form-group">
                                    <select name="payment_method" class="form-control" required>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-lg" style="width: 100%;" id="checkoutBtn" disabled>
                                    <i class="fas fa-check"></i> Complete Sale
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        let cart = JSON.parse(localStorage.getItem('pos_cart')) || [];
        
        function showTab(tab) {
            document.querySelectorAll('.pos-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            document.getElementById('productsGrid').style.display = tab === 'products' ? 'grid' : 'none';
            document.getElementById('servicesGrid').style.display = tab === 'services' ? 'grid' : 'none';
        }
        
        function addToCart(item) {
                        const existingIndex = cart.findIndex(i => i.id === item.id && i.type === item.type);
            
            if (existingIndex > -1) {
                if (item.type === 'product' && cart[existingIndex].quantity >= item.stock) {
                    alert('Not enough stock!');
                    return;
                }
                cart[existingIndex].quantity++;
            } else {
                cart.push({...item, quantity: 1});
            }
            
            updateCart();
        }
        
        function updateQuantity(index, delta) {
            const item = cart[index];
            const newQty = item.quantity + delta;
            
            if (newQty <= 0) {
                cart.splice(index, 1);
            } else if (item.type === 'product' && newQty > item.stock) {
                alert('Not enough stock!');
                return;
            } else {
                cart[index].quantity = newQty;
            }
            
            updateCart();
        }
        
        function setQuantity(index, value) {
            const item = cart[index];
            const newQty = parseInt(value);
            
            if (isNaN(newQty) || newQty <= 0) {
                cart.splice(index, 1);
            } else if (item.type === 'product' && newQty > item.stock) {
                alert('Not enough stock! Maximum available: ' + item.stock);
                cart[index].quantity = item.stock;
            } else {
                cart[index].quantity = newQty;
            }
            
            updateCart();
        }
        
        function setPrice(index, value) {
            const newPrice = parseFloat(value);
            
            if (isNaN(newPrice) || newPrice < 0) {
                alert('Please enter a valid price');
                updateCart();
                return;
            }
            
            cart[index].price = newPrice;
            updateCart();
        }
        
        function removeItem(index) {
            cart.splice(index, 1);
            updateCart();
        }
        
        function clearCart() {
            if (confirm('Clear all items from cart?')) {
                cart = [];
                updateCart();
            }
        }
        
        function updateCart() {
            localStorage.setItem('pos_cart', JSON.stringify(cart));
            
            const cartItemsEl = document.getElementById('cartItems');
            const cartTotalEl = document.getElementById('cartTotal');
            const checkoutBtn = document.getElementById('checkoutBtn');
            
            if (cart.length === 0) {
                cartItemsEl.innerHTML = '<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Cart is empty</p></div>';
                cartTotalEl.textContent = 'K0.00';
                checkoutBtn.disabled = true;
                return;
            }
            
            let html = '';
            let total = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <h4>${item.name}</h4>
                            <div class="cart-item-pricing">
                                <label>Price: K</label>
                                <input type="number" class="price-input" value="${item.price.toFixed(2)}" min="0" step="0.01" onchange="setPrice(${index}, this.value)">
                                <span class="item-total">× ${item.quantity} = K${itemTotal.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="cart-item-actions">
                            <button class="qty-btn" onclick="updateQuantity(${index}, -1)">-</button>
                            <input type="number" class="qty-input" value="${item.quantity}" min="1" ${item.type === 'product' ? `max="${item.stock}"` : ''} onchange="setQuantity(${index}, this.value)">
                            <button class="qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
                            <span class="remove-item" onclick="removeItem(${index})"><i class="fas fa-times"></i></span>
                        </div>
                    </div>
                `;
            });
            
            cartItemsEl.innerHTML = html;
            cartTotalEl.textContent = 'K' + total.toFixed(2);
            checkoutBtn.disabled = false;
            
            document.getElementById('cartItemsInput').value = JSON.stringify(cart);
            document.getElementById('totalAmountInput').value = total;
        }
        
        function searchItems() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.pos-product-card').forEach(card => {
                const name = card.querySelector('h4').textContent.toLowerCase();
                card.style.display = name.includes(query) ? 'block' : 'none';
            });
        }
        
        // Initialize cart on page load
        updateCart();
        
        // Form submission
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Cart is empty!');
            }
        });
    </script>
</body>
</html>
