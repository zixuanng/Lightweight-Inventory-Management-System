<?php
// api/v1/analytics/abc.php — ABC classification, 30d trend, category distribution, audit log

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_login();

// Serve audit log if requested
if (isset($_GET['type']) && $_GET['type'] === 'audit') {
    $rows = $pdo->query("
        SELECT a.audit_id, a.timestamp, u.username, a.action, a.table_name, a.record_id, a.delta_json, a.ip_address
        FROM audit_logs a JOIN users u ON a.user_id = u.user_id
        ORDER BY a.audit_id DESC LIMIT 500
    ")->fetchAll();
    json_response(['audit_logs' => $rows]);
}

// ── 1. ABC Classification ────────────────────────────────────────────────────
$products = $pdo->query("
    SELECT p.product_id, p.sku, p.name, p.unit_price, p.abc_class,
           COALESCE(SUM(i.quantity), 0) AS total_qty,
           COALESCE(SUM(i.quantity), 0) * p.unit_price AS total_value
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.product_id
    GROUP BY p.product_id, p.sku, p.name, p.unit_price, p.abc_class
    ORDER BY total_value DESC
")->fetchAll();

$grandTotal = array_sum(array_column($products, 'total_value'));
$cumulative = 0;
$result     = [];
$updateAbc  = $pdo->prepare("UPDATE products SET abc_class = ? WHERE product_id = ?");

foreach ($products as $p) {
    $val            = (float)$p['total_value'];
    $cumulative    += $val;
    $cumulPct       = $grandTotal > 0 ? ($cumulative / $grandTotal) * 100 : 0;
    $prevPct        = $grandTotal > 0 ? (($cumulative - $val) / $grandTotal) * 100 : 0;
    $abcClass       = $prevPct < 80 ? 'A' : ($prevPct < 95 ? 'B' : 'C');
    $updateAbc->execute([$abcClass, $p['product_id']]);
    $result[] = [
        'product_id'     => (int)$p['product_id'],
        'sku'            => $p['sku'],
        'name'           => $p['name'],
        'abc_class'      => $abcClass,
        'total_qty'      => (int)$p['total_qty'],
        'unit_price'     => (float)$p['unit_price'],
        'total_value'    => round($val, 2),
        'cumulative_pct' => round($cumulPct, 2),
    ];
}

// ── 2. 30-day stock movement trend ──────────────────────────────────────────
$trendRows = $pdo->query("
    SELECT DATE(sl.timestamp) AS date,
           COALESCE(SUM(CASE WHEN sl.quantity_change > 0 THEN sl.quantity_change ELSE 0 END), 0) AS total_in,
           COALESCE(SUM(CASE WHEN sl.quantity_change < 0 THEN ABS(sl.quantity_change) ELSE 0 END), 0) AS total_out
    FROM stock_logs sl
    WHERE sl.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(sl.timestamp)
    ORDER BY date ASC
")->fetchAll();

$trend = array_map(fn($r) => [
    'date'      => $r['date'],
    'total_in'  => (int)$r['total_in'],
    'total_out' => (int)$r['total_out'],
], $trendRows);

// ── 3. Category Value Distribution ──────────────────────────────────────────
$catRows = $pdo->query("
    SELECT COALESCE(c.category_name, 'Uncategorized') AS category_name,
           COALESCE(SUM(i.quantity * p.unit_price), 0) AS total_value
    FROM products p
    LEFT JOIN categories c ON c.category_id = p.category_id
    LEFT JOIN inventory i  ON i.product_id  = p.product_id
    GROUP BY c.category_id, c.category_name
    ORDER BY total_value DESC
")->fetchAll();

$catDist = array_map(fn($r) => [
    'category_name' => $r['category_name'],
    'total_value'   => round((float)$r['total_value'], 2),
], $catRows);

// ── 4. Inventory Turnover (30-day STOCK_OUT / avg inventory) ─────────────
$turnover = $pdo->query("
    SELECT p.product_id, p.sku, p.name,
           COALESCE(ABS(SUM(CASE WHEN sl.transaction_type='STOCK_OUT' AND sl.timestamp >= DATE_SUB(NOW(),INTERVAL 30 DAY) THEN sl.quantity_change ELSE 0 END)),0) AS out_30d,
           COALESCE(AVG(i.quantity),0) AS avg_inventory
    FROM products p
    LEFT JOIN stock_logs sl ON sl.product_id = p.product_id
    LEFT JOIN inventory i   ON i.product_id  = p.product_id
    GROUP BY p.product_id, p.sku, p.name
")->fetchAll();

$turnoverData = array_map(fn($r) => [
    'product_id'    => (int)$r['product_id'],
    'sku'           => $r['sku'],
    'name'          => $r['name'],
    'out_30d'       => (int)$r['out_30d'],
    'avg_inventory' => round((float)$r['avg_inventory'], 2),
    'turnover_rate' => $r['avg_inventory'] > 0 ? round($r['out_30d'] / $r['avg_inventory'], 2) : null,
], $turnover);

json_response([
    'products'               => $result,
    'grand_total_value'      => round($grandTotal, 2),
    'trend_30d'              => $trend,
    'category_distribution'  => $catDist,
    'inventory_turnover'     => $turnoverData,
]);
