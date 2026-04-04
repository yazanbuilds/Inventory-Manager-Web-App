<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();

// Date range filter
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Top selling products
$topProducts = $db->prepare('
    SELECT 
        p.ProductName,
        COALESCE(SUM(il.Quantity), 0) as TotalSold,
        COALESCE(SUM(r.Quantity), 0) as TotalReturned,
        COALESCE(SUM(il.Quantity), 0) - COALESCE(SUM(r.Quantity), 0) as NetSold,
        COALESCE(SUM(il.TotalPrice), 0) - COALESCE(SUM(r.TotalPrice), 0) as NetRevenue
    FROM Products p
    LEFT JOIN InvoiceLines il ON p.ProductID = il.ProductID
    LEFT JOIN Invoices i1 ON il.InvoiceID = i1.InvoiceID 
        AND date(i1.InvoiceDate) BETWEEN ? AND ?
    LEFT JOIN Returns r ON p.ProductID = r.ProductID 
        AND date(r.ReturnDate) BETWEEN ? AND ?
    GROUP BY p.ProductID
    HAVING NetSold > 0
    ORDER BY NetSold DESC
    LIMIT 10
');
$topProducts->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$topProducts = $topProducts->fetchAll();

// Vendor sales summary
// Vendor sales summary - NET of returns
$vendorSales = $db->prepare('
    SELECT 
        v.Name,
        COUNT(DISTINCT i.InvoiceID) as TotalInvoices,
        COALESCE(SUM(i.TotalAmount), 0) as GrossSales,
        COALESCE(SUM(r.TotalPrice), 0) as TotalReturns,
        COALESCE(SUM(i.TotalAmount), 0) - COALESCE(SUM(r.TotalPrice), 0) as NetSales,
        v.CurrentCredit
    FROM Vendors v
    LEFT JOIN Invoices i ON v.VendorID = i.VendorID 
        AND date(i.InvoiceDate) BETWEEN ? AND ?
    LEFT JOIN Returns r ON i.InvoiceID = r.InvoiceID 
        AND date(r.ReturnDate) BETWEEN ? AND ?
    GROUP BY v.VendorID
    HAVING GrossSales > 0
    ORDER BY NetSales DESC
');
$vendorSales->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
$vendorSales = $vendorSales->fetchAll();


// Daily sales
$dailySales = $db->prepare('
    SELECT date(InvoiceDate) as Date, SUM(TotalAmount) as DailySales, COUNT(*) as InvoiceCount
    FROM Invoices
    WHERE date(InvoiceDate) BETWEEN ? AND ?
    GROUP BY date(InvoiceDate)
    ORDER BY Date
');
$dailySales->execute([$dateFrom, $dateTo]);
$dailySales = $dailySales->fetchAll();

// Inventory Stats
$inventoryStats = $db->query('
    SELECT 
        SUM(StockQuantity) as TotalQuantity,
        SUM(StockQuantity * UnitPrice) as TotalValue
    FROM Products
')->fetch();

$pageTitle = 'Reports & Analytics';
include 'includes/header.php';
?>

<!-- Inventory Summary Cards -->
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card bg-gradient-primary stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title text-white-50">Total Inventory Quantity</h6>
                        <h2 class="mb-0 text-white"><?= number_format($inventoryStats['TotalQuantity'] ?? 0) ?></h2>
                    </div>
                    <div class="icon-box">
                        <i class="bi bi-box-seam text-white fs-1 opacity-25"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-white-50">Total items in stock</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card bg-gradient-success stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title text-white-50">Total Inventory Value</h6>
                        <h2 class="mb-0 text-white"><?= formatCurrency($inventoryStats['TotalValue'] ?? 0) ?></h2>
                    </div>
                    <div class="icon-box">
                        <i class="bi bi-currency-dollar text-white fs-1 opacity-25"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-white-50">Based on unit price</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Top Selling Products</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                                <th>Returned</th>
                                <th>Net Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $prod): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prod['ProductName']) ?></td>
                                    <td><?= number_format($prod['TotalSold']) ?></td>
                                    <td class="text-warning"><?= number_format($prod['TotalReturned']) ?></td>
                                    <td class="text-success"><strong><?= number_format($prod['NetSold']) ?></strong>
                                    </td>
                                <td><?= formatCurrency($prod['NetRevenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Vendor Sales Summary</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Vendor</th>
                                <th>Invoices</th>
                                <th>Gross Sales</th>
                                <th>Returns</th>
                                <th>Net Sales</th>
                                <th>Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendorSales as $vs): ?>
                                <tr>
                                    <td><?= htmlspecialchars($vs['Name']) ?></td>
                                    <td><?= $vs['TotalInvoices'] ?></td>
                                    <td><?= formatCurrency($vs['GrossSales']) ?></td>
                                    <td class="text-warning"><?= formatCurrency($vs['TotalReturns']) ?></td>
                                    <td class="text-success"><strong><?= formatCurrency($vs['NetSales']) ?></strong>
                                    </td>
                                    <td class="<?= $vs['CurrentCredit'] > 0 ? 'text-danger' : 'text-success' ?>">
                                        <?= formatCurrency($vs['CurrentCredit']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Daily Sales Trend</h5>
    </div>
    <div class="card-body">
        <canvas id="salesChart" height="80" 
            data-labels='<?= htmlspecialchars(json_encode(array_column($dailySales, "Date")), ENT_QUOTES, "UTF-8") ?>' 
            data-sales='<?= htmlspecialchars(json_encode(array_column($dailySales, "DailySales")), ENT_QUOTES, "UTF-8") ?>'>
        </canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>


<?php include 'includes/footer.php'; ?>