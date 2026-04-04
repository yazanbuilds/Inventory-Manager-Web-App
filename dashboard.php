<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();

// Get statistics
$stats = [
    'products' => $db->query('SELECT COUNT(*) FROM Products')->fetchColumn(),
    'vendors' => $db->query('SELECT COUNT(*) FROM Vendors')->fetchColumn(),
    'invoices' => $db->query('SELECT COUNT(*) FROM Invoices')->fetchColumn(),
    'totalCredit' => $db->query('SELECT SUM(CurrentCredit) FROM Vendors')->fetchColumn() ?? 0
];

// NET sales for last 30 days (minus returns)
$salesQuery = $db->query('
    SELECT 
        COALESCE(SUM(i.TotalAmount), 0) as GrossSales,
        COALESCE((SELECT SUM(r.TotalPrice) 
                  FROM Returns r 
                  JOIN Invoices i2 ON r.InvoiceID = i2.InvoiceID
                  WHERE date(r.ReturnDate) >= date("now", "-30 days")), 0) as TotalReturns
    FROM Invoices i 
    WHERE date(i.InvoiceDate) >= date("now", "-30 days")
')->fetch();

$stats['grossSales'] = $salesQuery['GrossSales'] ?? 0;
$stats['totalReturns'] = $salesQuery['TotalReturns'] ?? 0;
$stats['netSales'] = $stats['grossSales'] - $stats['totalReturns'];

// Pending payments
$stats['pendingPayments'] = $db->query('
    SELECT SUM(TotalAmount - PaidAmount) 
    FROM Invoices 
    WHERE TotalAmount > PaidAmount
')->fetchColumn() ?? 0;

// Recent invoices
$recentInvoices = $db->query('
    SELECT i.InvoiceID, i.InvoiceDate, i.TotalAmount, v.Name as VendorName
    FROM Invoices i
    LEFT JOIN Vendors v ON i.VendorID = v.VendorID
    ORDER BY i.InvoiceDate DESC
    LIMIT 5
')->fetchAll();

// Top selling products (last 30 days) - NET of returns
$topProducts = $db->query('
    SELECT 
        p.ProductName,
        COALESCE(SUM(il.Quantity), 0) as TotalSold,
        COALESCE((SELECT SUM(r.Quantity) 
                  FROM Returns r 
                  WHERE r.ProductID = p.ProductID 
                  AND date(r.ReturnDate) >= date("now", "-30 days")), 0) as TotalReturned,
        COALESCE(SUM(il.Quantity), 0) - 
        COALESCE((SELECT SUM(r.Quantity) 
                  FROM Returns r 
                  WHERE r.ProductID = p.ProductID 
                  AND date(r.ReturnDate) >= date("now", "-30 days")), 0) as NetSold,
        COALESCE(SUM(il.TotalPrice), 0) - 
        COALESCE((SELECT SUM(r.TotalPrice) 
                  FROM Returns r 
                  WHERE r.ProductID = p.ProductID 
                  AND date(r.ReturnDate) >= date("now", "-30 days")), 0) as NetRevenue
    FROM Products p
    LEFT JOIN InvoiceLines il ON p.ProductID = il.ProductID
    LEFT JOIN Invoices i ON il.InvoiceID = i.InvoiceID 
        AND date(i.InvoiceDate) >= date("now", "-30 days")
    GROUP BY p.ProductID
    HAVING NetSold > 0
    ORDER BY NetSold DESC
    LIMIT 5
')->fetchAll();


$pageTitle = 'Dashboard';
include 'includes/header.php';
?>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-4 fw-bold text-dark">Quick Actions</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="sales.php"
                            class="btn btn-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-cart-plus fs-5"></i> Create Sale
                        </a>
                    </div>
                    <div class="col-md-3">
                        <button onclick="openAddProduct()"
                            class="btn btn-outline-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2 border-2">
                            <i class="bi bi-box-seam fs-5"></i> Add Product
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button onclick="openAddVendor()"
                            class="btn btn-outline-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2 border-2">
                            <i class="bi bi-person-plus fs-5"></i> Add Vendor
                        </button>
                    </div>
                    <div class="col-md-3">
                        <a href="reports.php"
                            class="btn btn-outline-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2 border-2">
                            <i class="bi bi-graph-up fs-5"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-gradient-primary stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title text-white-50">Total Products</h6>
                        <h2 class="mb-0 text-white"><?= number_format($stats['products']) ?></h2>
                    </div>
                    <div class="icon-box">
                        <i class="bi bi-box-seam text-white fs-1 opacity-25"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="products.php" class="text-white text-decoration-none small">View all products <i
                            class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card bg-gradient-success stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title text-white-50">Total Vendors</h6>
                        <h2 class="mb-0 text-white"><?= number_format($stats['vendors']) ?></h2>
                    </div>
                    <div class="icon-box">
                        <i class="bi bi-people text-white fs-1 opacity-25"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="vendors.php" class="text-white text-decoration-none small">View all vendors <i
                            class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card bg-gradient-info stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title text-white-50">Total Invoices</h6>
                        <h2 class="mb-0 text-white"><?= number_format($stats['invoices']) ?></h2>
                    </div>
                    <div class="icon-box">
                        <i class="bi bi-receipt text-white fs-1 opacity-25"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="invoices.php" class="text-white text-decoration-none small">View history <i
                            class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-3">
        <div class="card bg-gradient-warning stat-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="card-title text-white-50">Total Credit</h6>
                        <h2 class="mb-0 text-white"><?= formatCurrency($stats['totalCredit']) ?></h2>
                    </div>
                    <div class="icon-box">
                        <i class="bi bi-currency-dollar text-white fs-1 opacity-25"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="vendors.php" class="text-white text-decoration-none small">Manage credit <i
                            class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sales & Payments Row -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-4">Sales Overview (30 Days)</h5>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="p-3 rounded-4 bg-light">
                            <h6 class="text-muted mb-2">Gross Sales</h6>
                            <h3 class="text-primary fw-bold mb-0"><?= formatCurrency($stats['grossSales']) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-4 bg-light">
                            <h6 class="text-muted mb-2">Returns</h6>
                            <h3 class="text-warning fw-bold mb-0">- <?= formatCurrency($stats['totalReturns']) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-4 bg-light">
                            <h6 class="text-muted mb-2">Net Sales</h6>
                            <h3 class="text-success fw-bold mb-0"><?= formatCurrency($stats['netSales']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-4">Status</h5>

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Pending Payments</span>
                        <span class="badge bg-danger rounded-pill">Action Needed</span>
                    </div>
                    <h3 class="text-danger fw-bold"><?= formatCurrency($stats['pendingPayments']) ?></h3>
                </div>


            </div>
        </div>
    </div>
</div>

<!-- Charts & Tables Row -->
<div class="row">
    <!-- Recent Invoices -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5>Recent Invoices</h5>
                <a href="invoices.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentInvoices)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 48px;"></i>
                        <p>No invoices yet</p>
                        <a href="sales.php" class="btn btn-primary">Create First Invoice</a>
                    </div>
                <?php else: ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Vendor</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentInvoices as $inv): ?>
                                <tr onclick="window.location='invoices.php'" style="cursor: pointer;">
                                    <td><strong><?= $inv['InvoiceID'] ?></strong></td>
                                    <td><?= htmlspecialchars($inv['VendorName']) ?></td>
                                    <td><?= formatCurrency($inv['TotalAmount']) ?></td>
                                    <td><?= formatDate($inv['InvoiceDate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5>Top Selling Products (30 Days)</h5>
                <a href="reports.php" class="btn btn-sm btn-primary">View Reports</a>
            </div>
            <div class="card-body">
                <?php if (empty($topProducts)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-graph-up" style="font-size: 48px;"></i>
                        <p>No sales data yet</p>
                    </div>
                <?php else: ?>
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Sold</th>
                                <th class="text-center">Returned</th>
                                <th class="text-center">Net</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $prod): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prod['ProductName']) ?></td>
                                    <td class="text-center text-primary"><?= number_format($prod['TotalSold']) ?></td>
                                    <td class="text-center text-warning"><?= number_format($prod['TotalReturned']) ?></td>
                                    <td class="text-center text-success"><strong><?= number_format($prod['NetSold']) ?></strong>
                                    </td>
                                    <td class="text-end"><?= formatCurrency($prod['NetRevenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>