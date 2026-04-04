<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    $db = getDB();  // Changed from getDbConnection() to getDB()
    
    if ($action === 'add') {
        $stmt = $db->prepare('INSERT INTO Products (ProductID, ProductName, UnitPrice, StockQuantity) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $_POST['product_id'],
            $_POST['product_name'],
            $_POST['unit_price'],
            $_POST['stock_qty']
        ]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'add_auto') {
        // Calculate max numeric ID above 1000
        $stmtId = $db->query("SELECT MAX(CAST(ProductID AS INTEGER)) FROM Products WHERE CAST(ProductID AS INTEGER) > 0");
        $maxId = $stmtId->fetchColumn();
        $newId = ($maxId && $maxId >= 1001) ? $maxId + 1 : 1001;
        $newIdStr = (string)$newId;

        $stmt = $db->prepare('INSERT INTO Products (ProductID, ProductName, UnitPrice, StockQuantity) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            $newIdStr,
            $_POST['product_name'],
            $_POST['unit_price'],
            100 // Hardcoded default stock for auto-adds
        ]);
        echo json_encode(['success' => true, 'product_id' => $newIdStr]);
        
    } elseif ($action === 'update') {
        $stmt = $db->prepare('UPDATE Products SET ProductName = ?, UnitPrice = ?, StockQuantity = ? WHERE ProductID = ?');
        $stmt->execute([
            $_POST['product_name'],
            $_POST['unit_price'],
            $_POST['stock_qty'],
            $_POST['product_id']
        ]);
        echo json_encode(['success' => true]);
        
    } elseif ($action === 'delete') {
        $stmt = $db->prepare('DELETE FROM Products WHERE ProductID = ?');
        $stmt->execute([$_POST['product_id']]);
        echo json_encode(['success' => true]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
