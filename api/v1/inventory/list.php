<?php
// api/v1/inventory/list.php — List inventory with FEFO sort, warehouse/ABC/batch filters

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_login();

$warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$abcClass    = $_GET['abc_class'] ?? '';
$search      = trim($_GET['search'] ?? '');
$lowStock    = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

// Build base query — inventory line per product+batch+location, sorted FEFO
$where  = ['1=1'];
$params = [];

if ($warehouseId > 0) {
    $where[]  = 'l.warehouse_id = ?';
    $params[] = $warehouseId;
}
if (in_array($abcClass, ['A','B','C'])) {
    $where[]  = 'p.abc_class = ?';
    $params[] = $abcClass;
}
if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

$rows = $pdo->prepare("
    SELECT
        i.inventory_id,
        i.quantity,
        p.product_id, p.sku, p.barcode, p.name AS product_name,
        p.reorder_level, p.unit_cost, p.unit_price, p.abc_class,
        b.batch_id, b.batch_number, b.manufacturing_date, b.expiry_date,
        l.location_id, l.zone, l.shelf_bin,
        w.warehouse_id, w.name AS warehouse_name, w.code AS warehouse_code,
        c.category_name,
        s.supplier_name
    FROM inventory i
    JOIN products p       ON i.product_id  = p.product_id
    JOIN product_batches b ON i.batch_id    = b.batch_id
    JOIN locations l      ON i.location_id = l.location_id
    JOIN warehouses w     ON l.warehouse_id = w.warehouse_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s  ON p.default_supplier_id = s.supplier_id
    WHERE $whereStr
    ORDER BY p.name ASC, b.expiry_date ASC
");
$rows->execute($params);
$inventory = $rows->fetchAll();

// Calculate total qty per product for low-stock detection
$productTotals = [];
foreach ($inventory as $row) {
    $pid = $row['product_id'];
    $productTotals[$pid] = ($productTotals[$pid] ?? 0) + (int)$row['quantity'];
}

// Annotate with is_low_stock flag
foreach ($inventory as &$row) {
    $total = $productTotals[$row['product_id']] ?? 0;
    $row['total_qty']    = $total;
    $row['is_low_stock'] = $total <= (int)$row['reorder_level'];

    // Days to expiry
    $expiry = new DateTime($row['expiry_date']);
    $now    = new DateTime();
    $row['days_to_expiry'] = (int)$now->diff($expiry)->days * ($now < $expiry ? 1 : -1);
}
unset($row);

if ($lowStock) {
    $inventory = array_values(array_filter($inventory, fn($r) => $r['is_low_stock']));
}

json_response($inventory);
