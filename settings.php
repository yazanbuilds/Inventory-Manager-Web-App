<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'save_settings') {
            try {
                $stmt = $db->prepare('INSERT OR REPLACE INTO Settings (SettingKey, SettingValue) VALUES (?, ?)');
                
                if (isset($_POST['company_name'])) {
                    // Update main details form
                    $settings = [
                        'company_name', 'company_logo', 
                        'branch1_name', 'branch1_address', 'branch1_phone',
                        'branch2_name', 'branch2_address', 'branch2_phone',
                        'account_title',
                        'bank1_name', 'bank1_account',
                        'bank2_name', 'bank2_account',
                        'bank3_name', 'bank3_account'
                    ];
                } elseif (isset($_POST['form_type']) && $_POST['form_type'] === 'behaviors') {
                    // Update behaviors form
                    $settings = ['ignore_stock_limits'];
                } else {
                    $settings = [];
                }
                
                foreach ($settings as $key) {
                    $value = $_POST[$key] ?? '';
                    $stmt->execute([$key, $value]);
                }
                
                $message = '<div class="alert alert-success">Settings saved successfully!</div>';
            } catch (Exception $e) {
                $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            }
        } elseif ($_POST['action'] === 'reset_database') {
            if ($_POST['confirm_reset'] === 'RESET') {
                try {
                    $db->exec('DELETE FROM Payments');
                    $db->exec('DELETE FROM Returns');
                    $db->exec('DELETE FROM InvoiceLines');
                    $db->exec('DELETE FROM Invoices');
                    $db->exec('DELETE FROM Products');
                    $db->exec('DELETE FROM Vendors');
                    $db->exec('UPDATE sqlite_sequence SET seq = 0');
                    
                    $message = '<div class="alert alert-success">Database reset successfully! All data deleted.</div>';
                } catch (Exception $e) {
                    $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">Incorrect confirmation. Type RESET to confirm.</div>';
            }
        }
    }
}

// Load current settings
$stmt = $db->query('SELECT SettingKey, SettingValue FROM Settings');
$currentSettings = [];
while ($row = $stmt->fetch()) {
    $currentSettings[$row['SettingKey']] = $row['SettingValue'];
}

function getSetting($key, $default = '') {
    global $currentSettings;
    return $currentSettings[$key] ?? $default;
}

$pageTitle = 'Settings';
include 'includes/header.php';
?>

<?= $message ?>

<div class="row">
    <!-- Company Information -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="bi bi-building"></i> Company Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name *</label>
                            <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars(getSetting('company_name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Logo URL</label>
                            <input type="text" name="company_logo" class="form-control" value="<?= htmlspecialchars(getSetting('company_logo')) ?>" placeholder="https://example.com/logo.png">
                            <small class="text-muted">Enter image URL or leave blank</small>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Branch Information</h6>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Branch 1 Name</label>
                            <input type="text" name="branch1_name" class="form-control" value="<?= htmlspecialchars(getSetting('branch1_name')) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Branch 1 Address</label>
                            <input type="text" name="branch1_address" class="form-control" value="<?= htmlspecialchars(getSetting('branch1_address')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Branch 1 Phone</label>
                            <input type="text" name="branch1_phone" class="form-control" value="<?= htmlspecialchars(getSetting('branch1_phone')) ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Branch 2 Name</label>
                            <input type="text" name="branch2_name" class="form-control" value="<?= htmlspecialchars(getSetting('branch2_name')) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Branch 2 Address</label>
                            <input type="text" name="branch2_address" class="form-control" value="<?= htmlspecialchars(getSetting('branch2_address')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Branch 2 Phone</label>
                            <input type="text" name="branch2_phone" class="form-control" value="<?= htmlspecialchars(getSetting('branch2_phone')) ?>">
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Bank Details</h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Title (Same for all banks)</label>
                        <input type="text" name="account_title" class="form-control" value="<?= htmlspecialchars(getSetting('account_title')) ?>">
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank 1 Name</label>
                            <input type="text" name="bank1_name" class="form-control" value="<?= htmlspecialchars(getSetting('bank1_name')) ?>" placeholder="e.g., Allied Bank">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank 1 Account Number</label>
                            <input type="text" name="bank1_account" class="form-control" value="<?= htmlspecialchars(getSetting('bank1_account')) ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank 2 Name</label>
                            <input type="text" name="bank2_name" class="form-control" value="<?= htmlspecialchars(getSetting('bank2_name')) ?>" placeholder="e.g., Meezan Bank">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank 2 Account Number</label>
                            <input type="text" name="bank2_account" class="form-control" value="<?= htmlspecialchars(getSetting('bank2_account')) ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank 3 Name</label>
                            <input type="text" name="bank3_name" class="form-control" value="<?= htmlspecialchars(getSetting('bank3_name')) ?>" placeholder="e.g., HBL">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank 3 Account Number</label>
                            <input type="text" name="bank3_account" class="form-control" value="<?= htmlspecialchars(getSetting('bank3_account')) ?>">
                        </div>
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- System Behaviors & DB Management -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5><i class="bi bi-gear"></i> System Behaviors</h5>
            </div>
            <div class="card-body">
                <h6>Inventory Restrictions</h6>
                <p class="text-muted">Disable strict stock checks globally. When enabled, products will display "Unlimited" stock and can be sold infinitely.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="form_type" value="behaviors">
                    <div class="form-check form-switch mb-3 mt-2 fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" name="ignore_stock_limits" id="ignoreStockLimits" value="1" <?= getSetting('ignore_stock_limits') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ignoreStockLimits">Sell Unlimited Stock</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary mt-2">
                        <i class="bi bi-save"></i> Save Behaviors
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5><i class="bi bi-exclamation-triangle"></i> Danger Zone</h5>
            </div>
            <div class="card-body">
                <h6>Reset Database</h6>
                <p class="text-muted">This will permanently delete all data including products, vendors, invoices, payments, and returns. This action cannot be undone!</p>
                
                <form method="POST" onsubmit="return confirm('Are you absolutely sure? This will delete ALL data permanently!')">
                    <input type="hidden" name="action" value="reset_database">
                    <div class="mb-3">
                        <label class="form-label">Type <strong>RESET</strong> to confirm:</label>
                        <input type="text" name="confirm_reset" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Reset Database
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
