<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();

// Get filter parameters
$vendorFilter = $_GET['vendor'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Get all vendors for filter dropdown
$vendors = $db->query('SELECT * FROM Vendors ORDER BY Name')->fetchAll();

// Get unpaid invoices for "Add Payment" modal
$unpaidInvoices = $db->query('
    SELECT i.InvoiceID, i.VendorID, i.InvoiceDate, i.TotalAmount, i.PaidAmount, v.Name as VendorName
    FROM Invoices i
    LEFT JOIN Vendors v ON i.VendorID = v.VendorID
    WHERE i.TotalAmount > i.PaidAmount
    ORDER BY i.InvoiceDate DESC
')->fetchAll();

// Build payment query with filters - GROUP BY INVOICE
$query = '
    SELECT 
        i.InvoiceID,
        i.InvoiceDate,
        i.TotalAmount,
        i.PaidAmount,
        v.VendorID,
        v.Name as VendorName,
        COUNT(p.PaymentID) as PaymentCount,
        SUM(p.Amount) as TotalPaid
    FROM Invoices i
    LEFT JOIN Vendors v ON i.VendorID = v.VendorID
    LEFT JOIN Payments p ON i.InvoiceID = p.InvoiceID
    WHERE i.PaidAmount > 0
';

$params = [];

if ($vendorFilter) {
    $query .= ' AND v.VendorID = ?';
    $params[] = $vendorFilter;
}

if ($dateFrom) {
    $query .= ' AND date(i.InvoiceDate) >= ?';
    $params[] = $dateFrom;
}

if ($dateTo) {
    $query .= ' AND date(i.InvoiceDate) <= ?';
    $params[] = $dateTo;
}

$query .= ' GROUP BY i.InvoiceID ORDER BY i.InvoiceDate DESC';

$stmt = $db->prepare($query);
$stmt->execute($params);
$invoicesWithPayments = $stmt->fetchAll();

// Calculate totals
$totalPaymentsAmount = 0;
$totalTransactions = 0;
foreach ($invoicesWithPayments as $inv) {
    $totalPaymentsAmount += $inv['TotalPaid'];
    $totalTransactions += $inv['PaymentCount'];
}

$pageTitle = 'Payments';
include 'includes/header.php';
?>

<!-- Filter Section -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Vendor</label>
                <select name="vendor" class="form-select">
                    <option value="">All Vendors</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['VendorID'] ?>" <?= $vendorFilter == $v['VendorID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>
        <?php if ($vendorFilter || $dateFrom || $dateTo): ?>
            <div class="mt-2">
                <a href="payments.php" class="btn btn-sm btn-secondary">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="text-white">Total Payments Received</h6>
                <h2 class="text-white"><?= formatCurrency($totalPaymentsAmount) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="text-white">Total Transactions</h6>
                <h2 class="text-white"><?= $totalTransactions ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="text-white">Invoices with Payments</h6>
                <h2 class="text-white"><?= count($invoicesWithPayments) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Button -->
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
        <i class="bi bi-plus-circle"></i> Add Payment
    </button>
</div>

<!-- Payment History - Grouped by Invoice -->
<div class="card">
    <div class="card-header">
        <h5>Payment History - Grouped by Invoice</h5>
    </div>
    <div class="card-body">
        <?php if (empty($invoicesWithPayments)): ?>
            <div class="alert alert-info">No payments found for the selected filters.</div>
        <?php else: ?>
            <div class="accordion" id="paymentsAccordion">
                <?php foreach ($invoicesWithPayments as $index => $inv): ?>
                    <?php
                    // Get individual payments for this invoice
                    $stmtPayments = $db->prepare('
                        SELECT * FROM Payments 
                        WHERE InvoiceID = ? 
                        ORDER BY PaymentDate DESC
                    ');
                    $stmtPayments->execute([$inv['InvoiceID']]);
                    $invoicePayments = $stmtPayments->fetchAll();

                    $balance = $inv['TotalAmount'] - $inv['PaidAmount'];
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?= $index ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse<?= $index ?>" aria-expanded="false">
                                <div class="d-flex justify-content-between w-100 me-3">
                                    <div>
                                        <strong>Invoice #<?= $inv['InvoiceID'] ?></strong> -
                                        <?= htmlspecialchars($inv['VendorName']) ?>
                                        <small class="text-muted ms-2">(<?= formatDate($inv['InvoiceDate']) ?>)</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success me-2"><?= $inv['PaymentCount'] ?>
                                            Payment<?= $inv['PaymentCount'] > 1 ? 's' : '' ?></span>
                                        <span class="badge bg-info">Total: <?= formatCurrency($inv['TotalPaid']) ?></span>
                                        <?php if ($balance > 0): ?>
                                            <span class="badge bg-danger ms-2">Balance: <?= formatCurrency($balance) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success ms-2">Paid</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#paymentsAccordion">
                            <div class="accordion-body">
                                <!-- Invoice Summary -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>Invoice Total:</strong> <?= formatCurrency($inv['TotalAmount']) ?></p>
                                        <p><strong>Total Paid:</strong> <?= formatCurrency($inv['PaidAmount']) ?></p>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <p class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>">
                                            <strong>Balance:</strong> <?= formatCurrency($balance) ?>
                                        </p>
                                        <?php if ($balance > 0): ?>
                                            <button class="btn btn-sm btn-success"
                                                onclick="addPaymentToInvoice(<?= $inv['InvoiceID'] ?>)">
                                                <i class="bi bi-plus-circle"></i> Add Payment
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Payments Table -->
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Payment ID</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoicePayments as $p): ?>
                                            <tr>
                                                <td><?= $p['PaymentID'] ?></td>
                                                <td><?= formatDate($p['PaymentDate']) ?></td>
                                                <td class="text-success"><strong><?= formatCurrency($p['Amount']) ?></strong></td>
                                                <td><?= htmlspecialchars($p['PaymentMethod'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($p['Notes'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr>
                                            <th colspan="2" class="text-end">Total for this Invoice:</th>
                                            <th class="text-success"><?= formatCurrency($inv['TotalPaid']) ?></th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Add New Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPaymentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Invoice *</label>
                        <select name="invoice_id" id="payment_invoice_id" class="form-select" required>
                            <option value="">-- Select Invoice --</option>
                            <?php foreach ($unpaidInvoices as $inv): ?>
                                <?php $balance = $inv['TotalAmount'] - $inv['PaidAmount']; ?>
                                <option value="<?= $inv['InvoiceID'] ?>" data-balance="<?= $balance ?>">
                                    Invoice #<?= $inv['InvoiceID'] ?> - <?= htmlspecialchars($inv['VendorName']) ?> -
                                    Balance: <?= formatCurrency($balance) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Only invoices with outstanding balance are shown</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Outstanding Balance</label>
                        <input type="text" id="outstanding_balance" class="form-control" readonly value="Rs. 0.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Amount *</label>
                        <input type="number" step="0.01" name="amount" id="payment_amount" class="form-control" required
                            min="0.01">
                        <small class="text-muted">Enter amount to pay (can be partial or full)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Online">Online Payment</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"
                            placeholder="Optional payment notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>