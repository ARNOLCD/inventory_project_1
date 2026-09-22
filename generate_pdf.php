<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$doc_id = intval($_GET['id'] ?? 0);

if (!$doc_id) {
    die('Invalid document ID');
}

// Get document
$document = $conn->query("
    SELECT d.*, u.full_name as created_by 
    FROM documents d 
    LEFT JOIN users u ON d.user_id = u.id 
    WHERE d.id = $doc_id
")->fetch_assoc();

if (!$document) {
    die('Document not found');
}

// Get document items
$items_result = $conn->query("SELECT * FROM document_items WHERE document_id = $doc_id ORDER BY id ASC");
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Get company info
$company = $conn->query("SELECT * FROM company_info LIMIT 1")->fetch_assoc();

$type_titles = [
    'invoice' => 'INVOICE',
    'quotation' => 'QUOTATION',
    'receipt' => 'RECEIPT'
];

// Generate HTML for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $type_titles[$document['document_type']] . ' - ' . htmlspecialchars($document['document_number']) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px solid #1a365d; }
        .logo-section { display: flex; align-items: center; gap: 10px; }
        .logo-section img { height: 60px; }
        .company-name { font-size: 20px; font-weight: bold; color: #1a365d; }
        .tagline { font-size: 10px; color: #666; font-style: italic; }
        
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 24px; color: #1a365d; margin: 0; }
        .doc-number { font-size: 12px; color: #666; }
        
        .company-info { background: #f0f4f8; padding: 10px; margin-bottom: 15px; font-size: 11px; }
        .company-info p { margin: 3px 0; }
        
        .brands { text-align: center; padding: 10px; background: #f8f9fa; margin-bottom: 15px; }
        .brands span { margin: 0 15px; font-weight: bold; font-size: 14px; }
        
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-left, .info-right { display: table-cell; width: 50%; vertical-align: top; }
        .info-right { text-align: right; }
        
        .bank-title { color: #e53e3e; font-weight: bold; font-size: 11px; margin-bottom: 5px; }
        .info-label { font-weight: bold; }
        
        table.items { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.items th { background: #1a365d; color: white; padding: 10px; text-align: left; font-weight: bold; }
        table.items td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
        table.items tr:nth-child(even) { background: #f8f9fa; }
        table.items .text-right { text-align: right; }
        table.items .text-center { text-align: center; }
        
        .total-row { background: #1a365d !important; color: white; font-weight: bold; }
        .total-row td { font-size: 14px; }
        
        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .signature-section { margin-top: 30px; }
        .signature-line { display: inline-block; width: 200px; border-top: 1px solid #333; padding-top: 5px; font-size: 11px; }
        
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="container">
        <table style="width: 100%; margin-bottom: 20px; border-bottom: 3px solid #1a365d; padding-bottom: 15px;">
            <tr>
                <td style="vertical-align: top;">
                    <div style="font-size: 24px; font-weight: bold; color: #1a365d;">AC-TECHNOLOGY LIMITED</div>
                    <div style="font-size: 10px; color: #666; font-style: italic;">SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div style="font-size: 28px; font-weight: bold; color: #1a365d;">' . $type_titles[$document['document_type']] . '</div>
                    <div style="font-size: 12px; color: #666;">No. ' . htmlspecialchars($document['document_number']) . '</div>
                </td>
            </tr>
        </table>
        
        <div class="company-info">
            <p><strong>UNZA MAIN CAMPUS.</strong> &nbsp;&nbsp;&nbsp; EMAIL: ' . htmlspecialchars($company['email'] ?? 'info@actechnology.co.zm') . ', TPIN #:' . htmlspecialchars($company['tpin'] ?? '2002530937') . '</p>
            <p><strong>NEXT TO THE POST OFFICE.</strong> &nbsp;&nbsp;&nbsp; Mobile: ' . htmlspecialchars($company['phone'] ?? '0979145428') . ', ' . htmlspecialchars($company['mobile'] ?? '0968745131') . '</p>
            <p>' . htmlspecialchars($company['address'] ?? '+260974728675.') . '</p>
        </div>
        
        <div class="brands">
            <span style="color: #0096D6;">hp</span>
            <span style="color: #E2231A;">Lenovo</span>
            <span style="color: #000;">DELL</span>
            <span style="color: #83B81A;">acer</span>
            <span style="color: #00A4EF;">Microsoft</span>
        </div>
        
        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td style="vertical-align: top; width: 50%;">
                    <div class="bank-title">BANK DETAILS</div>
                    <p><strong>BANK:</strong> ' . htmlspecialchars($company['bank_name'] ?? 'STANBIC') . '</p>
                    <p><strong>ACCOUNT NAME:</strong> ' . htmlspecialchars($company['account_name'] ?? 'AC-TECHNOLOGY') . '</p>
                    <p><strong>Account No:</strong> ' . htmlspecialchars($company['account_number'] ?? '6292984114') . '</p>
                    <p><strong>BRANCH:</strong> ' . htmlspecialchars($company['branch'] ?? '260006') . '</p>
                    <p><strong>PAY TO SALE:</strong> ' . htmlspecialchars($company['pay_to_sale'] ?? '0973071800') . '</p>
                </td>
                <td style="vertical-align: top; text-align: right; width: 50%;">
                    <p><strong>DATE:</strong> ' . date('d/m/Y', strtotime($document['created_at'])) . '</p>
                    <p><strong>CLIENT NAME:</strong> ' . htmlspecialchars($document['client_name']) . '</p>';

if ($document['client_phone']) {
    $html .= '<p><strong>PHONE:</strong> ' . htmlspecialchars($document['client_phone']) . '</p>';
}
if ($document['client_address']) {
    $html .= '<p><strong>ADDRESS:</strong> ' . htmlspecialchars($document['client_address']) . '</p>';
}

$html .= '
                </td>
            </tr>
        </table>
        
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 60px;">QTY</th>
                    <th>DESCRIPTION</th>
                    <th style="width: 100px; text-align: right;">UNIT PRICE</th>
                    <th style="width: 100px; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>';

foreach ($items as $item) {
    $html .= '
                <tr>
                    <td class="text-center">' . $item['quantity'] . '</td>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td class="text-right">K' . number_format($item['unit_price'], 2) . '</td>
                    <td class="text-right">K' . number_format($item['total_price'], 2) . '</td>
                </tr>';
}

// Add empty row for spacing
$html .= '<tr><td colspan="4">&nbsp;</td></tr>';

// Add totals if there's tax or discount
if ($document['tax_amount'] > 0 || $document['discount_amount'] > 0) {
    $html .= '
                <tr>
                    <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                    <td class="text-right">K' . number_format($document['subtotal'], 2) . '</td>
                </tr>';
    
    if ($document['tax_amount'] > 0) {
        $html .= '
                <tr>
                    <td colspan="3" class="text-right">Tax:</td>
                    <td class="text-right">K' . number_format($document['tax_amount'], 2) . '</td>
                </tr>';
    }
    
    if ($document['discount_amount'] > 0) {
        $html .= '
                <tr>
                    <td colspan="3" class="text-right">Discount:</td>
                    <td class="text-right">-K' . number_format($document['discount_amount'], 2) . '</td>
                </tr>';
    }
}

$html .= '
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL</td>
                    <td class="text-right">K' . number_format($document['total_amount'], 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            <p><strong>AC-TECHNOLOGY LIMITED</strong></p>';

if ($document['notes']) {
    $html .= '<p style="margin-top: 10px;"><strong>Notes:</strong> ' . nl2br(htmlspecialchars($document['notes'])) . '</p>';
}

$html .= '
            <div class="signature-section">
                <table style="width: 100%; margin-top: 40px;">
                    <tr>
                        <td style="width: 50%;">
                            <div style="border-top: 1px solid #333; width: 200px; padding-top: 5px;">
                                Prepared by: ' . htmlspecialchars($document['created_by'] ?? '') . '
                            </div>
                        </td>
                        <td style="width: 50%; text-align: right;">
                            <div style="border-top: 1px solid #333; width: 200px; padding-top: 5px; display: inline-block;">
                                Customer Signature
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>';

// Check if TCPDF or FPDF is available, otherwise use browser print
// For simplicity, we'll use browser-based PDF generation

// Set headers for PDF-like behavior (will prompt print dialog)
$filename = $type_titles[$document['document_type']] . '_' . $document['document_number'] . '.pdf';

// Try to use dompdf if available, otherwise output HTML for printing
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
    
    if (class_exists('Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}

// Fallback: Output HTML with print styles and auto-print
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $type_titles[$document['document_type']] . ' - ' . htmlspecialchars($document['document_number']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: #f5f5f5; }
        .print-container { max-width: 800px; margin: 20px auto; padding: 40px; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .no-print { text-align: center; margin-bottom: 20px; padding: 15px; background: #1a365d; }
        .no-print button { padding: 10px 30px; font-size: 16px; cursor: pointer; margin: 0 10px; border: none; border-radius: 5px; }
        .no-print .print-btn { background: #38a169; color: white; }
        .no-print .back-btn { background: #718096; color: white; }
        
        .header-table { width: 100%; margin-bottom: 20px; border-bottom: 3px solid #1a365d; padding-bottom: 15px; }
        .company-name { font-size: 24px; font-weight: bold; color: #1a365d; }
        .tagline { font-size: 10px; color: #666; font-style: italic; }
        .doc-type { font-size: 28px; font-weight: bold; color: #1a365d; }
        .doc-num { font-size: 12px; color: #666; }
        
        .company-info { background: #f0f4f8; padding: 10px; margin-bottom: 15px; font-size: 11px; }
        .company-info p { margin: 3px 0; }
        
        .brands { text-align: center; padding: 10px; background: #f8f9fa; margin-bottom: 15px; }
        .brands span { margin: 0 15px; font-weight: bold; font-size: 16px; }
        
        .info-table { width: 100%; margin-bottom: 15px; }
        .bank-title { color: #e53e3e; font-weight: bold; font-size: 11px; margin-bottom: 5px; }
        
        table.items { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table.items th { background: #1a365d; color: white; padding: 10px; text-align: left; font-weight: bold; }
        table.items td { padding: 10px; border-bottom: 1px solid #e2e8f0; }
        table.items tr:nth-child(even) { background: #f8f9fa; }
        table.items .text-right { text-align: right; }
        table.items .text-center { text-align: center; }
        
        .total-row { background: #1a365d !important; color: white !important; font-weight: bold; }
        .total-row td { font-size: 14px; color: white !important; }
        
        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .sig-table { width: 100%; margin-top: 40px; }
        .sig-line { border-top: 1px solid #333; width: 200px; padding-top: 5px; font-size: 11px; }
        
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .print-container { box-shadow: none; margin: 0; padding: 20px; }
            table.items th { background: #1a365d !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row { background: #1a365d !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row td { color: white !important; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="back-btn" onclick="window.history.back()">← Back</button>
        <button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>
    </div>
    
    <div class="print-container">
        <table class="header-table">
            <tr>
                <td style="vertical-align: top;">
                    <div class="company-name">AC-TECHNOLOGY LIMITED</div>
                    <div class="tagline">SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS</div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="doc-type"><?php echo $type_titles[$document['document_type']]; ?></div>
                    <div class="doc-num">No. <?php echo htmlspecialchars($document['document_number']); ?></div>
                </td>
            </tr>
        </table>
        
        <div class="company-info">
            <p><strong>UNZA MAIN CAMPUS.</strong> &nbsp;&nbsp;&nbsp; EMAIL: <?php echo htmlspecialchars($company['email'] ?? 'info@actechnology.co.zm'); ?>, TPIN #:<?php echo htmlspecialchars($company['tpin'] ?? '2002530937'); ?></p>
            <p><strong>NEXT TO THE POST OFFICE.</strong> &nbsp;&nbsp;&nbsp; Mobile: <?php echo htmlspecialchars($company['phone'] ?? '0979145428'); ?>, <?php echo htmlspecialchars($company['mobile'] ?? '0968745131'); ?></p>
            <p><?php echo htmlspecialchars($company['address'] ?? '+260974728675.'); ?></p>
        </div>
        
        <div class="brands">
            <span style="color: #0096D6;">hp</span>
            <span style="color: #E2231A;">Lenovo</span>
            <span style="color: #000;">DELL</span>
            <span style="color: #83B81A;">acer</span>
            <span style="color: #00A4EF;">Microsoft</span>
        </div>
        
        <table class="info-table">
            <tr>
                <td style="vertical-align: top; width: 50%;">
                    <div class="bank-title">BANK DETAILS</div>
                    <p><strong>BANK:</strong> <?php echo htmlspecialchars($company['bank_name'] ?? 'STANBIC'); ?></p>
                    <p><strong>ACCOUNT NAME:</strong> <?php echo htmlspecialchars($company['account_name'] ?? 'AC-TECHNOLOGY'); ?></p>
                    <p><strong>Account No:</strong> <?php echo htmlspecialchars($company['account_number'] ?? '6292984114'); ?></p>
                    <p><strong>BRANCH:</strong> <?php echo htmlspecialchars($company['branch'] ?? '260006'); ?></p>
                    <p><strong>PAY TO SALE:</strong> <?php echo htmlspecialchars($company['pay_to_sale'] ?? '0973071800'); ?></p>
                </td>
                <td style="vertical-align: top; text-align: right; width: 50%;">
                    <p><strong>DATE:</strong> <?php echo date('d/m/Y', strtotime($document['created_at'])); ?></p>
                    <p><strong>CLIENT NAME:</strong> <?php echo htmlspecialchars($document['client_name']); ?></p>
                    <?php if ($document['client_phone']): ?>
                        <p><strong>PHONE:</strong> <?php echo htmlspecialchars($document['client_phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($document['client_address']): ?>
                        <p><strong>ADDRESS:</strong> <?php echo htmlspecialchars($document['client_address']); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 60px;">QTY</th>
                    <th>DESCRIPTION</th>
                    <th style="width: 100px; text-align: right;">UNIT PRICE</th>
                    <th style="width: 100px; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-right">K<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-right">K<?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                
                <tr><td colspan="4">&nbsp;</td></tr>
                
                <?php if ($document['tax_amount'] > 0 || $document['discount_amount'] > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
                        <td class="text-right">K<?php echo number_format($document['subtotal'], 2); ?></td>
                    </tr>
                    <?php if ($document['tax_amount'] > 0): ?>
                        <tr>
                            <td colspan="3" class="text-right">Tax:</td>
                            <td class="text-right">K<?php echo number_format($document['tax_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($document['discount_amount'] > 0): ?>
                        <tr>
                            <td colspan="3" class="text-right">Discount:</td>
                            <td class="text-right">-K<?php echo number_format($document['discount_amount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
                
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL</td>
                    <td class="text-right">K<?php echo number_format($document['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="footer">
            <p><strong>AC-TECHNOLOGY LIMITED</strong></p>
            
            <?php if ($document['notes']): ?>
                <p style="margin-top: 10px;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($document['notes'])); ?></p>
            <?php endif; ?>
            
            <table class="sig-table">
                <tr>
                    <td style="width: 50%;">
                        <div class="sig-line">Prepared by: <?php echo htmlspecialchars($document['created_by'] ?? ''); ?></div>
                    </td>
                    <td style="width: 50%; text-align: right;">
                        <div class="sig-line" style="display: inline-block;">Customer Signature</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    
    <script>
        // Tip: Use browser's "Save as PDF" option in print dialog
    </script>
</body>
</html>
