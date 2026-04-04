<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();
$productId = $_GET['id'] ?? '';

if (!$productId) {
    header('Location: products.php');
    exit;
}

// Get product info
$product = $db->prepare('SELECT * FROM Products WHERE ProductID = ?');
$product->execute([$productId]);
$product = $product->fetch();

if (!$product) {
    die('Product not found');
}

// Get sales history
$sales = $db->prepare('
    SELECT i.InvoiceID, i.InvoiceDate, v.Name as VendorName, 
           il.Quantity, il.UnitPrice, il.TotalPrice
    FROM InvoiceLines il
    JOIN Invoices i ON il.InvoiceID = i.InvoiceID
    LEFT JOIN Vendors v ON i.VendorID = v.VendorID
    WHERE il.ProductID = ?
    ORDER BY i.InvoiceDate DESC
');
$sales->execute([$productId]);
$sales = $sales->fetchAll();

// Get returns history
$returns = $db->prepare('
    SELECT r.ReturnID, r.ReturnDate, i.InvoiceID, v.Name as VendorName,
           r.Quantity, r.UnitPrice, r.TotalPrice, r.Reason
    FROM Returns r
    JOIN Invoices i ON r.InvoiceID = i.InvoiceID
    LEFT JOIN Vendors v ON i.VendorID = v.VendorID
    WHERE r.ProductID = ?
    ORDER BY r.ReturnDate DESC
');
$returns->execute([$productId]);
$returns = $returns->fetchAll();

// Calculate totals
$totalSold = array_sum(array_column($sales, 'Quantity'));
$totalReturned = array_sum(array_column($returns, 'Quantity'));
$netSold = $totalSold - $totalReturned;

$pageTitle = 'Product Details';
include 'includes/header.php';
?>

<div class="card mb-3">
    <div class="card-header">
        <h5><?= htmlspecialchars($product['ProductName']) ?></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <p><strong>Product ID:</strong> <?= htmlspecialchars($product['ProductID']) ?></p>
                <p><strong>Unit Price:</strong> <?= formatCurrency($product['UnitPrice']) ?></p>
            </div>
            <div class="col-md-3">
                <p><strong>Current Stock:</strong> <?= $product['StockQuantity'] ?></p>
            </div>
            <div class="col-md-3">
                <p><strong>Total Sold:</strong> <span class="text-primary"><?= $totalSold ?></span></p>
                <p><strong>Total Returned:</strong> <span class="text-warning"><?= $totalReturned ?></span></p>
            </div>
            <div class="col-md-3">
                <p><strong>Net Sold:</strong> <span class="text-success h4"><?= $netSold ?></span></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6>Sales History (<?= count($sales) ?>)</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Vendor</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $s): ?>
                        <tr>
                            <td><?= formatDate($s['InvoiceDate']) ?></td>
                            <td><a href="invoices.php">#<?= $s['InvoiceID'] ?></a></td>
                            <td><?= htmlspecialchars($s['VendorName']) ?></td>
                            <td><?= $s['Quantity'] ?></td>
                            <td><?= formatCurrency($s['TotalPrice']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning">
                <h6>Returns History (<?= count($returns) ?>)</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Vendor</th>
                            <th>Qty</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $r): ?>
                        <tr>
                            <td><?= formatDate($r['ReturnDate']) ?></td>
                            <td><a href="invoices.php">#<?= $r['InvoiceID'] ?></a></td>
                            <td><?= htmlspecialchars($r['VendorName']) ?></td>
                            <td><?= $r['Quantity'] ?></td>
                            <td><?= htmlspecialchars($r['Reason'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
