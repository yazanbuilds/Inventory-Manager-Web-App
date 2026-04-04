<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$action = $_POST['action'] ?? 'add';

try {
    if ($action === 'add') {
        $stmt = $db->prepare('INSERT INTO Vendors (Name, Phone, CurrentCredit, Notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $_POST['vendor_name'],
            $_POST['phone'],
            $_POST['credit'] ?? 0,
            $_POST['notes'] ?? ''
        ]);
        echo json_encode(['success' => true, 'vendor_id' => $db->lastInsertId()]);
        
    } elseif ($action === 'update') {
        $stmt = $db->prepare('UPDATE Vendors SET Name = ?, Phone = ?, CurrentCredit = ?, Notes = ? WHERE VendorID = ?');
        $stmt->execute([
            $_POST['vendor_name'],
            $_POST['phone'],
            $_POST['credit'],
            $_POST['notes'],
            $_POST['vendor_id']
        ]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM Vendors WHERE VendorID = ?');
        $stmt->execute([$_POST['vendor_id']]);
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
if ($action === 'pay_vendor') {
    try {
        $vendorId = $_POST['vendor_id'];
        $paymentAmount = floatval($_POST['payment_amount']);
        $paymentDate = $_POST['payment_date'];
        $paymentMethod = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';
        
        // Reduce vendor credit (add payment as negative reduces debt)
        $stmt = $db->prepare('UPDATE Vendors SET CurrentCredit = CurrentCredit + ? WHERE VendorID = ?');
        $stmt->execute([$paymentAmount, $vendorId]);
        
        // Optional: Log this payment somewhere for records
        // You could create a VendorPayments table to track these
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
