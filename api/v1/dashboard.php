<?php
// api/v1/dashboard.php — Aggregated enterprise metrics

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$today = date('Y-m-d');

// 1. Total unique SKUs
$totalSkus = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();

// 2. Total inventory value (sum across all locations)
$totalValue = (float)$pdo->query("
    SELECT COALESCE(SUM(i.quantity * p.unit_price), 0)
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
")->fetchColumn();

// 3. Low stock products (total quantity across all locations < reorder_level)
$lowStockRows = $pdo->query("
    SELECT p.product_id
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    GROUP BY p.product_id, p.reorder_level
    HAVING COALESCE(SUM(i.quantity), 0) <= p.reorder_level
")->fetchAll();
$lowStockCount = count($lowStockRows);

// 4. Expiry alerts
$exp30 = (int)$pdo->query("
    SELECT COUNT(DISTINCT b.batch_id) FROM product_batches b
    JOIN inventory i ON b.batch_id = i.batch_id
    WHERE b.expiry_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 30 DAY)
    AND i.quantity > 0
")->fetchColumn();

$exp60 = (int)$pdo->query("
    SELECT COUNT(DISTINCT b.batch_id) FROM product_batches b
    JOIN inventory i ON b.batch_id = i.batch_id
    WHERE b.expiry_date BETWEEN DATE_ADD('$today', INTERVAL 31 DAY) AND DATE_ADD('$today', INTERVAL 60 DAY)
    AND i.quantity > 0
")->fetchColumn();

$exp90 = (int)$pdo->query("
    SELECT COUNT(DISTINCT b.batch_id) FROM product_batches b
    JOIN inventory i ON b.batch_id = i.batch_id
    WHERE b.expiry_date BETWEEN DATE_ADD('$today', INTERVAL 61 DAY) AND DATE_ADD('$today', INTERVAL 90 DAY)
    AND i.quantity > 0
")->fetchColumn();

// 5. Open POs
$openPos = (int)$pdo->query("
    SELECT COUNT(*) FROM purchase_orders WHERE status IN ('Draft','Sent','Partial')
")->fetchColumn();

// 6. ABC counts
$abcCounts = $pdo->query("
    SELECT abc_class, COUNT(*) as cnt FROM products GROUP BY abc_class
")->fetchAll();
$abc = ['A' => 0, 'B' => 0, 'C' => 0];
foreach ($abcCounts as $row) {
    $abc[$row['abc_class']] = (int)$row['cnt'];
}

// 7. Pending transfers
$pendingTransfers = (int)$pdo->query("
    SELECT COUNT(*) FROM stock_transfers WHERE status IN ('Pending','In-Transit')
")->fetchColumn();

json_response([
    'total_skus'        => $totalSkus,
    'total_value'       => number_format($totalValue, 2, '.', ''),
    'low_stock_count'   => $lowStockCount,
    'expiry_30'         => $exp30,
    'expiry_60'         => $exp60,
    'expiry_90'         => $exp90,
    'open_pos'          => $openPos,
    'abc'               => $abc,
    'pending_transfers' => $pendingTransfers,
]);
