<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? 'summary';

try {
    if ($action === 'summary') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $summary = [
            'total_sales' => $db->prepare('SELECT SUM(TotalAmount) FROM Invoices WHERE date(InvoiceDate) BETWEEN ? AND ?'),
            'total_invoices' => $db->prepare('SELECT COUNT(*) FROM Invoices WHERE date(InvoiceDate) BETWEEN ? AND ?'),
            'total_payments' => $db->prepare('SELECT SUM(Amount) FROM Payments WHERE date(PaymentDate) BETWEEN ? AND ?'),
            'outstanding' => $db->prepare('SELECT SUM(TotalAmount - PaidAmount) FROM Invoices WHERE date(InvoiceDate) BETWEEN ? AND ?')
        ];
        
        $result = [];
        foreach ($summary as $key => $stmt) {
            $stmt->execute([$dateFrom, $dateTo]);
            $result[$key] = $stmt->fetchColumn() ?? 0;
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
        
    } elseif ($action === 'product_sales') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $stmt = $db->prepare('
            SELECT p.ProductName, SUM(il.Quantity) as TotalSold, SUM(il.TotalPrice) as Revenue
            FROM InvoiceLines il
            JOIN Products p ON il.ProductID = p.ProductID
            JOIN Invoices i ON il.InvoiceID = i.InvoiceID
            WHERE date(i.InvoiceDate) BETWEEN ? AND ?
            GROUP BY il.ProductID
            ORDER BY TotalSold DESC
        ');
        $stmt->execute([$dateFrom, $dateTo]);
        
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
