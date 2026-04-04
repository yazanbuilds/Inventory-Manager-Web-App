<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();
$vendors = $db->query('SELECT * FROM Vendors ORDER BY Name')->fetchAll();
$products = $db->query('SELECT * FROM Products ORDER BY ProductName')->fetchAll();

// Check system behavior setting
$stmtLimit = $db->query("SELECT SettingValue FROM Settings WHERE SettingKey = 'ignore_stock_limits'");
$unlimitedStock = $stmtLimit->fetchColumn() === '1';

$pageTitle = 'Create Sale';
include 'includes/header.php';
?>

<div class="abaya-sales-page">
    <form id="salesForm">
        
        <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
            <h4 class="mb-0 fw-bold d-flex align-items-center">
                <a href="dashboard.php" class="text-dark text-decoration-none me-3"><i class="bi bi-arrow-left"></i></a>
                Create Sale
            </h4>
            <span class="text-muted fw-bold text-uppercase" style="letter-spacing: 1px; font-size: 0.85rem;">Abaya</span>
        </div>

        <div class="row">
            <div class="col-lg-8">
                
                <div class="sales-card">
                    <div class="mb-3">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" id="vendorSelect" class="form-select" required>
                            <option value="">Search Vendor...</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= $v['VendorID'] ?>" data-credit="<?= $v['CurrentCredit'] ?>"
                                    data-phone="<?= htmlspecialchars($v['Phone'] ?? '') ?>">
                                    <?= htmlspecialchars($v['Name']) ?> - <?= htmlspecialchars($v['Phone'] ?? '') ?> (Credit:
                                    <?= formatCurrency($v['CurrentCredit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Previous Credit</label>
                            <input type="text" id="previousCreditDisplay" class="form-control text-muted" value="AED 0.00" readonly>
                            <input type="hidden" name="previous_credit" id="previousCredit" value="0">
                        </div>
                    </div>
                </div>

                <div class="sales-card">
                    <h6 class="fw-bold mb-3" style="color: #064e3b;">Add Products</h6>
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select id="productSelect" class="form-select">
                            <option value="">SKU or Name</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['ProductID'] ?>" data-price="<?= $p['UnitPrice'] ?>"
                                    data-name="<?= htmlspecialchars($p['ProductName']) ?>"
                                    data-stock="<?= $unlimitedStock ? '999999' : $p['StockQuantity'] ?>">
                                    <?= htmlspecialchars($p['ProductName']) ?> - <?= $p['ProductID'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <label class="form-label">Type</label>
                            <div class="type-toggle-container">
                                <button type="button" class="type-toggle-btn active-sale" id="btnTypeSale" onclick="setType('sale')">SALE</button>
                                <button type="button" class="type-toggle-btn" id="btnTypeRet" onclick="setType('return')">RET</button>
                            </div>
                            <select id="itemType" class="d-none">
                                <option value="sale" selected>Sale</option>
                                <option value="return">Return</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Qty</label>
                            <input type="number" id="productQty" class="form-control" value="0" min="1">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" id="productPrice" class="form-control" value="0.00">
                        </div>
                    </div>

                    <button type="button" id="addProductBtn" class="btn btn-dark-green w-100">
                        <i class="bi bi-plus-lg me-2"></i> Add Product
                    </button>
                </div>

                <h6 class="form-label mt-4 mb-3">Current Items (<span id="itemCount">0</span>)</h6>
                <div id="invoiceItemsContainer">
                    <div class="text-center text-muted p-4">No products added yet</div>
                </div>

            </div>

            <div class="col-lg-4">
                
                <div class="sales-card">
                    <div class="totals-row">
                        <span>Sale Total</span>
                        <span id="saleTotal">AED 0.00</span>
                    </div>
                    <div class="totals-row" style="color: #991b1b;">
                        <span>Return Total</span>
                        <span id="returnTotal">AED 0.00</span>
                    </div>
                    <div class="totals-row">
                        <span>Net Sale</span>
                        <span id="invoiceTotal">AED 0.00</span>
                    </div>
                    <hr class="my-3">
                    <div class="totals-row align-items-center">
                        <div>
                            <div class="fw-bold text-dark" style="color: #7f1d1d !important;">Total Payable</div>
                            <small style="font-size: 0.65rem; color: #7f1d1d;">(INC. PREVIOUS CREDIT)</small>
                        </div>
                        <div class="fs-3 fw-bold" id="grandTotal" style="color: #7f1d1d;">0.00</div>
                    </div>
                </div>

                <div class="sales-card">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" step="0.01" name="payment_amount" id="paymentAmount" class="form-control" value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional delivery instructions..."></textarea>
                    </div>
                </div>

            </div>
        </div>

<div class="sticky-footer">
            <div class="d-flex justify-content-between align-items-center mb-3 d-md-none border-bottom pb-2">
                <div>
                    <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Net Sale</small>
                    <div class="fw-bold" id="footerNetSaleMobile">AED 0.00</div>
                </div>
                <div class="text-end">
                    <small class="text-uppercase fw-bold" style="font-size: 0.7rem; color: #7f1d1d;">Total Payable</small>
                    <div class="fw-bold fs-5" id="footerTotalPayableMobile" style="color: #7f1d1d;">0.00</div>
                </div>
            </div>

            <div class="d-flex justify-content-center justify-content-md-between align-items-center">
                <div class="d-none d-md-block">
                    <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Net Sale</small>
                    <div class="fw-bold" id="footerNetSale">AED 0.00</div>
                </div>
                
                <div class="d-flex gap-3 align-items-center w-100 w-md-auto justify-content-center justify-content-md-end">
                    <div class="me-3 text-end d-none d-md-block">
                        <small class="text-uppercase fw-bold" style="font-size: 0.7rem; color: #7f1d1d;">Total Payable</small>
                        <div class="fw-bold fs-5" id="footerTotalPayable" style="color: #7f1d1d;">0.00</div>
                    </div>
                    <button type="button" class="btn" style="background: #f3f4f6; color: #4b5563; font-weight: 600; padding: 12px 24px; border-radius: 8px;" onclick="window.location='dashboard.php'">Cancel</button>
                    <button type="submit" class="btn btn-dark-green d-flex align-items-center" style="padding: 12px 24px; border-radius: 8px;">
                        Create Invoice <i class="bi bi-file-earmark-text-fill ms-2"></i>
                    </button>
                </div>
            </div>

    </form>
</div>

<script>
    // Handles the custom UI toggle for Sale/Return
    function setType(type) {
        document.getElementById('itemType').value = type;
        const btnSale = document.getElementById('btnTypeSale');
        const btnRet = document.getElementById('btnTypeRet');
        
        if(type === 'sale') {
            btnSale.className = 'type-toggle-btn active-sale';
            btnRet.className = 'type-toggle-btn';
        } else {
            btnRet.className = 'type-toggle-btn active-return';
            btnSale.className = 'type-toggle-btn';
        }
    }

    // Enter key submit prevention
    document.getElementById('salesForm').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            document.getElementById('addProductBtn').click();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>