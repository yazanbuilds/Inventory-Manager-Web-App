<?php
require_once 'includes/config.php';
requireLogin();

$invoiceId = $_GET['id'] ?? null;

if (!$invoiceId) {
    die('Invoice ID required');
}

$db = getDB();

// Load settings from database
$stmt = $db->query('SELECT SettingKey, SettingValue FROM Settings');
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['SettingKey']] = $row['SettingValue'];
}

function getSetting($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

// Get invoice data
$invoice = $db->prepare('
    SELECT i.*, v.Name as VendorName, v.Phone as VendorPhone
    FROM Invoices i
    LEFT JOIN Vendors v ON i.VendorID = v.VendorID
    WHERE i.InvoiceID = ?
');
$invoice->execute([$invoiceId]);
$invoice = $invoice->fetch();

if (!$invoice) {
    die('Invoice not found');
}

// Get invoice lines
$items = $db->prepare('
    SELECT il.*, p.ProductName
    FROM InvoiceLines il
    LEFT JOIN Products p ON il.ProductID = p.ProductID
    WHERE il.InvoiceID = ?
');
$items->execute([$invoiceId]);
$items = $items->fetchAll();

// Get returns
$returns = $db->prepare('
    SELECT r.*, p.ProductName
    FROM Returns r
    LEFT JOIN Products p ON r.ProductID = p.ProductID
    WHERE r.InvoiceID = ?
    ORDER BY r.ReturnDate
');
$returns->execute([$invoiceId]);
$returns = $returns->fetchAll();

// Get payments
$payments = $db->prepare('SELECT * FROM Payments WHERE InvoiceID = ?');
$payments->execute([$invoiceId]);
$payments = $payments->fetchAll();
// Mathematical Breakdown for Invoice State
$currentInvoiceAmount = (float)$invoice['TotalAmount'];
$previousBalance = (float)$invoice['PreviousCredit'];
$totalPayable = $currentInvoiceAmount + $previousBalance;
$totalPaid = (float)$invoice['PaidAmount'];

// Vendor's ultimate pending balance matching this snapshot
$totalPendingVendorBalance = $totalPayable - $totalPaid;

// Calculate how much payment cascaded correctly to just THIS invoice
$paidTowardsPrior = min($totalPaid, $previousBalance);
$paidTowardsCurrent = $totalPaid - $paidTowardsPrior;
$currentInvoicePendingBalance = $currentInvoiceAmount - $paidTowardsCurrent;

// Overpay safety check for specific invoice line
if ($currentInvoicePendingBalance < 0) $currentInvoicePendingBalance = 0;

$status = 'UNPAID';
$statusClass = 'stamp-unpaid';
if ($totalPendingVendorBalance <= 0) {
    $status = 'PAID';
    $statusClass = 'stamp-paid';
} elseif ($totalPaid > 0) {
    $status = 'PARTIAL';
    $statusClass = 'stamp-partial';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?= $invoice['InvoiceID'] ?></title>
    <style>
        /* Print Constraints */
        @page { size: letter; margin: 0.15in; }
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .avoid-break { page-break-inside: avoid; }
        }
        
        /* Modern Reset & Typography */
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #2c3e50;
            line-height: 1.35;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
        }

        /* Header Layout Engine */
        .flex-container { display: flex; justify-content: space-between; align-items: flex-start; }
        .header-wrap { padding-bottom: 12px; border-bottom: 2px solid #34495e; margin-bottom: 15px; }
        .company-meta { max-width: 60%; }
        .company-logo { max-height: 50px; margin-bottom: 8px; }
        .company-title { margin: 0 0 2px 0; font-size: 18px; font-weight: 700; color: #2c3e50; }
        .company-tagline { margin: 0; color: #7f8c8d; font-size: 10px; }
        
        .invoice-title { text-align: right; }
        .invoice-title h1 { margin: 0 0 5px 0; font-size: 32px; font-weight: 300; letter-spacing: 2px; color: #2c3e50; text-transform: uppercase; }
        .invoice-title table { width: auto; margin-left: auto; border: none; }
        .invoice-title td { padding: 2px 5px; border: none; font-size: 11px; text-align: right; }
        .invoice-title td.label { color: #7f8c8d; font-weight: 600; text-transform: uppercase; }

        /* Billing Engine */
        .billing-grid { display: flex; justify-content: space-between; margin-bottom: 15px; gap: 15px; }
        .billing-block { flex: 1; padding: 10px; background: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef; }
        .billing-block .title { font-size: 10px; text-transform: uppercase; color: #7f8c8d; font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid #dee2e6; padding-bottom: 3px; }
        .billing-block p { margin: 2px 0; font-size: 11px; }
        .billing-block strong { color: #2c3e50; }

        /* Standard Table CSS */
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10.5px; }
        th { background-color: #34495e; color: #ffffff; text-align: left; padding: 6px 8px; text-transform: uppercase; font-size: 9.5px; font-weight: 600; }
        td { padding: 5px 8px; border-bottom: 1px solid #e9ecef; vertical-align: top; }
        tr:nth-child(even) td { background-color: #fbfcff; }
        th.text-right, td.text-right { text-align: right; }
        th.text-center, td.text-center { text-align: center; }

        /* Highlights */
        .table-returns th { background-color: #e67e22; }
        .table-returns td { background-color: #fdf3e8 !important; border-bottom: 1px solid #fadbd8; }

        /* Core Summary Infrastructure */
        .summary-container { flex: 0 0 45%; border: 1px solid #cfd8dc; border-radius: 4px; overflow: hidden; background: #fff; }
        .summary-wrapper { display: flex; justify-content: space-between; margin-top: 5px; page-break-inside: avoid; gap: 15px;}
        .summary-notes { flex: 0 0 50%; }
        
        .summary-table { margin: 0; }
        .summary-table td, .summary-table th { padding: 6px 12px; border: none; border-bottom: 1px solid #eceff1; background: transparent !important; }
        .summary-table th { color: #546e7a; font-size: 10px; text-align: left; }
        .summary-table td { text-align: right; color: #37474f; font-weight: 600; }
        .summary-table tr.total-row th, .summary-table tr.total-row td { font-size: 12px; color: #263238; border-top: 2px solid #37474f; border-bottom: none; font-weight: bold; padding-top: 8px;}
        .summary-table tr.highlight td { color: #d32f2f; }
        
        /* Grand Balance Alert box */
        .grand-balance-stripe { background: #34495e; color: #fff; padding: 10px 12px; display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .grand-balance-stripe.green { background: #27ae60; }
        
        /* Watermark Wrapper */
        .status-stamp { font-size: 30px; font-weight: 900; text-transform: uppercase; padding: 4px 15px; border: 4px solid; border-radius: 6px; display: inline-block; position: absolute; right: 55%; margin-top: 20px; transform: rotate(-15deg); opacity: 0.15; z-index: 0; letter-spacing: 4px;}

        /* Utilities */
        .bank-info { font-size: 9px; line-height: 1.4; color: #7f8c8d; }
        .bank-info strong { color: #34495e; }
        .fw-bold { font-weight: bold; }
        .text-muted { color: #95a5a6; }
        .print-btn-bar { background: #f1f2f6; padding: 10px; text-align: center; border-radius: 6px; margin-bottom: 20px; }
        .btn { padding: 8px 24px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; color: white; display: inline-block; text-decoration: none; font-weight: bold; margin: 0 5px;}
        .btn-primary { background: #3498db; } .btn-primary:hover { background: #2980b9; }
        .btn-secondary { background: #95a5a6; } .btn-secondary:hover { background: #7f8d8d; }
    </style>
</head>
<body>

    <!-- Controls Print Bar -->
    <div class="no-print print-btn-bar">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Document</button>
        <button onclick="window.close()" class="btn btn-secondary">Close Window</button>
    </div>

    <!-- 1. HEADER ROW -->
    <div class="header-wrap flex-container">
        <div class="company-meta">
            <?php if (getSetting('company_logo')): ?>
                <img src="<?= htmlspecialchars(getSetting('company_logo')) ?>" class="company-logo" alt="Logo">
            <?php endif; ?>
            <h2 class="company-title"><?= htmlspecialchars(getSetting('company_name', 'Abaya Wholesale Manager')) ?></h2>
            <?php if (getSetting('branch1_address') || getSetting('branch1_phone')): ?>
                <p class="company-tagline">
                    <?= htmlspecialchars(getSetting('branch1_address')) ?> 
                    <?= getSetting('branch1_phone') ? '| Tel: ' . getSetting('branch1_phone') : '' ?>
                </p>
            <?php endif; ?>
        </div>
        
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <table cellspacing="0">
                <tr><td class="label">Invoice No:</td><td class="fw-bold">#<?= $invoice['InvoiceID'] ?></td></tr>
                <tr><td class="label">Invoice Date:</td><td class="fw-bold"><?= date('d M, Y', strtotime($invoice['InvoiceDate'])) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- 2. BILLING & ROUTES ROW -->
    <div class="billing-grid">
        <div class="billing-block">
            <div class="title">Billed To (Customer)</div>
            <p><strong><?= htmlspecialchars($invoice['VendorName']) ?></strong></p>
            <p class="text-muted">Account No. <?= $invoice['VendorID'] ?></p>
            <?php if ($invoice['VendorPhone']): ?>
                 <p><span class="text-muted">Phone:</span> <?= htmlspecialchars($invoice['VendorPhone']) ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (getSetting('branch2_name')): ?>
        <div class="billing-block">
            <div class="title">Route Delivery / Secondary Branch</div>
            <p><strong><?= htmlspecialchars(getSetting('branch2_name')) ?></strong></p>
            <?php if (getSetting('branch2_address')): ?>
                <p class="text-muted"><?= htmlspecialchars(getSetting('branch2_address')) ?></p>
            <?php endif; ?>
            <?php if (getSetting('branch2_phone')): ?>
                 <p><span class="text-muted">Tel:</span> <?= htmlspecialchars(getSetting('branch2_phone')) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- 3. PRODUCTS TABLE -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Item Description</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 15%;">Unit Price</th>
                <th class="text-right" style="width: 20%;">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
            <tr>
                <td class="text-muted"><?= $i++ ?></td>
                <td class="fw-bold"><?= htmlspecialchars($item['ProductName']) ?></td>
                <td class="text-center"><?= $item['Quantity'] ?></td>
                <td class="text-right">Rs. <?= number_format($item['UnitPrice'], 2) ?></td>
                <td class="text-right fw-bold">Rs. <?= number_format($item['TotalPrice'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 4. RETURNS TABLE (Conditional) -->
    <?php if (count($returns) > 0): ?>
    <table class="table-returns avoid-break">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Returned Items</th>
                <th class="text-center" style="width: 15%;">Qty</th>
                <th class="text-right" style="width: 15%;">Unit Value</th>
                <th class="text-right" style="width: 20%;">Credit Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $j = 1; foreach ($returns as $r): ?>
            <tr>
                <td class="text-muted"><?= $j++ ?></td>
                <td><span class="fw-bold"><?= htmlspecialchars($r['ProductName']) ?></span> <span class="text-muted" style="font-size: 9px;">(<?= date('d/m/y', strtotime($r['ReturnDate'])) ?>)</span></td>
                <td class="text-center"><?= $r['Quantity'] ?></td>
                <td class="text-right">Rs. <?= number_format($r['UnitPrice'], 2) ?></td>
                <td class="text-right fw-bold">-Rs. <?= number_format($r['TotalPrice'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- 5. SPLIT FOOTER SYSTEM & MATH ENGINE -->
    <div class="summary-wrapper avoid-break">
        
        <!-- Left Footer (Payments & Bank Info) -->
        <div class="summary-notes">
            <?php if (count($payments) > 0): ?>
                <div style="font-size: 10px; text-transform: uppercase; font-weight: bold; color: #7f8c8d; margin-bottom: 5px; border-bottom: 1px solid #dee2e6; padding-bottom: 2px;">Recent Payments Recieved</div>
                <table style="font-size: 9.5px; margin-bottom: 12px;">
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td style="padding: 2px 0;">✔️ <?= date('d M', strtotime($p['PaymentDate'])) ?></td>
                        <td style="padding: 2px 0;"><?= htmlspecialchars($p['PaymentMethod']) ?></td>
                        <td class="text-right fw-bold" style="padding: 2px 0; color: #27ae60;">Rs. <?= number_format($p['Amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
            
            <div class="status-stamp <?= $statusClass ?>"><?= $status ?></div>
            
            <div class="bank-info" style="margin-top: 15px;">
                <p style="text-transform: uppercase; font-weight:bold; color: #34495e; border-bottom: 1px solid #dee2e6; padding-bottom: 2px; margin-bottom: 5px;">Bank Details / Remittance</p>
                <?php if (getSetting('account_title')): ?>
                    Title: <strong><?= htmlspecialchars(getSetting('account_title')) ?></strong> | 
                <?php endif; ?>
                <?php if (getSetting('bank1_name')): ?>
                    <?= htmlspecialchars(getSetting('bank1_name')) ?>: <strong><?= htmlspecialchars(getSetting('bank1_account')) ?></strong><br>
                <?php endif; ?>
                <?php if (getSetting('bank2_name')): ?>
                    <?= htmlspecialchars(getSetting('bank2_name')) ?>: <strong><?= htmlspecialchars(getSetting('bank2_account')) ?></strong><br>
                <?php endif; ?>
            </div>
            <p style="margin-top: 15px; font-weight: bold; font-style: italic; font-size: 10px; color: #7f8c8d;">Thank you for your continued business!</p>
        </div>

        <!-- Right Footer (Structured Logic Totals) -->
        <div class="summary-container">
            <table class="summary-table">
                <tr>
                    <th>Total Current Invoice</th>
                    <td>Rs. <?= number_format($currentInvoiceAmount, 2) ?></td>
                </tr>
                <tr>
                    <th>Prior Pending Balance</th>
                    <td>Rs. <?= number_format($previousBalance, 2) ?></td>
                </tr>
                <tr class="total-row">
                    <th>Total Payable</th>
                    <td>Rs. <?= number_format($totalPayable, 2) ?></td>
                </tr>
                <tr>
                    <th>Less: Payments Received</th>
                    <td>- Rs. <?= number_format($totalPaid, 2) ?></td>
                </tr>
                <tr class="highlight">
                    <th>Current Invoice Pending</th>
                    <td>Rs. <?= number_format($currentInvoicePendingBalance, 2) ?></td>
                </tr>
            </table>
            <div class="grand-balance-stripe <?= $status === 'PAID' ? 'green' : '' ?>">
                <span>Total Vendor Balance Due</span>
                <span>Rs. <?= number_format($totalPendingVendorBalance, 2) ?></span>
            </div>
        </div>
        
    </div>

</body>
</html>
