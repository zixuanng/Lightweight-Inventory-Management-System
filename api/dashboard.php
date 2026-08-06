<?php
// api/dashboard.php - Dashboard aggregate stock metrics

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

// 1. Total SKUs
$stmtTotal = $pdo->query("SELECT COUNT(*) AS total_skus FROM products");
$totalSkus = (int)$stmtTotal->fetchColumn();

// 2. Low-Stock Count (quantity <= reorder_level)
$stmtLowStock = $pdo->query("SELECT COUNT(*) AS low_stock_count FROM products WHERE quantity <= reorder_level");
$lowStockCount = (int)$stmtLowStock->fetchColumn();

// 3. Total Inventory Value (SUM(quantity * unit_price))
$stmtValue = $pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0.00) AS total_inventory_value FROM products");
$totalInventoryValue = (float)$stmtValue->fetchColumn();

json_response([
    'total_skus' => $totalSkus,
    'low_stock_count' => $lowStockCount,
    'total_inventory_value' => number_format($totalInventoryValue, 2, '.', '')
]);
