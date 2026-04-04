<?php
// includes/ledger.php

/**
 * Self-healing ledger autocorrection mechanism.
 * Dynamically reallocates overpayments (negative balances from returns) across the oldest unpaid invoices.
 *
 * @param PDO $db The database connection.
 * @param int $vendorId The vendor ID to synchronize.
 * @param ?string $date The transaction date to record for system corrections.
 */
function syncVendorBalances(PDO $db, int $vendorId, ?string $date = null) {
    if (!$date) $date = date('Y-m-d');
    
    // 1. Find all overpaid invoices where (TotalAmount + PreviousCredit - PaidAmount) < -0.001
    // Meaning the vendor has overpaid or "over-returned" relative to this specific invoice's history.
    $stmt = $db->prepare('
        SELECT InvoiceID, (PaidAmount - (TotalAmount + PreviousCredit)) AS OverpayAmount
        FROM Invoices
        WHERE VendorID = ?
        AND (TotalAmount + PreviousCredit - PaidAmount) < -0.001
        ORDER BY InvoiceDate ASC, InvoiceID ASC
    ');
    $stmt->execute([$vendorId]);
    $overpaidInvoices = $stmt->fetchAll();

    if (empty($overpaidInvoices)) return;

    $updatePaid = $db->prepare('UPDATE Invoices SET PaidAmount = PaidAmount - ? WHERE InvoiceID = ?');
    $insertPmt = $db->prepare('INSERT INTO Payments (InvoiceID, PaymentDate, Amount, PaymentMethod, Notes) VALUES (?, ?, ?, ?, ?)');

    $totalOverpayPool = 0;
    foreach ($overpaidInvoices as $inv) {
        $amt = round((float)$inv['OverpayAmount'], 2);
        if ($amt <= 0) continue;

        // Withdraw the overpayment from this invoice to balance it out exactly at 0.
        $updatePaid->execute([$amt, $inv['InvoiceID']]);
        $insertPmt->execute([$inv['InvoiceID'], $date, -$amt, 'System', 'Credit withdrawn to balance other invoices']);
        $totalOverpayPool += $amt;
    }

    $totalOverpayPool = round($totalOverpayPool, 2);
    if ($totalOverpayPool <= 0) return;

    // 2. Waterfall distribution: Apply the extracted credit pool sequentially to any existing UNPAID invoices.
    $stmtUnpaid = $db->prepare('
        SELECT InvoiceID, (TotalAmount + PreviousCredit - PaidAmount) AS Owed
        FROM Invoices
        WHERE VendorID = ?
        AND (TotalAmount + PreviousCredit - PaidAmount) > 0.001
        ORDER BY InvoiceDate ASC, InvoiceID ASC
    ');
    $stmtUnpaid->execute([$vendorId]);
    $unpaidInvoices = $stmtUnpaid->fetchAll();

    $applyPaid = $db->prepare('UPDATE Invoices SET PaidAmount = PaidAmount + ? WHERE InvoiceID = ?');

    foreach ($unpaidInvoices as $inv) {
        if ($totalOverpayPool <= 0.001) break;
        
        $owed = round((float)$inv['Owed'], 2);
        $applied = min($totalOverpayPool, $owed);
        
        $applyPaid->execute([$applied, $inv['InvoiceID']]);
        $insertPmt->execute([$inv['InvoiceID'], $date, $applied, 'System', 'Credit applied from overpayment balance']);
        
        $totalOverpayPool -= $applied;
        $totalOverpayPool = round($totalOverpayPool, 2);
    }

    // 3. Settling Remnant: If there are no unpaid invoices left, but we still hold credit, pin it to the latest invoice securely.
    if ($totalOverpayPool > 0.001) {
        $stmtLatest = $db->prepare('SELECT InvoiceID FROM Invoices WHERE VendorID = ? ORDER BY InvoiceDate DESC, InvoiceID DESC LIMIT 1');
        $stmtLatest->execute([$vendorId]);
        $latestId = $stmtLatest->fetchColumn();

        if ($latestId) {
            $applyPaid->execute([$totalOverpayPool, $latestId]);
            $insertPmt->execute([$latestId, $date, $totalOverpayPool, 'System', 'Net vendor credit retained']);
        }
    }
}
?>
