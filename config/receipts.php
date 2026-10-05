<?php
// Automatic receipts: every completed payment (online order, POS sale, repair payment) gets exactly
// one receipt document (documents.document_type = 'receipt'), linked by source_type/source_id.
require_once __DIR__ . '/email.php';

const PAYMENT_METHODS = ['cash' => 'Cash', 'card' => 'Card', 'mobile_money' => 'Mobile Money'];

function paymentMethodLabel($method) {
    return PAYMENT_METHODS[$method] ?? ucfirst(str_replace('_', ' ', (string)$method));
}

function getReceiptForSource($conn, $source_type, $source_id) {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE document_type = 'receipt' AND source_type = ? AND source_id = ?");
    $stmt->bind_param("si", $source_type, $source_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function nextReceiptNumber($conn) {
    $prefix = 'REC-' . date('Ymd') . '-';
    $row = $conn->query("SELECT MAX(CAST(SUBSTRING(document_number, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS n
                         FROM documents WHERE document_number LIKE '" . $prefix . "%'")->fetch_assoc();
    return $prefix . str_pad(((int)$row['n']) + 1, 4, '0', STR_PAD_LEFT);
}

// Builds receipt data from a paid sale or repair. Returns null if the source is not paid.
function buildReceiptData($conn, $source_type, $source_id) {
    if ($source_type === 'sale') {
        $stmt = $conn->prepare("SELECT s.*, u.email AS account_email, u.full_name AS account_name
                                FROM sales s LEFT JOIN users u ON u.id = s.customer_id WHERE s.id = ?");
        $stmt->bind_param("i", $source_id);
        $stmt->execute();
        $sale = $stmt->get_result()->fetch_assoc();
        if (!$sale || ($sale['payment_status'] ?? 'paid') !== 'paid') {
            return null;
        }
        $items = $conn->query("SELECT si.quantity, si.unit_price, si.total_price, COALESCE(p.name, sv.name, si.item_type) AS name
                               FROM sale_items si
                               LEFT JOIN products p ON si.product_id = p.id
                               LEFT JOIN services sv ON si.service_id = sv.id
                               WHERE si.sale_id = " . (int)$source_id)->fetch_all(MYSQLI_ASSOC);
        return [
            'client_name' => $sale['customer_name'] ?: ($sale['account_name'] ?: 'Walk-in Customer'),
            'client_phone' => $sale['customer_phone'] ?? '',
            'client_email' => $sale['customer_email'] ?: ($sale['account_email'] ?? ''),
            'customer_id' => $sale['customer_id'] ?? null,
            'payment_method' => $sale['payment_method'],
            'total' => (float)$sale['total_amount'],
            'notes' => 'Payment for order/invoice ' . $sale['invoice_number'],
            'items' => array_map(fn($i) => [$i['name'], (int)$i['quantity'], (float)$i['unit_price'], (float)$i['total_price']], $items),
        ];
    }

    if ($source_type === 'repair') {
        $repair = getRepairForNotification($conn, $source_id);
        if (!$repair || $repair['payment_status'] !== 'paid' || (float)$repair['final_cost'] <= 0) {
            return null;
        }
        return [
            'client_name' => $repair['notify_name'],
            'client_phone' => $repair['customer_phone'] ?? '',
            'client_email' => $repair['notify_email'] ?? '',
            'customer_id' => $repair['customer_id'],
            'payment_method' => $repair['payment_method'],
            'total' => (float)$repair['final_cost'],
            'notes' => 'Payment for repair ticket ' . $repair['ticket_number'],
            'items' => [['Repair service - Ticket ' . $repair['ticket_number'] . ' (' . getRepairDeviceInfo($repair) . ')', 1, (float)$repair['final_cost'], (float)$repair['final_cost']]],
        ];
    }

    return null;
}

/**
 * Create the receipt for a payment (idempotent). Returns the receipt document id, or null if unpaid.
 */
function createReceipt($conn, $source_type, $source_id) {
    $existing = getReceiptForSource($conn, $source_type, $source_id);
    if ($existing) {
        return (int)$existing['id'];
    }
    $data = buildReceiptData($conn, $source_type, $source_id);
    if (!$data) {
        return null;
    }

    $user_id = $_SESSION['user_id'] ?? null;
    $conn->begin_transaction();
    try {
        $number = nextReceiptNumber($conn);
        $stmt = $conn->prepare("INSERT INTO documents (document_number, document_type, user_id, client_name, client_phone, client_email, subtotal, tax_amount, discount_amount, total_amount, notes, status, source_type, source_id, customer_id, payment_method)
                                VALUES (?, 'receipt', ?, ?, ?, ?, ?, 0, 0, ?, ?, 'paid', ?, ?, ?, ?)");
        $stmt->bind_param("sisssddssiis", $number, $user_id, $data['client_name'], $data['client_phone'], $data['client_email'],
                          $data['total'], $data['total'], $data['notes'], $source_type, $source_id, $data['customer_id'], $data['payment_method']);
        $stmt->execute();
        $receipt_id = $conn->insert_id;

        $item_stmt = $conn->prepare("INSERT INTO document_items (document_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($data['items'] as [$description, $quantity, $unit_price, $total_price]) {
            $item_stmt->bind_param("isidd", $receipt_id, $description, $quantity, $unit_price, $total_price);
            $item_stmt->execute();
        }
        $conn->commit();
        return $receipt_id;
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        // Another request created it at the same time
        $existing = getReceiptForSource($conn, $source_type, $source_id);
        if ($existing) {
            return (int)$existing['id'];
        }
        throw $e;
    }
}

function getReceipt($conn, $receipt_id) {
    $receipt = $conn->query("SELECT * FROM documents WHERE id = " . (int)$receipt_id . " AND document_type = 'receipt'")->fetch_assoc();
    if ($receipt) {
        $receipt['items'] = $conn->query("SELECT * FROM document_items WHERE document_id = " . (int)$receipt_id . " ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    }
    return $receipt;
}

/**
 * Email the receipt to the client (queued, sent in the background)
 */
function emailReceipt($conn, $receipt_id) {
    $receipt = getReceipt($conn, $receipt_id);
    if (!$receipt || !filter_var($receipt['client_email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $rows = '';
    foreach ($receipt['items'] as $item) {
        $rows .= "<tr>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'>" . e($item['description']) . "</td>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: center;'>" . (int)$item['quantity'] . "</td>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right;'>K" . number_format($item['unit_price'], 2) . "</td>
            <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right;'>K" . number_format($item['total_price'], 2) . "</td>
        </tr>";
    }

    $content = "
        <p>Hello <strong>" . e($receipt['client_name']) . "</strong>,</p>
        <p>Thank you for your payment. Here is your receipt.</p>
        <div style='background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #e2e8f0; margin-bottom: 15px;'>
            <p style='margin: 4px 0;'><strong>Receipt No:</strong> " . e($receipt['document_number']) . "</p>
            <p style='margin: 4px 0;'><strong>Date:</strong> " . date('M d, Y H:i', strtotime($receipt['created_at'])) . "</p>
            <p style='margin: 4px 0;'><strong>Payment Method:</strong> " . e(paymentMethodLabel($receipt['payment_method'])) . "</p>
            <p style='margin: 4px 0;'><strong>For:</strong> " . e($receipt['notes']) . "</p>
        </div>
        <table style='width: 100%; border-collapse: collapse; background: #fff;'>
            <thead>
                <tr style='background: #1a365d; color: #fff;'>
                    <th style='padding: 8px; text-align: left;'>Description</th>
                    <th style='padding: 8px;'>Qty</th>
                    <th style='padding: 8px; text-align: right;'>Unit Price</th>
                    <th style='padding: 8px; text-align: right;'>Amount</th>
                </tr>
            </thead>
            <tbody>$rows</tbody>
            <tfoot>
                <tr>
                    <td colspan='3' style='padding: 10px; text-align: right;'><strong>Total Paid</strong></td>
                    <td style='padding: 10px; text-align: right;'><strong>K" . number_format($receipt['total_amount'], 2) . "</strong></td>
                </tr>
            </tfoot>
        </table>
        <p style='text-align: center; margin-top: 25px;'>
            <a href='" . e(SYSTEM_URL . '/receipt.php?id=' . $receipt['id']) . "' style='display: inline-block; background: #48bb78; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>View &amp; Print Receipt</a>
        </p>";

    return queueEmail($receipt['client_email'], 'Payment Receipt ' . $receipt['document_number'] . ' - ' . companyName(),
                      emailLayout('Payment Receipt', 'Receipt ' . $receipt['document_number'], $content));
}

// Create the receipt for a payment and email it to the client. A receipt is emailed automatically
// only when it is first created (staff can resend from the receipt page).
function issueReceipt($conn, $source_type, $source_id) {
    $existing = getReceiptForSource($conn, $source_type, $source_id);
    if ($existing) {
        return (int)$existing['id'];
    }
    $receipt_id = createReceipt($conn, $source_type, $source_id);
    if ($receipt_id) {
        emailReceipt($conn, $receipt_id);
    }
    return $receipt_id;
}
