<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$message = '';
$error = '';

// Get document type
$doc_type = $_GET['type'] ?? 'invoice';
$edit_id = $_GET['edit'] ?? null;
$document = null;
$document_items = [];

// If editing, get existing document (admin only)
if ($edit_id) {
    if (!isAdmin()) {
        header('Location: documents.php');
        exit;
    }
    $document = $conn->query("SELECT * FROM documents WHERE id = " . intval($edit_id))->fetch_assoc();
    if ($document) {
        $doc_type = $document['document_type'];
        $items_result = $conn->query("SELECT * FROM document_items WHERE document_id = " . intval($edit_id));
        while ($item = $items_result->fetch_assoc()) {
            $document_items[] = $item;
        }
    }
}

// Get company info
$company = $conn->query("SELECT * FROM company_info LIMIT 1")->fetch_assoc();

// Get products for autocomplete
$products = $conn->query("SELECT id, name, price FROM products WHERE status = 'active' ORDER BY name");
$products_list = [];
while ($p = $products->fetch_assoc()) {
    $products_list[] = $p;
}

// Get services for autocomplete
$services = $conn->query("SELECT id, name, price FROM services WHERE status = 'active' ORDER BY name");
$services_list = [];
while ($s = $services->fetch_assoc()) {
    $services_list[] = $s;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name']);
    $client_phone = trim($_POST['client_phone']);
    $client_email = trim($_POST['client_email']);
    $client_address = trim($_POST['client_address']);
    $notes = trim($_POST['notes']);
    $status = $_POST['status'];
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    
    $items = json_decode($_POST['items_json'], true);
    
    if (empty($client_name)) {
        $error = 'Client name is required.';
    } elseif (empty($items)) {
        $error = 'At least one item is required.';
    } else {
        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['total']);
        }
        $tax_amount = floatval($_POST['tax_amount'] ?? 0);
        $discount_amount = floatval($_POST['discount_amount'] ?? 0);
        $total_amount = $subtotal + $tax_amount - $discount_amount;
        
        $conn->begin_transaction();
        
        try {
            if ($edit_id && $document) {
                // Update existing document
                $stmt = $conn->prepare("UPDATE documents SET client_name=?, client_phone=?, client_email=?, client_address=?, subtotal=?, tax_amount=?, discount_amount=?, total_amount=?, notes=?, status=?, valid_until=? WHERE id=?");
                $stmt->bind_param("ssssddddsssi", $client_name, $client_phone, $client_email, $client_address, $subtotal, $tax_amount, $discount_amount, $total_amount, $notes, $status, $valid_until, $edit_id);
                $stmt->execute();
                $doc_id = $edit_id;
                
                // Delete old items
                $conn->query("DELETE FROM document_items WHERE document_id = $doc_id");
            } else {
                // Create new document
                $doc_number = generateDocumentNumber($conn, $doc_type);
                $stmt = $conn->prepare("INSERT INTO documents (document_number, document_type, user_id, client_name, client_phone, client_email, client_address, subtotal, tax_amount, discount_amount, total_amount, notes, status, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissssddddsss", $doc_number, $doc_type, $user['id'], $client_name, $client_phone, $client_email, $client_address, $subtotal, $tax_amount, $discount_amount, $total_amount, $notes, $status, $valid_until);
                $stmt->execute();
                $doc_id = $conn->insert_id;
            }
            
            // Insert items
            foreach ($items as $item) {
                $desc = $item['description'];
                $qty = intval($item['qty']);
                $unit_price = floatval($item['price']);
                $item_total = floatval($item['total']);
                
                $stmt = $conn->prepare("INSERT INTO document_items (document_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isidd", $doc_id, $desc, $qty, $unit_price, $item_total);
                $stmt->execute();
            }
            
            $conn->commit();
            
            header("Location: view_document.php?id=$doc_id&created=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error saving document: ' . $e->getMessage();
        }
    }
}

