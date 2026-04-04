<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();

// Get filter parameters
$vendorFilter = $_GET['vendor'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$query = 'SELECT i.*, v.Name as VendorName FROM Invoices i LEFT JOIN Vendors v ON i.VendorID = v.VendorID WHERE 1=1';
$params = [];

if ($vendorFilter) {
    $query .= ' AND i.VendorID = ?';
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

$query .= ' ORDER BY i.InvoiceDate DESC';

$stmt = $db->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$vendors = $db->query('SELECT * FROM Vendors ORDER BY Name')->fetchAll();

$pageTitle = 'Invoices';
include 'includes/header.php';
?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
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
                <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="invoices.php" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h5>Invoice History (<?= count($invoices) ?>)</h5>
        <a href="sales.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> New Invoice
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <?php
                        $invoiceAmount  = (float)$inv['TotalAmount'];
                        $previousCredit = (float)$inv['PreviousCredit'];
                        $paidAmount     = (float)$inv['PaidAmount'];
                        // Effective total = this invoice's items + whatever was owed before it
                        $effectiveTotal = $invoiceAmount + $previousCredit;
                        // Balance is what remains unpaid of the effective total
                        $balance = round($effectiveTotal - $paidAmount, 2);
                    ?>
                    <tr>
                        <td><strong><?= $inv['InvoiceID'] ?></strong></td>
                        <td><?= formatDate($inv['InvoiceDate']) ?></td>
                        <td><?= htmlspecialchars($inv['VendorName'] ?? 'Unknown Vendor') ?></td>
                        <td><?= formatCurrency($effectiveTotal) ?></td>
                        <td><?= formatCurrency($paidAmount) ?></td>
                        <td class="<?= $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-success' : 'text-muted') ?>">
                            <?= formatCurrency(abs($balance)) ?>
                            <?php if ($balance < 0): ?>
                                <small class="text-success">(Overpaid)</small>
                            <?php elseif ($balance == 0): ?>
                                <small class="text-muted">(Paid)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info viewInvoiceBtn" data-id="<?= $inv['InvoiceID'] ?>" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <a href="invoice-pdf.php?id=<?= $inv['InvoiceID'] ?>" target="_blank" class="btn btn-sm btn-danger" title="Export PDF">
                                <i class="bi bi-file-pdf"></i>
                            </a>
                            <button class="btn btn-sm btn-warning returnProductBtn" data-id="<?= $inv['InvoiceID'] ?>" title="Return Product">
                                <i class="bi bi-arrow-return-left"></i>
                            </button>
                            <?php if ($balance > 0): ?>
                            <button class="btn btn-sm btn-success" onclick="addPayment(<?= $inv['InvoiceID'] ?>, <?= $balance ?>)" title="Add Payment">
                                <i class="bi bi-cash"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-danger deleteInvoiceBtn" data-id="<?= $inv['InvoiceID'] ?>" data-vendor="<?= htmlspecialchars($inv['VendorName'] ?? 'Unknown Vendor') ?>" title="Delete Invoice">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="invoiceDetails">
                <div class="text-center">
                    <div class="spinner-border" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPaymentForm">
                <input type="hidden" name="invoice_id" id="payment_invoice_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Product Modal -->
<div class="modal fade" id="returnProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Return Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="returnProductForm">
                <input type="hidden" name="invoice_id" id="return_invoice_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Product to Return *</label>
                        <select name="product_id" id="return_product_select" class="form-select" required>
                            <option value="">-- Select Product --</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity to Return *</label>
                        <input type="number" name="quantity" id="return_quantity" class="form-control" min="1" required>
                        <small class="text-muted">Maximum quantity: <span id="max_return_qty">0</span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Unit Price
                            <span class="badge bg-warning text-dark ms-1"><i class="bi bi-lock-fill"></i> Invoice Locked</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="0.01" name="unit_price" id="return_unit_price" 
                                   class="form-control bg-light" readonly tabindex="-1"
                                   style="cursor: not-allowed;">
                        </div>
                        <div id="return_price_note" class="form-text text-muted">
                            <i class="bi bi-lock-fill"></i> Select a product to see the locked invoice price
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Return Date *</label>
                        <input type="date" name="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Return</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Optional reason..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Note:</strong> Returned products will be added back to inventory and invoice total will be adjusted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Process Return</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>
