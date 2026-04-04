<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
    if ($action === 'add') {
        $db->beginTransaction();

        // Validate payment
        $paymentAmount = (float)$_POST['amount'];
        if ($paymentAmount <= 0) throw new Exception('Payment amount must be greater than 0');

        $targetInvoiceId = (int)$_POST['invoice_id'];

        // Fetch target invoice to get VendorID and its own outstanding balance
        $invStmt = $db->prepare('SELECT VendorID, TotalAmount, PreviousCredit, PaidAmount FROM Invoices WHERE InvoiceID = ?');
        $invStmt->execute([$targetInvoiceId]);
        $targetInvoice = $invStmt->fetch();
        if (!$targetInvoice) throw new Exception('Invoice not found');

        $vendorId = $targetInvoice['VendorID'];

        $pool = $paymentAmount;

        $updateStmt = $db->prepare('UPDATE Invoices SET PaidAmount = PaidAmount + ? WHERE InvoiceID = ?');
        $insertPmt  = $db->prepare('INSERT INTO Payments (InvoiceID, PaymentDate, Amount, PaymentMethod, Notes) VALUES (?, ?, ?, ?, ?)');

        // ---------------------------------------------------------------
        // PAYMENT WATERFALL — CURRENT INVOICE FIRST, THEN OLDER (FIFO)
        // Step 1: Apply payment to the TARGET (current) invoice first.
        // Step 2: If anything remains, apply to older unpaid invoices.
        // ---------------------------------------------------------------

        // STEP 1 — Fill the current (selected) invoice first
        $targetOwed = round(
            (float)$targetInvoice['TotalAmount'] + (float)$targetInvoice['PreviousCredit'] - (float)$targetInvoice['PaidAmount'],
            2
        );

        if ($targetOwed > 0 && $pool > 0) {
            $applyToTarget = min($pool, $targetOwed);
            $pool          = round($pool - $applyToTarget, 2);
            $updateStmt->execute([$applyToTarget, $targetInvoiceId]);
            $insertPmt->execute([
                $targetInvoiceId,
                $_POST['payment_date'],
                $applyToTarget,
                $_POST['payment_method'],
                $_POST['notes'] ?? ''
            ]);
        } elseif ($targetOwed <= 0) {
            // Target already fully paid — record the payment against it anyway (overpayment)
            $updateStmt->execute([$pool, $targetInvoiceId]);
            $insertPmt->execute([
                $targetInvoiceId,
                $_POST['payment_date'],
                $pool,
                $_POST['payment_method'],
                $_POST['notes'] ?? ''
            ]);
            $pool = 0;
        }

        // STEP 2 — Apply remaining pool to older unpaid invoices (oldest first)
        if ($pool > 0.001) {
            $unpaidStmt = $db->prepare('
                SELECT InvoiceID, TotalAmount, PreviousCredit, PaidAmount
                FROM Invoices
                WHERE VendorID = ?
                  AND InvoiceID != ?
                  AND (TotalAmount + PreviousCredit - PaidAmount) > 0.001
                ORDER BY InvoiceID ASC
            ');
            $unpaidStmt->execute([$vendorId, $targetInvoiceId]);
            $olderInvoices = $unpaidStmt->fetchAll();

            foreach ($olderInvoices as $inv) {
                if ($pool <= 0) break;
                $owed  = round((float)$inv['TotalAmount'] + (float)$inv['PreviousCredit'] - (float)$inv['PaidAmount'], 2);
                if ($owed <= 0) continue;
                $apply = min($pool, $owed);
                $pool  = round($pool - $apply, 2);
                $updateStmt->execute([$apply, $inv['InvoiceID']]);
                $insertPmt->execute([
                    $inv['InvoiceID'],
                    $_POST['payment_date'],
                    $apply,
                    $_POST['payment_method'],
                    'Overflow from payment on Invoice #' . $targetInvoiceId
                ]);
            }
        }

        // Reduce the vendor's total outstanding credit by the full payment
        $db->prepare('UPDATE Vendors SET CurrentCredit = CurrentCredit - ? WHERE VendorID = ?')
           ->execute([$paymentAmount, $vendorId]);

        // Re-sync any desynced balances
        syncVendorBalances($db, (int)$vendorId, $_POST['payment_date']);

        $db->commit();
        echo json_encode(['success' => true]);

    } elseif ($action === 'list') {
        $payments = $db->query('
            SELECT p.*, i.InvoiceID, v.Name as VendorName
            FROM Payments p
            LEFT JOIN Invoices i ON p.InvoiceID = i.InvoiceID
            LEFT JOIN Vendors v ON i.VendorID = v.VendorID
            ORDER BY p.PaymentDate DESC
        ')->fetchAll();

        echo json_encode(['success' => true, 'payments' => $payments]);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
