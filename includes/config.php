<?php
// includes/config.php
session_start();

define('DB_PATH', __DIR__ . '/../data/AbayaWholesale.db');
define('SITE_NAME', 'Abaya Wholesale Manager');
define('ADMIN_PASSWORD_HASH', '$2y$12$jEFjk4fRbkhi/a1tWjXiJ.eT9ukC6OhbTMnXnXPoJdawaIHtJ50bS'); // Hash for 'admin123'

require_once __DIR__ . '/ledger.php';

// Database connection
function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }
}

// Check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.php');
        exit;
    }
}

// Format currency
function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date('d M Y', strtotime($date));
}
?>
