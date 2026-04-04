<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();
$vendors = $db->query('SELECT * FROM Vendors ORDER BY VendorID DESC')->fetchAll();

$pageTitle = 'Vendors';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <h4>Total Vendors: <?= count($vendors) ?></h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVendorModal">
        <i class="bi bi-plus-circle"></i> Add Vendor
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Current Credit</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): ?>
                        <?php
                        $credit = $v['CurrentCredit'] ?? 0;
                        $owesVendor = $credit < 0; // Negative means we owe vendor
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($v['VendorID'] ?? '') ?></td>
                            <td><?= htmlspecialchars($v['Name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($v['Phone'] ?? '') ?></td>
                            <td class="<?= $credit > 0 ? 'text-danger' : ($credit < 0 ? 'text-success' : 'text-muted') ?>">
                                <?= formatCurrency(abs($credit)) ?>
                                <?php if ($credit > 0): ?>
                                    <small>(Vendor owes us)</small>
                                <?php elseif ($credit < 0): ?>
                                    <small>(We owe vendor)</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($v['Notes'] ?? '') ?></td>
                            <td>
                                <?php if ($owesVendor): ?>
                                    <button class="btn btn-sm btn-success payVendorBtn" data-id="<?= $v['VendorID'] ?>"
                                        data-name="<?= htmlspecialchars($v['Name'] ?? '') ?>" data-amount="<?= abs($credit) ?>"
                                        title="Pay Vendor">
                                        <i class="bi bi-cash-coin"></i> Pay
                                    </button>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-warning editVendorBtn" data-id="<?= $v['VendorID'] ?>"
                                    data-name="<?= htmlspecialchars($v['Name'] ?? '') ?>"
                                    data-phone="<?= htmlspecialchars($v['Phone'] ?? '') ?>" data-credit="<?= $credit ?>"
                                    data-notes="<?= htmlspecialchars($v['Notes'] ?? '') ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger deleteVendorBtn" data-id="<?= $v['VendorID'] ?>">
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

<!-- Add Vendor Modal -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addVendorForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vendor Name *</label>
                        <input type="text" name="vendor_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Credit</label>
                        <input type="number" step="0.01" name="credit" class="form-control" value="0">
                        <small class="text-muted">Use negative value if you owe vendor, positive if they owe you</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Vendor Modal -->
<div class="modal fade" id="editVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editVendorForm">
                <input type="hidden" name="vendor_id" id="edit_vendor_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vendor Name *</label>
                        <input type="text" name="vendor_name" id="edit_vendor_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Credit</label>
                        <input type="number" step="0.01" name="credit" id="edit_credit" class="form-control">
                        <small class="text-muted">Use negative value if you owe vendor, positive if they owe you</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Pay Vendor Modal -->
<div class="modal fade" id="payVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Pay Vendor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="payVendorForm">
                <input type="hidden" name="vendor_id" id="pay_vendor_id">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Vendor:</strong> <span id="pay_vendor_name"></span><br>
                        <strong>Amount Owed:</strong> <span id="pay_vendor_amount" class="text-danger"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Amount *</label>
                        <input type="number" step="0.01" name="payment_amount" id="pay_amount_input"
                            class="form-control" required min="0.01">
                        <small class="text-muted">Enter amount you're paying to vendor</small>
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
                            placeholder="Payment reference or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cash-coin"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include 'includes/footer.php'; ?>