$type_titles = [
    'invoice' => 'Invoice',
    'quotation' => 'Quotation',
    'receipt' => 'Receipt'
];
$page_title = ($edit_id ? 'Edit ' : 'Create ') . $type_titles[$doc_type];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - AC-TECHNOLOGY</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .document-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .document-form {
                grid-template-columns: 1fr;
            }
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .items-table th,
        .items-table td {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        .items-table th {
            background: var(--light-bg);
            font-weight: 600;
        }
        
        .items-table input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        
        .items-table .qty-col { width: 80px; }
        .items-table .price-col { width: 120px; }
        .items-table .total-col { width: 120px; }
        .items-table .action-col { width: 50px; text-align: center; }
        
        .add-item-btn {
            margin-top: 1rem;
        }
        
        .totals-section {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
        }
        
        .totals-table {
            width: 300px;
        }
        
        .totals-table td {
            padding: 0.5rem;
        }
        
        .totals-table .label {
            text-align: right;
            font-weight: 500;
        }
        
        .totals-table .grand-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .remove-item {
            color: var(--danger-color);
            cursor: pointer;
            font-size: 1.2rem;
        }
        
        .autocomplete-list {
            position: absolute;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }
        
        .autocomplete-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        
        .autocomplete-item:hover {
            background: var(--light-bg);
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
                    <h1><i class="fas fa-file-alt"></i> <?php echo $page_title; ?></h1>
                    <p>Fill in the details below</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" id="documentForm">
                    <input type="hidden" name="items_json" id="itemsJson">
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><i class="fas fa-user"></i> Client Information</h3>
                        </div>
                        <div class="card-body">
                            <div class="document-form">
                                <div class="form-group">
                                    <label>Client Name *</label>
                                    <input type="text" name="client_name" class="form-control" value="<?php echo htmlspecialchars($document['client_name'] ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" name="client_phone" class="form-control" value="<?php echo htmlspecialchars($document['client_phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="client_email" class="form-control" value="<?php echo htmlspecialchars($document['client_email'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <input type="text" name="client_address" class="form-control" value="<?php echo htmlspecialchars($document['client_address'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Items</h3>
                        </div>
                        <div class="card-body">
                            <table class="items-table" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th class="qty-col">QTY</th>
                                        <th>DESCRIPTION</th>
                                        <th class="price-col">UNIT PRICE</th>
                                        <th class="total-col">TOTAL</th>
                                        <th class="action-col"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <!-- Items will be added here dynamically -->
                                </tbody>
                            </table>
                            
                            <button type="button" class="btn btn-primary add-item-btn" onclick="addItem()" style="margin-top: 15px; padding: 12px 25px; font-size: 1rem;">
                                <i class="fas fa-plus-circle"></i> Add Another Item
                            </button>
                            
                            <div class="totals-section">
                                <table class="totals-table">
                                    <tr>
                                        <td class="label">Subtotal:</td>
                                        <td>K<span id="subtotal">0.00</span></td>
                                    </tr>
                                    <tr>
                                        <td class="label">Tax:</td>
                                        <td>
                                            <input type="number" name="tax_amount" id="taxAmount" class="form-control" style="width: 100px;" step="0.01" min="0" value="<?php echo $document['tax_amount'] ?? 0; ?>" onchange="calculateTotals()">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label">Discount:</td>
                                        <td>
                                            <input type="number" name="discount_amount" id="discountAmount" class="form-control" style="width: 100px;" step="0.01" min="0" value="<?php echo $document['discount_amount'] ?? 0; ?>" onchange="calculateTotals()">
                                        </td>
                                    </tr>
                                    <tr class="grand-total">
                                        <td class="label">TOTAL:</td>
                                        <td>K<span id="grandTotal">0.00</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3><i class="fas fa-cog"></i> Document Settings</h3>
                        </div>
                        <div class="card-body">
                            <div class="document-form">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="draft" <?php echo ($document['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="sent" <?php echo ($document['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>Sent</option>
                                        <option value="paid" <?php echo ($document['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="cancelled" <?php echo ($document['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <?php if ($doc_type === 'quotation'): ?>
                                <div class="form-group">
                                    <label>Valid Until</label>
                                    <input type="date" name="valid_until" class="form-control" value="<?php echo $document['valid_until'] ?? date('Y-m-d', strtotime('+30 days')); ?>">
                                </div>
                                <?php endif; ?>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($document['notes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <a href="documents.php?type=<?php echo urlencode($doc_type); ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save <?php echo $type_titles[$doc_type]; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
    <script>
        // Products and services data for autocomplete
        const products = <?php echo json_encode($products_list); ?>;
        const services = <?php echo json_encode($services_list); ?>;
        const allItems = [...products.map(p => ({...p, type: 'product'})), ...services.map(s => ({...s, type: 'service'}))];
        
        // Existing items (for edit mode)
        const existingItems = <?php echo json_encode($document_items); ?>;
        
        let itemCount = 0;
        
        function addItem(description = '', qty = 1, price = 0) {
            itemCount++;
            const tbody = document.getElementById('itemsBody');
            const row = document.createElement('tr');
            row.id = 'item-' + itemCount;
            row.innerHTML = `
                <td class="qty-col">
                    <input type="number" class="item-qty" value="${qty}" min="1" onchange="calculateRowTotal(${itemCount})">
                </td>
                <td style="position: relative;">
                    <input type="text" class="item-desc" value="${description}" placeholder="Type to search products/services..." oninput="showAutocomplete(this, ${itemCount})">
                    <div class="autocomplete-list" id="autocomplete-${itemCount}" style="display: none;"></div>
                </td>
                <td class="price-col">
                    <input type="number" class="item-price" value="${price}" step="0.01" min="0" onchange="calculateRowTotal(${itemCount})">
                </td>
                <td class="total-col">
                    <strong>K<span class="item-total">${(qty * price).toFixed(2)}</span></strong>
                </td>
                <td class="action-col">
                    <span class="remove-item" onclick="removeItem(${itemCount})"><i class="fas fa-times"></i></span>
                </td>
            `;
            tbody.appendChild(row);
            calculateTotals();
        }
        
        function removeItem(id) {
            const row = document.getElementById('item-' + id);
            if (row) {
                row.remove();
                calculateTotals();
            }
        }
        
        function calculateRowTotal(id) {
            const row = document.getElementById('item-' + id);
            if (row) {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const total = qty * price;
                row.querySelector('.item-total').textContent = total.toFixed(2);
                calculateTotals();
            }
        }
        
        function calculateTotals() {
            let subtotal = 0;
            document.querySelectorAll('.item-total').forEach(el => {
                subtotal += parseFloat(el.textContent) || 0;
            });
            
            const tax = parseFloat(document.getElementById('taxAmount').value) || 0;
            const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
            const grandTotal = subtotal + tax - discount;
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
        }
        
        function showAutocomplete(input, rowId) {
            const query = input.value.toLowerCase();
            const list = document.getElementById('autocomplete-' + rowId);
            
            if (query.length < 2) {
                list.style.display = 'none';
                return;
            }
            
            const matches = allItems.filter(item => item.name.toLowerCase().includes(query));
            
            if (matches.length === 0) {
                list.style.display = 'none';
                return;
            }
            
            list.innerHTML = matches.slice(0, 10).map(item => `
                <div class="autocomplete-item" onclick="selectItem(${rowId}, '${item.name.replace(/'/g, "\\'")}', ${item.price})">
                    ${item.name} - K${parseFloat(item.price).toFixed(2)}
                </div>
            `).join('');
            list.style.display = 'block';
        }
        
        function selectItem(rowId, name, price) {
            const row = document.getElementById('item-' + rowId);
            row.querySelector('.item-desc').value = name;
            row.querySelector('.item-price').value = price;
            document.getElementById('autocomplete-' + rowId).style.display = 'none';
            calculateRowTotal(rowId);
        }
        
        // Hide autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.classList.contains('item-desc')) {
                document.querySelectorAll('.autocomplete-list').forEach(list => {
                    list.style.display = 'none';
                });
            }
        });
        
        // Form submission
        document.getElementById('documentForm').addEventListener('submit', function(e) {
            const items = [];
            document.querySelectorAll('#itemsBody tr').forEach(row => {
                const desc = row.querySelector('.item-desc').value;
                const qty = row.querySelector('.item-qty').value;
                const price = row.querySelector('.item-price').value;
                const total = row.querySelector('.item-total').textContent;
                
                if (desc && qty && price) {
                    items.push({
                        description: desc,
                        qty: parseInt(qty),
                        price: parseFloat(price),
                        total: parseFloat(total)
                    });
                }
            });
            
            if (items.length === 0) {
                e.preventDefault();
                alert('Please add at least one item.');
                return;
            }
            
            document.getElementById('itemsJson').value = JSON.stringify(items);
        });
        
        // Initialize
        if (existingItems.length > 0) {
            existingItems.forEach(item => {
                addItem(item.description, item.quantity, item.unit_price);
            });
        } else {
            addItem();
        }
    </script>
</body>
</html>
