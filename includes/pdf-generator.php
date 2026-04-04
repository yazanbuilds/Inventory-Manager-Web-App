<?php
// This is a simplified PDF generator
// For production, install TCPDF: composer require tecnickcom/tcpdf

function generateInvoicePDF($invoiceId) {
    require_once __DIR__ . '/config.php';
    $db = getDB();
    
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
        return false;
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
    
    // Get payments
    $payments = $db->prepare('SELECT * FROM Payments WHERE InvoiceID = ?');
    $payments->execute([$invoiceId]);
    $payments = $payments->fetchAll();
    
    // Generate HTML (simple version - can be enhanced with TCPDF)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Invoice #<?= $invoice['InvoiceID'] ?></title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .info { margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .total { text-align: right; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>INVOICE</h1>
            <h2>Abaya Wholesale Manager</h2>
        </div>
        
        <div class="info">
            <p><strong>Invoice #:</strong> <?= $invoice['InvoiceID'] ?></p>
            <p><strong>Date:</strong> <?= date('d M Y', strtotime($invoice['InvoiceDate'])) ?></p>
            <p><strong>Vendor:</strong> <?= htmlspecialchars($invoice['VendorName']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($invoice['VendorPhone']) ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['ProductName']) ?></td>
                    <td><?= $item['Quantity'] ?></td>
                    <td>Rs. <?= number_format($item['UnitPrice'], 2) ?></td>
                    <td>Rs. <?= number_format($item['TotalPrice'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total">
            <p><strong>Previous Credit:</strong> Rs. <?= number_format($invoice['PreviousCredit'], 2) ?></p>
            <p><strong>Invoice Total:</strong> Rs. <?= number_format($invoice['TotalAmount'], 2) ?></p>
            <p><strong>Total Paid:</strong> Rs. <?= number_format($invoice['PaidAmount'], 2) ?></p>
            <p><strong>Balance Due:</strong> Rs. <?= number_format($invoice['TotalAmount'] - $invoice['PaidAmount'], 2) ?></p>
        </div>
        
        <?php if (count($payments) > 0): ?>
        <h3>Payment History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($p['PaymentDate'])) ?></td>
                    <td>Rs. <?= number_format($p['Amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['PaymentMethod']) ?></td>
                    <td><?= htmlspecialchars($p['Notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </body>
    </html>
    <?php
    
    $html = ob_get_clean();
    
    // For now, return HTML. To convert to PDF, use TCPDF or similar library
    return $html;
}
?>
