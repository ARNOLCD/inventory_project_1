<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireStaff();

$user = getCurrentUser();
$doc_id = intval($_GET['id'] ?? 0);

if (!$doc_id) {
    header('Location: documents.php');
    exit();
}

// Get document
$document = $conn->query("
    SELECT d.*, u.full_name as created_by 
    FROM documents d 
    LEFT JOIN users u ON d.user_id = u.id 
    WHERE d.id = $doc_id
")->fetch_assoc();

if (!$document) {
    header('Location: documents.php');
    exit();
}

// Get document items
$items = $conn->query("SELECT * FROM document_items WHERE document_id = $doc_id ORDER BY id ASC");

// Get company info
$company = $conn->query("SELECT * FROM company_info LIMIT 1")->fetch_assoc();

$type_titles = [
    'invoice' => 'INVOICE',
    'quotation' => 'QUOTATION',
    'receipt' => 'RECEIPT'
];

$created = isset($_GET['created']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $type_titles[$document['document_type']]; ?> <?php echo htmlspecialchars($document['document_number']); ?> - Sims-Tech Zambia</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; }
        
        .document-preview {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 0;
            overflow: hidden;
        }
        
        .doc-header-wave {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 50%, #3182ce 100%);
            padding: 20px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .doc-header-wave::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        
        .doc-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        
        .doc-logo {
            background: white;
            padding: 10px;
            border-radius: 8px;
            flex-shrink: 0;
        }
        
        .doc-logo img {
            height: 70px;
            width: auto;
            display: block;
        }
        
        .doc-logo-text {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3a5f;
            line-height: 1;
        }
        
        .doc-logo-text span {
            color: #e53e3e;
        }
        
        .doc-company-title {
            color: white;
        }
        
        .doc-company-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .doc-company-title p {
            font-size: 0.75rem;
            margin: 5px 0 0 0;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .doc-body {
            padding: 30px;
        }
        
        .contact-info {
            font-size: 0.85rem;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .contact-info p {
            margin: 3px 0;
        }
        
        .contact-info strong {
            color: #1e3a5f;
        }
        
        .contact-info a {
            color: #3182ce;
            text-decoration: none;
        }
        
        .brand-logos {
            display: flex;
            justify-content: flex-start;
            gap: 25px;
            margin: 15px 0 20px 0;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .brand-logo {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .brand-hp { color: #0096D6; font-style: italic; }
        .brand-lenovo { color: #E2231A; }
        .brand-dell { color: #007DB8; font-weight: 800; }
        .brand-acer { color: #83B81A; }
        
        .doc-type-header {
            text-align: center;
            margin: 20px 0;
            padding: 10px 0;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .doc-type-header h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #e53e3e;
            font-weight: 600;
        }
        
        .doc-type-header span {
            color: #1e3a5f;
            font-size: 0.9rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 25px;
        }
        
        .bank-section h4 {
            color: #e53e3e;
            font-size: 0.85rem;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            border-bottom: 2px solid #e53e3e;
            padding-bottom: 5px;
            display: inline-block;
        }
        
        .bank-section p {
            margin: 4px 0;
            font-size: 0.85rem;
        }
        
        .bank-section strong {
            color: #1e3a5f;
        }
        
        .client-section {
            text-align: right;
        }
        
        .client-section p {
            margin: 4px 0;
            font-size: 0.9rem;
        }
        
        .client-section strong {
            color: #1e3a5f;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border: 1px solid #1e3a5f;
        }
        
        .items-table th {
            background: white;
            color: #1e3a5f;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #1e3a5f;
            font-size: 0.9rem;
        }
        
        .items-table td {
            padding: 12px;
            border: 1px solid #1e3a5f;
            font-size: 0.9rem;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        .total-row td {
            font-weight: 600;
            background: #f8f9fa;
        }
        
        .doc-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .doc-footer p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .signature-area {
            margin-top: 40px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            width: 250px;
            padding-top: 5px;
            font-size: 0.85rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        @media print {
            .dashboard-wrapper { display: block; }
            .sidebar, .top-header, .action-buttons, .page-header { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .dashboard-content { padding: 0 !important; }
            .document-preview { box-shadow: none; max-width: 100%; }
        }
        
        @media (max-width: 768px) {
            .doc-header-content { flex-direction: column; text-align: center; }
            .info-grid { grid-template-columns: 1fr; }
            .client-section { text-align: left; }
            .doc-body { padding: 15px; }
        }
        
        .document-title h1 {
            font-size: 2rem;
            color: #1a365d;
            margin: 0;
        }
        
        .document-title .doc-number {
            font-size: 1rem;
            color: #666;
        }
        
        .company-details {
            background: #f0f4f8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }
        
        .company-details p {
            margin: 5px 0;
        }
        
        .brand-logos {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .brand-logos img {
            height: 40px;
            width: auto;
            filter: grayscale(0.3);
        }
        
        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 25px;
        }
        
        .bank-details h4,
        .client-details h4 {
            color: #1a365d;
            font-size: 0.9rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .bank-details p,
        .client-details p {
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .date-info {
            text-align: right;
        }
        
        .date-info p {
            margin: 5px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        
        .items-table th {
            background: #1a365d;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        .totals-row td {
            border-top: 2px solid #1a365d;
            font-weight: 600;
        }
        
        .grand-total-row {
            background: #1a365d !important;
            color: white;
        }
        
        .grand-total-row td {
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .document-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            padding-top: 5px;
            font-size: 0.85rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        @media print {
            .dashboard-wrapper { display: block; }
            .sidebar, .top-header, .action-buttons, .page-header { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .dashboard-content { padding: 0 !important; }
            .document-preview { box-shadow: none; max-width: 100%; }
        }
        
        @media (max-width: 768px) {
            .document-header {
                flex-direction: column;
                gap: 1rem;
            }
            .document-title {
                text-align: left;
            }
            .info-section {
                grid-template-columns: 1fr;
            }
            .document-preview {
                padding: 20px;
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
                    <h1><i class="fas fa-file-alt"></i> View <?php echo ucfirst($document['document_type']); ?></h1>
                </div>
                
                <?php if ($created): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Document created successfully!</div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="documents.php?type=<?php echo urlencode($document['document_type']); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to <?php echo ucfirst($document['document_type']); ?>s
                    </a>
                    <a href="create_document.php?edit=<?php echo $doc_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="generate_pdf.php?id=<?php echo $doc_id; ?>" class="btn btn-danger" target="_blank">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                    <a href="generate_word.php?id=<?php echo $doc_id; ?>" class="btn btn-info">
                        <i class="fas fa-file-word"></i> Download Word
                    </a>
                    <button onclick="window.print()" class="btn btn-success">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                
                <!-- Document Preview -->
                <div class="document-preview" id="documentContent">
                    <!-- Blue Wave Header -->
                    <div class="doc-header-wave">
                        <div class="doc-header-content">
                            <div class="doc-company-title">
                                <h1>Sims-Tech Zambia LIMITED</h1>
                                <p>SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS</p>
                            </div>
                            <div class="doc-logo">
                                <img src="assets/images/logo.png" alt="Sims-Tech Zambia Logo" onerror="this.parentElement.innerHTML='<div class=\'doc-logo-text\'>AC<span>TECH</span></div>'">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Body -->
                    <div class="doc-body">
                        <!-- Contact Information -->
                        <div class="contact-info">
                            <p><strong>UNZA MAIN CAMPUS.</strong> &nbsp;&nbsp;&nbsp; EMAIL: <a href="mailto:<?php echo htmlspecialchars($company['email'] ?? 'info@actechnology.co.zm'); ?>"><?php echo htmlspecialchars($company['email'] ?? 'info@actechnology.co.zm'); ?></a>, TPIN #:<?php echo htmlspecialchars($company['tpin'] ?? '2002530937'); ?></p>
                            <p><strong>NEXT TO THE POST OFFICE.</strong> &nbsp;&nbsp;&nbsp; Mobile: <?php echo htmlspecialchars($company['phone'] ?? '0979145428'); ?>, <?php echo htmlspecialchars($company['mobile'] ?? '0968745131'); ?></p>
                            <p><?php echo htmlspecialchars($company['address'] ?? '+260974728675'); ?></p>
                        </div>
                        
                                                
                        <!-- Document Type Header -->
                        <div class="doc-type-header">
                            <h2><?php echo $type_titles[$document['document_type']]; ?> <span>No. <?php echo htmlspecialchars($document['document_number']); ?></span></h2>
                        </div>
                        
                        <!-- Info Grid: Bank Details & Client Info -->
                        <div class="info-grid">
                            <div class="bank-section">
                                <h4>BANK DETAILS</h4>
                                <p><strong>BANK:</strong> <?php echo htmlspecialchars($company['bank_name'] ?? 'STANBIC'); ?></p>
                                <p><strong>ACCOUNT NAME:</strong> <?php echo htmlspecialchars($company['account_name'] ?? 'Sims-Tech Zambia'); ?></p>
                                <p><strong>Account No:</strong> <?php echo htmlspecialchars($company['account_number'] ?? '6292984114'); ?></p>
                                <p><strong>BRANCH:</strong> <?php echo htmlspecialchars($company['branch'] ?? '260006'); ?></p>
                                <p><strong>PAY TO SALE:</strong> <?php echo htmlspecialchars($company['pay_to_sale'] ?? '0973071800'); ?></p>
                            </div>
                            <div class="client-section">
                                <p><strong>DATE:</strong> <?php echo date('d/m/Y', strtotime($document['created_at'])); ?></p>
                                <p><strong>CLIENT NAME:</strong> <?php echo htmlspecialchars($document['client_name']); ?></p>
                                <?php if ($document['client_phone']): ?>
                                    <p><strong>PHONE:</strong> <?php echo htmlspecialchars($document['client_phone']); ?></p>
                                <?php endif; ?>
                                <?php if ($document['client_address']): ?>
                                    <p><strong>ADDRESS:</strong> <?php echo htmlspecialchars($document['client_address']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Items Table -->
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">QTY</th>
                                    <th>DESCRIPTION</th>
                                    <th style="width: 120px;" class="text-right">UNIT PRICE</th>
                                    <th style="width: 120px;" class="text-right">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                                        <td class="text-right">K<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-right">K<?php echo number_format($item['total_price'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                
                                <!-- Empty row for spacing -->
                                <tr><td colspan="4" style="height: 30px;"></td></tr>
                                
                                <!-- Totals -->
                                <?php if ($document['tax_amount'] > 0 || $document['discount_amount'] > 0): ?>
                                    <tr class="total-row">
                                        <td colspan="3" class="text-right">Subtotal:</td>
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
                                    <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                                    <td class="text-right"><strong>K<?php echo number_format($document['total_amount'], 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Footer -->
                        <div class="doc-footer">
                            <p><strong>Sims-Tech Zambia LIMITED</strong></p>
                            
                            <?php if ($document['notes']): ?>
                                <p style="margin-top: 15px;"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($document['notes'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="signature-area">
                                <div class="signature-line">
                                    Prepared by: ___________________________
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
