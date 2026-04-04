<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
    if ($action === 'add') {
        $db->beginTransaction();
        
        $invoiceId = $_POST['invoice_id'];
        $productId = $_POST['product_id'];
        $requestedQty = (int)$_POST['quantity'];
        
        if ($requestedQty <= 0) throw new Exception('Invalid return quantity');
        
        // 1. Fetch exact price and quantity bought
        $stmtLine = $db->prepare('SELECT Quantity, UnitPrice FROM InvoiceLines WHERE InvoiceID = ? AND ProductID = ?');
        $stmtLine->execute([$invoiceId, $productId]);
        $invoiceLine = $stmtLine->fetch();
        
        if (!$invoiceLine) {
            throw new Exception('Product was not part of this invoice.');
        }
        
        // 2. Fetch how many items have ALREADY been returned
        $stmtReturns = $db->prepare('SELECT COALESCE(SUM(Quantity), 0) FROM Returns WHERE InvoiceID = ? AND ProductID = ?');
        $stmtReturns->execute([$invoiceId, $productId]);
        $previouslyReturnedQty = (int)$stmtReturns->fetchColumn();
        
        // 3. Mathematical enforcement
        $maxReturnable = $invoiceLine['Quantity'] - $previouslyReturnedQty;
        if ($requestedQty > $maxReturnable) {
            throw new Exception("Cannot return $requestedQty. Only $maxReturnable remaining to be returned.");
        }
        
        // 4. Force exact original pricing
        $unitPrice = (float)$invoiceLine['UnitPrice'];
        $totalPrice = $requestedQty * $unitPrice;
        
        $returnDate = $_POST['return_date'];
        $reason = $_POST['reason'] ?? '';

        
        // Insert return record
        $stmt = $db->prepare('
            INSERT INTO Returns (InvoiceID, ProductID, Quantity, UnitPrice, TotalPrice, ReturnDate, Reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$invoiceId, $productId, $requestedQty, $unitPrice, $totalPrice, $returnDate, $reason]);
        
        // Add quantity back to stock
        $stmt = $db->prepare('UPDATE Products SET StockQuantity = StockQuantity + ? WHERE ProductID = ?');
        $stmt->execute([$requestedQty, $productId]);
        
        // Update invoice total amount (reduce by return amount)
        $stmt = $db->prepare('UPDATE Invoices SET TotalAmount = TotalAmount - ? WHERE InvoiceID = ?');
        $stmt->execute([$totalPrice, $invoiceId]);
        
        // Update vendor credit (reduce by return amount)
        $invoice = $db->prepare('SELECT VendorID FROM Invoices WHERE InvoiceID = ?');
        $invoice->execute([$invoiceId]);
        $vendorId = $invoice->fetchColumn();
        
        $stmt = $db->prepare('UPDATE Vendors SET CurrentCredit = CurrentCredit - ? WHERE VendorID = ?');
        $stmt->execute([$totalPrice, $vendorId]);
        
        // Re-sync any desynced overpaid balances caused by return values shattering older structured debts.
        syncVendorBalances($db, (int)$vendorId, $returnDate);
        
        $db->commit();
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'list') {
        $invoiceId = $_GET['invoice_id'];
        
        $stmt = $db->prepare('
            SELECT r.*, p.ProductName
            FROM Returns r
            LEFT JOIN Products p ON r.ProductID = p.ProductID
            WHERE r.InvoiceID = ?
            ORDER BY r.ReturnDate DESC
        ');
        $stmt->execute([$invoiceId]);
        $returns = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'returns' => $returns]);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
