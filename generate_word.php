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

$filename = $type_titles[$document['document_type']] . '_' . $document['document_number'] . '.doc';
$logoUrl = appUrl() . '/' . companyLogo();

// Set headers for Word document download
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Generate Word-compatible HTML
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <title><?php echo $type_titles[$document['document_type']] . ' - ' . htmlspecialchars($document['document_number']); ?></title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        .header-table {
            margin-bottom: 15pt;
            border-bottom: 3pt solid #1a365d;
            padding-bottom: 10pt;
        }
        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1a365d;
        }
        .tagline {
            font-size: 9pt;
            color: #666;
            font-style: italic;
        }
        .doc-type {
            font-size: 22pt;
            font-weight: bold;
            color: #1a365d;
            text-align: right;
        }
        .doc-num {
            font-size: 10pt;
            color: #666;
            text-align: right;
        }
        .company-info {
            background-color: #f0f4f8;
            padding: 8pt;
            margin-bottom: 12pt;
            font-size: 9pt;
        }
        .brands {
            text-align: center;
            padding: 8pt;
            background-color: #f8f9fa;
            margin-bottom: 12pt;
            font-size: 12pt;
            font-weight: bold;
        }
        .bank-title {
            color: #e53e3e;
            font-weight: bold;
            font-size: 9pt;
            margin-bottom: 5pt;
        }
        .items-table {
            margin: 12pt 0;
        }
        .items-table th {
            background-color: #1a365d;
            color: white;
            padding: 8pt;
            text-align: left;
            font-weight: bold;
        }
        .items-table td {
            padding: 8pt;
            border-bottom: 1pt solid #e2e8f0;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background-color: #1a365d;
            color: white;
            font-weight: bold;
        }
        .total-row td {
            font-size: 12pt;
            color: white;
        }
        .footer {
            margin-top: 15pt;
            padding-top: 10pt;
            border-top: 1pt solid #e2e8f0;
        }
        .sig-line {
            border-top: 1pt solid #333;
            width: 150pt;
            padding-top: 5pt;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <?php echo documentBanner($logoUrl); ?>

    <?php echo documentContactInfo(); ?>

    <?php echo documentBrands(appUrl() . '/assets/images/doc-brands.png'); ?>

    <?php echo documentTitle($type_titles[$document['document_type']], $document['document_number']); ?>

    <table style="margin-bottom: 12pt;">
        <tr>
            <td style="vertical-align: top; width: 50%;">
                <?php echo documentBankDetails(); ?>
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
    
    <table class="items-table" border="1" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th>DESCRIPTION</th>
                <th style="width: 50pt;" class="text-center">QTY</th>
                <th style="width: 80pt;" class="text-right">UNIT PRICE</th>
                <th style="width: 80pt;" class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
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
        <p style="text-align: center;"><strong><?php echo e(companyName()); ?></strong></p>

        <?php if ($document['notes']): ?>
            <p style="margin-top: 8pt;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($document['notes'])); ?></p>
        <?php endif; ?>

        <table style="margin-top: 30pt; width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <div class="sig-line">Prepared by: <?php echo htmlspecialchars($document['created_by'] ?? ''); ?></div>
                </td>
                <td style="width: 50%; text-align: right;">
                    <div class="sig-line" style="display: inline-block;">SIGNATURE</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
