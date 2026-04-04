<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// ---------------------------------------------------------------
// AUTO-ABSORPTION HELPER
// When a new invoice is created and the vendor has older unpaid
// invoices, this marks all those older invoices as PAID (absorbed)
// by the new invoice. It creates a "Balance transferred to Invoice #X"
// payment record for audit trail on each absorbed invoice.
// The new invoice already carries the accumulated debt via PreviousCredit.
// ---------------------------------------------------------------
function absorbOlderInvoices(PDO $db, int $vendorId, int $newInvoiceId, string $invoiceDate): void {
    $stmt = $db->prepare('
        SELECT InvoiceID, TotalAmount, PreviousCredit, PaidAmount
        FROM Invoices
        WHERE VendorID = ?
          AND InvoiceID != ?
          AND (TotalAmount + PreviousCredit - PaidAmount) > 0.001
        ORDER BY InvoiceID ASC
    ');
    $stmt->execute([$vendorId, $newInvoiceId]);
    $oldInvoices = $stmt->fetchAll();

    $updateStmt = $db->prepare('UPDATE Invoices SET PaidAmount = TotalAmount + PreviousCredit WHERE InvoiceID = ?');
    $payStmt    = $db->prepare('INSERT INTO Payments (InvoiceID, PaymentDate, Amount, PaymentMethod, Notes) VALUES (?, ?, ?, ?, ?)');

    foreach ($oldInvoices as $inv) {
        $owed = round((float)$inv['TotalAmount'] + (float)$inv['PreviousCredit'] - (float)$inv['PaidAmount'], 2);
        if ($owed <= 0) continue;
        $updateStmt->execute([$inv['InvoiceID']]);
        $payStmt->execute([
            $inv['InvoiceID'],
            $invoiceDate,
            $owed,
            'System',
            'Balance transferred to Invoice #' . $newInvoiceId
        ]);
    }
}

try {

    // ==========================================
    // CREATE INVOICE (legacy action)
    // ==========================================
    if ($action === 'create') {
        $db->beginTransaction();

        $items = json_decode($_POST['items'], true);
        if (empty($items)) throw new Exception('No items in invoice');

        $vendorStmt = $db->prepare('SELECT CurrentCredit FROM Vendors WHERE VendorID = ?');
        $vendorStmt->execute([$_POST['vendor_id']]);
        $currentCredit = (float)$vendorStmt->fetchColumn();

        $totalAmount = 0;
        $stmtCheckStock = $db->prepare('SELECT StockQuantity, ProductName FROM Products WHERE ProductID = ?');

        foreach ($items as &$item) {
            $item['quantity'] = (int)$item['quantity'];
            $item['unitPrice'] = max(0, (float)$item['unitPrice']);
            $item['total'] = $item['quantity'] * $item['unitPrice'];

            if ($item['quantity'] <= 0) throw new Exception('Quantity must be greater than 0');

            $stmtCheckStock->execute([$item['productId']]);
            $product = $stmtCheckStock->fetch();
            if (!$product) throw new Exception('Product not found ID: ' . $item['productId']);
            if ($product['StockQuantity'] < $item['quantity']) {
                throw new Exception('Insufficient stock for ' . $product['ProductName'] . '. Available: ' . $product['StockQuantity']);
            }
            $totalAmount += $item['total'];
        }
        unset($item); // destroy reference from foreach &$item to prevent array corruption in subsequent loops

        $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
        $stmt = $db->prepare('INSERT INTO Invoices (VendorID, InvoiceDate, PreviousCredit, TotalAmount, PaidAmount, Notes) VALUES (?, ?, ?, ?, 0, ?)');
        $stmt->execute([$_POST['vendor_id'], $_POST['invoice_date'], $currentCredit, $totalAmount, $_POST['notes'] ?? '']);

        $invoiceId = $db->lastInsertId();

        $stmtLine  = $db->prepare('INSERT INTO InvoiceLines (InvoiceID, ProductID, Quantity, UnitPrice, TotalPrice) VALUES (?, ?, ?, ?, ?)');
        $stmtStock = $db->prepare('UPDATE Products SET StockQuantity = StockQuantity - ? WHERE ProductID = ?');

        foreach ($items as $item) {
            $stmtLine->execute([$invoiceId, $item['productId'], $item['quantity'], $item['unitPrice'], $item['total']]);
            $stmtStock->execute([$item['quantity'], $item['productId']]);
        }

        // Auto-absorb older unpaid invoices into this new invoice
        if ($currentCredit > 0.001) {
            absorbOlderInvoices($db, (int)$_POST['vendor_id'], $invoiceId, $_POST['invoice_date']);
        }

        $newCredit = $currentCredit + $totalAmount - $paymentAmount;
        $db->prepare('UPDATE Vendors SET CurrentCredit = ? WHERE VendorID = ?')->execute([$newCredit, $_POST['vendor_id']]);

        if ($paymentAmount > 0) {
            $db->prepare('UPDATE Invoices SET PaidAmount = PaidAmount + ? WHERE InvoiceID = ?')->execute([$paymentAmount, $invoiceId]);
            $db->prepare('INSERT INTO Payments (InvoiceID, PaymentDate, Amount, PaymentMethod, Notes) VALUES (?, ?, ?, ?, ?)')
               ->execute([$invoiceId, $_POST['invoice_date'], $paymentAmount, $_POST['payment_method'] ?? 'Cash', 'Initial payment for invoice #' . $invoiceId]);
        }

        // Re-sync any desynced balances inherited from older overlapping returns globally
        syncVendorBalances($db, (int)$_POST['vendor_id'], $_POST['invoice_date']);

        $db->commit();
        echo json_encode(['success' => true, 'invoice_id' => $invoiceId]);

    // ==========================================
    // CREATE INVOICE WITH RETURNS (main sales flow)
    // ==========================================
    } elseif ($action === 'create_with_returns') {
        $db->beginTransaction();

        $salesItems  = json_decode($_POST['sales_items'],  true) ?? [];
        $returnItems = json_decode($_POST['return_items'], true) ?? [];

        if (empty($salesItems) && empty($returnItems)) throw new Exception('No items in invoice');

        $vendorStmt = $db->prepare('SELECT CurrentCredit FROM Vendors WHERE VendorID = ?');
        $vendorStmt->execute([$_POST['vendor_id']]);
        $currentCredit = (float)$vendorStmt->fetchColumn();

        $stmtCheckStock = $db->prepare('SELECT StockQuantity, ProductName FROM Products WHERE ProductID = ?');
        
        $stmtLimit = $db->query("SELECT SettingValue FROM Settings WHERE SettingKey = 'ignore_stock_limits'");
        $unlimitedStock = $stmtLimit->fetchColumn() === '1';

        // Validate and total SALES
        $saleTotal = 0;
        foreach ($salesItems as &$item) {
            $item['quantity']  = (int)$item['quantity'];
            $item['unitPrice'] = max(0, (float)$item['unitPrice']);
            $item['total']     = $item['quantity'] * $item['unitPrice'];
            if ($item['quantity'] <= 0) throw new Exception('Invalid quantity');

            $stmtCheckStock->execute([$item['productId']]);
            $product = $stmtCheckStock->fetch();
            if (!$product) throw new Exception('Product not found: ' . $item['productId']);
            if (!$unlimitedStock && $product['StockQuantity'] < $item['quantity']) {
                throw new Exception('Insufficient stock for ' . $product['ProductName'] . '. Available: ' . $product['StockQuantity']);
            }
            $saleTotal += $item['total'];
        }
        unset($item); // destroy reference from foreach &$item to prevent array corruption in subsequent loops

        // Validate and total RETURNS
        $returnTotal = 0;
        foreach ($returnItems as &$item) {
            $item['quantity']  = (int)$item['quantity'];
            $item['unitPrice'] = max(0, (float)$item['unitPrice']);
            $item['total']     = $item['quantity'] * $item['unitPrice'];
            if ($item['quantity'] <= 0) throw new Exception('Invalid quantity');
            $returnTotal += $item['total'];
        }
        unset($item); // destroy reference from foreach &$item

        $netTotal      = $saleTotal - $returnTotal;
        $paymentAmount = (float)($_POST['payment_amount'] ?? 0);

        // Insert new invoice with PaidAmount = 0 initially (payment applied below)
        $stmt = $db->prepare('INSERT INTO Invoices (VendorID, InvoiceDate, PreviousCredit, TotalAmount, PaidAmount, Notes) VALUES (?, ?, ?, ?, 0, ?)');
        $stmt->execute([$_POST['vendor_id'], $_POST['invoice_date'], $currentCredit, $netTotal, $_POST['notes'] ?? '']);

        $invoiceId = $db->lastInsertId();

        // Process SALES
        $stmtLine  = $db->prepare('INSERT INTO InvoiceLines (InvoiceID, ProductID, Quantity, UnitPrice, TotalPrice) VALUES (?, ?, ?, ?, ?)');
        $stmtStock = $db->prepare('UPDATE Products SET StockQuantity = StockQuantity - ? WHERE ProductID = ?');
        foreach ($salesItems as $item) {
            $stmtLine->execute([$invoiceId, $item['productId'], $item['quantity'], $item['unitPrice'], $item['total']]);
            $stmtStock->execute([$item['quantity'], $item['productId']]);
        }

        // Process RETURNS
        $stmtReturn   = $db->prepare('INSERT INTO Returns (InvoiceID, ProductID, Quantity, UnitPrice, TotalPrice, ReturnDate, Reason) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmtStockAdd = $db->prepare('UPDATE Products SET StockQuantity = StockQuantity + ? WHERE ProductID = ?');
        foreach ($returnItems as $item) {
            $stmtReturn->execute([$invoiceId, $item['productId'], $item['quantity'], $item['unitPrice'], $item['total'], $_POST['invoice_date'], 'Return on invoice creation']);
            $stmtStockAdd->execute([$item['quantity'], $item['productId']]);
        }

        // Auto-absorb older unpaid invoices into this new invoice.
        // Older invoices get marked as paid (their balance "transferred" here).
        // The new invoice already carries the accumulated debt via PreviousCredit.
        if ($currentCredit > 0.001) {
            absorbOlderInvoices($db, (int)$_POST['vendor_id'], $invoiceId, $_POST['invoice_date']);
        }

        $newCredit = $currentCredit + $netTotal - $paymentAmount;
        $db->prepare('UPDATE Vendors SET CurrentCredit = ? WHERE VendorID = ?')->execute([$newCredit, $_POST['vendor_id']]);

        if ($paymentAmount > 0) {
            $db->prepare('UPDATE Invoices SET PaidAmount = PaidAmount + ? WHERE InvoiceID = ?')->execute([$paymentAmount, $invoiceId]);
            $db->prepare('INSERT INTO Payments (InvoiceID, PaymentDate, Amount, PaymentMethod, Notes) VALUES (?, ?, ?, ?, ?)')
               ->execute([$invoiceId, $_POST['invoice_date'], $paymentAmount, $_POST['payment_method'] ?? 'Cash', 'Initial payment for invoice #' . $invoiceId]);
        }

        // Re-sync any desynced balances inherited from older overlapping returns globally
        syncVendorBalances($db, (int)$_POST['vendor_id'], $_POST['invoice_date']);

        $db->commit();
        echo json_encode(['success' => true, 'invoice_id' => $invoiceId]);

    // ==========================================
    // LIST INVOICES
    // ==========================================
    } elseif ($action === 'list') {
        $invoices = $db->query('
            SELECT i.*, v.Name as VendorName
            FROM Invoices i
            LEFT JOIN Vendors v ON i.VendorID = v.VendorID
            ORDER BY i.InvoiceDate DESC
        ')->fetchAll();

        echo json_encode(['success' => true, 'invoices' => $invoices]);

    // ==========================================
    // INVOICE DETAILS
    // ==========================================
    } elseif ($action === 'details') {
        $invoiceId = $_GET['invoice_id'];

        $invoice = $db->prepare('
            SELECT i.*, v.Name as VendorName, v.Phone as VendorPhone
            FROM Invoices i
            LEFT JOIN Vendors v ON i.VendorID = v.VendorID
            WHERE i.InvoiceID = ?
        ');
        $invoice->execute([$invoiceId]);
        $invoice = $invoice->fetch();

        // Explicitly alias columns to prevent shadowing; include remaining returnable qty
        $items = $db->prepare('
            SELECT
                il.ProductID,
                il.Quantity                                  AS InvoiceQty,
                COALESCE((
                    SELECT SUM(r.Quantity) FROM Returns r
                    WHERE r.InvoiceID = il.InvoiceID AND r.ProductID = il.ProductID
                ), 0)                                        AS AlreadyReturnedQty,
                (il.Quantity - COALESCE((
                    SELECT SUM(r.Quantity) FROM Returns r
                    WHERE r.InvoiceID = il.InvoiceID AND r.ProductID = il.ProductID
                ), 0))                                       AS RemainingReturnableQty,
                il.UnitPrice                                 AS InvoiceUnitPrice,
                il.TotalPrice                                AS InvoiceTotalPrice,
                p.ProductName,
                p.UnitPrice                                  AS CatalogUnitPrice
            FROM InvoiceLines il
            LEFT JOIN Products p ON il.ProductID = p.ProductID
            WHERE il.InvoiceID = ?
        ');
        $items->execute([$invoiceId]);
        $items = $items->fetchAll();

        $payments = $db->prepare('SELECT * FROM Payments WHERE InvoiceID = ? ORDER BY PaymentDate');
        $payments->execute([$invoiceId]);
        $payments = $payments->fetchAll();

        echo json_encode(['success' => true, 'invoice' => $invoice, 'items' => $items, 'payments' => $payments]);

    // ==========================================
    // DELETE INVOICE
    // ==========================================
    } elseif ($action === 'delete') {
        $invoiceId = $_POST['invoice_id'];

        $db->beginTransaction();

        // 1. Get invoice details to reverse financials
        $stmtInv = $db->prepare('SELECT VendorID, TotalAmount FROM Invoices WHERE InvoiceID = ?');
        $stmtInv->execute([$invoiceId]);
        $invoice = $stmtInv->fetch();

        if (!$invoice) throw new Exception('Invoice not found');

        // 2. Reverse the purchase debt on the vendor
        //    Payments already made stay on their account as a positive credit balance (per business requirement)
        $db->prepare('UPDATE Vendors SET CurrentCredit = CurrentCredit - ? WHERE VendorID = ?')
           ->execute([$invoice['TotalAmount'], $invoice['VendorID']]);

        // 3. Restore stock — only items NOT already returned
        $items = $db->prepare('
            SELECT il.ProductID,
                   il.Quantity                                              AS BoughtQty,
                   COALESCE(SUM(r.Quantity), 0)                            AS ReturnedQty
            FROM InvoiceLines il
            LEFT JOIN Returns r ON il.InvoiceID = r.InvoiceID AND il.ProductID = r.ProductID
            WHERE il.InvoiceID = ?
            GROUP BY il.ProductID
        ');
        $items->execute([$invoiceId]);
        $items = $items->fetchAll();

        $stmtStock = $db->prepare('UPDATE Products SET StockQuantity = StockQuantity + ? WHERE ProductID = ?');
        foreach ($items as $item) {
            $netQtyToRestore = $item['BoughtQty'] - $item['ReturnedQty'];
            if ($netQtyToRestore > 0) {
                $stmtStock->execute([$netQtyToRestore, $item['ProductID']]);
            }
        }

        // 4. Delete all associated records
        $db->prepare('DELETE FROM Returns     WHERE InvoiceID = ?')->execute([$invoiceId]);
        $db->prepare('DELETE FROM Payments    WHERE InvoiceID = ?')->execute([$invoiceId]);
        $db->prepare('DELETE FROM InvoiceLines WHERE InvoiceID = ?')->execute([$invoiceId]);
        $db->prepare('DELETE FROM Invoices    WHERE InvoiceID = ?')->execute([$invoiceId]);

        // Re-sync any desynced balances inherited from older overlapping returns globally
        syncVendorBalances($db, (int)$invoice['VendorID'], date('Y-m-d'));

        $db->commit();
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
