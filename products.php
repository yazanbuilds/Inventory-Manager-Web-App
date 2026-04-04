<?php
require_once 'includes/config.php';
requireLogin();

$db = getDB();
$search = $_GET['search'] ?? '';
$idOnly = isset($_GET['idOnly']) ? true : false;
$sortBy = $_GET['sort'] ?? 'id_desc'; // Default sort by ID descending

// Check system behavior setting
$stmtLimit = $db->query("SELECT SettingValue FROM Settings WHERE SettingKey = 'ignore_stock_limits'");
$unlimitedStock = $stmtLimit->fetchColumn() === '1';

// Determine sort column and order
$sortOptions = [
    'id_asc' => 'ProductID ASC',
    'id_desc' => 'ProductID DESC',
    'name_asc' => 'ProductName ASC',
    'name_desc' => 'ProductName DESC',
    'price_asc' => 'UnitPrice ASC',
    'price_desc' => 'UnitPrice DESC',
    'stock_asc' => 'StockQuantity ASC',
    'stock_desc' => 'StockQuantity DESC'
];

$orderBy = $sortOptions[$sortBy] ?? 'ProductID DESC';

if ($search) {
    if ($idOnly) {
        // Search EXACT match for ID only
        $stmt = $db->prepare("SELECT * FROM Products WHERE ProductID = ? ORDER BY $orderBy");
        $stmt->execute([$search]);
    } else {
        // Search by both ProductName and ProductID
        $stmt = $db->prepare("SELECT * FROM Products WHERE ProductName LIKE ? OR ProductID LIKE ? ORDER BY $orderBy");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
    }
    $products = $stmt->fetchAll();
} else {
    $products = $db->query("SELECT * FROM Products ORDER BY $orderBy")->fetchAll();
}

$pageTitle = 'Products';
include 'includes/header.php';
?>

<!-- Mobile-Friendly Search and Add Button -->
<div class="mb-3">
    <div class="d-flex flex-column gap-2 mb-2">
        <div class="d-flex flex-column flex-md-row gap-2" id="searchForm">
            <input type="text" id="searchInput" class="form-control flex-grow-1" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
            
            <div class="form-check d-flex align-items-center ps-md-2">
                <input class="form-check-input" type="checkbox" id="idOnly" name="idOnly" <?= $idOnly ? 'checked' : '' ?>>
                <label class="form-check-label ps-2 mb-0" for="idOnly">ID Only</label>
            </div>
            
            <select id="sortSelect" class="form-select" style="width: auto;">
                <option value="id_desc" <?= $sortBy == 'id_desc' ? 'selected' : '' ?>>ID (High to Low)</option>
                <option value="id_asc" <?= $sortBy == 'id_asc' ? 'selected' : '' ?>>ID (Low to High)</option>
                <option value="name_asc" <?= $sortBy == 'name_asc' ? 'selected' : '' ?>>Name (A to Z)</option>
                <option value="name_desc" <?= $sortBy == 'name_desc' ? 'selected' : '' ?>>Name (Z to A)</option>
                <option value="price_asc" <?= $sortBy == 'price_asc' ? 'selected' : '' ?>>Price (Low to High)</option>
                <option value="price_desc" <?= $sortBy == 'price_desc' ? 'selected' : '' ?>>Price (High to Low)</option>
                <option value="stock_asc" <?= $sortBy == 'stock_asc' ? 'selected' : '' ?>>Stock (Low to High)</option>
                <option value="stock_desc" <?= $sortBy == 'stock_desc' ? 'selected' : '' ?>>Stock (High to Low)</option>
            </select>
        </div>
    </div>

    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="bi bi-plus-circle"></i> Add Product
    </button>
</div>

<!-- Products Table - Responsive Card View on Mobile -->
<div class="card">
    <div class="card-body">
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Unit Price</th>
                        <th>Stock Qty</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTable">
                    <?php foreach ($products as $p): ?>
                    <tr data-id="<?= $p['ProductID'] ?>">
                        <td><?= htmlspecialchars($p['ProductID']) ?></td>
                        <td><?= htmlspecialchars($p['ProductName']) ?></td>
                        <td><?= formatCurrency($p['UnitPrice']) ?></td>
                        <td><?= $unlimitedStock ? '<span class="badge bg-success">Unlimited</span>' : $p['StockQuantity'] ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning editBtn" data-id="<?= $p['ProductID'] ?>" data-name="<?= htmlspecialchars($p['ProductName']) ?>" data-price="<?= $p['UnitPrice'] ?>" data-stock="<?= $p['StockQuantity'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger deleteBtn" data-id="<?= $p['ProductID'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div id="productsCardView" class="d-lg-none">
            <?php foreach ($products as $p): ?>
            <div class="product-card mb-3" data-id="<?= $p['ProductID'] ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="card-title mb-1"><?= htmlspecialchars($p['ProductName']) ?></h6>
                                <small class="text-muted">ID: <?= htmlspecialchars($p['ProductID']) ?></small>
                            </div>
                            <?php if ($unlimitedStock): ?>
                                <span class="badge bg-success">Unlimited</span>
                            <?php else: ?>
                                <span class="badge bg-info"><?= $p['StockQuantity'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="card-text mb-2">
                            <strong><?= formatCurrency($p['UnitPrice']) ?></strong>
                        </p>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-warning flex-grow-1 editBtn" data-id="<?= $p['ProductID'] ?>" data-name="<?= htmlspecialchars($p['ProductName']) ?>" data-price="<?= $p['UnitPrice'] ?>" data-stock="<?= $p['StockQuantity'] ?>">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger flex-grow-1 deleteBtn" data-id="<?= $p['ProductID'] ?>">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProductForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product ID</label>
                        <input type="text" name="product_id" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="product_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" step="0.01" name="unit_price" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_qty" class="form-control" value="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProductForm">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" step="0.01" name="unit_price" id="edit_unit_price" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_qty" id="edit_stock_qty" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>




<?php include 'includes/footer.php'; ?>
