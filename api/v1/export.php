<?php
// api/v1/export.php — CSV export for inventory, audit logs, PO history

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$type = $_GET['type'] ?? 'inventory';
$ts   = date('Y-m-d_H-i-s');

switch ($type) {

    case 'inventory':
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"inventory_$ts.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Product ID', 'SKU', 'Barcode', 'Product Name', 'ABC', 'Category',
                       'Batch', 'Expiry Date', 'Days to Expiry', 'Warehouse', 'Zone',
                       'Shelf/Bin', 'Quantity', 'Unit Cost', 'Unit Price', 'Line Value', 'Status']);
        $rows = $pdo->query("
            SELECT p.product_id, p.sku, p.barcode, p.name, p.abc_class,
                   c.category_name, b.batch_number, b.expiry_date,
                   w.name AS wh, l.zone, l.shelf_bin,
                   i.quantity, p.unit_cost, p.unit_price, p.reorder_level
            FROM inventory i
            JOIN products p       ON i.product_id = p.product_id
            JOIN product_batches b ON i.batch_id   = b.batch_id
            JOIN locations l      ON i.location_id = l.location_id
            JOIN warehouses w     ON l.warehouse_id = w.warehouse_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            ORDER BY p.name, b.expiry_date ASC
        ")->fetchAll();
        $today = new DateTime();
        foreach ($rows as $r) {
            $exp    = new DateTime($r['expiry_date']);
            $days   = (int)$today->diff($exp)->days * ($today < $exp ? 1 : -1);
            $value  = $r['quantity'] * $r['unit_price'];
            // total qty for this product
            $tqStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM inventory WHERE product_id = ?");
            $tqStmt->execute([$r['product_id']]);
            $totalQ = (int)$tqStmt->fetchColumn();
            $status = $totalQ <= $r['reorder_level'] ? 'LOW STOCK' : 'OK';
            fputcsv($out, [
                $r['product_id'], $r['sku'], $r['barcode'] ?? '', $r['name'],
                $r['abc_class'], $r['category_name'] ?? '',
                $r['batch_number'], $r['expiry_date'], $days,
                $r['wh'], $r['zone'], $r['shelf_bin'],
                $r['quantity'], number_format($r['unit_cost'],2), number_format($r['unit_price'],2),
                number_format($value, 2), $status
            ]);
        }
        fclose($out);
        break;

    case 'audit':
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"audit_logs_$ts.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Audit ID', 'Timestamp', 'User', 'Action', 'Table', 'Record ID', 'Changes (JSON)', 'IP Address']);
        $rows = $pdo->query("
            SELECT a.audit_id, a.timestamp, u.username, a.action, a.table_name, a.record_id, a.delta_json, a.ip_address
            FROM audit_logs a JOIN users u ON a.user_id = u.user_id
            ORDER BY a.audit_id DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['audit_id'], $r['timestamp'], $r['username'],
                           $r['action'], $r['table_name'], $r['record_id'],
                           $r['delta_json'] ?? '', $r['ip_address']]);
        }
        fclose($out);
        break;

    case 'purchase_orders':
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"purchase_orders_$ts.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['PO Number', 'Status', 'Supplier', 'Created By', 'Created At',
                       'Product SKU', 'Product Name', 'Ordered Qty', 'Received Qty', 'Unit Cost']);
        $rows = $pdo->query("
            SELECT po.po_number, po.status, s.supplier_name, u.username AS created_by, po.created_at,
                   p.sku, p.name AS product_name, pi.ordered_quantity, pi.received_quantity, pi.unit_cost
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.supplier_id
            JOIN users u ON po.created_by = u.user_id
            JOIN po_items pi ON po.po_id = pi.po_id
            JOIN products p ON pi.product_id = p.product_id
            ORDER BY po.po_id DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['po_number'], $r['status'], $r['supplier_name'], $r['created_by'],
                           $r['created_at'], $r['sku'], $r['product_name'],
                           $r['ordered_quantity'], $r['received_quantity'],
                           number_format($r['unit_cost'],2)]);
        }
        fclose($out);
        break;

    case 'stock_logs':
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"stock_logs_$ts.csv\"");
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Log ID', 'Timestamp', 'Type', 'Product SKU', 'Product Name',
                       'Batch', 'Warehouse', 'Zone', 'Shelf/Bin', 'Qty Change', 'Reference', 'Notes', 'User']);
        $rows = $pdo->query("
            SELECT sl.log_id, sl.timestamp, sl.transaction_type, p.sku, p.name,
                   b.batch_number, w.name AS wh, l.zone, l.shelf_bin,
                   sl.quantity_change, sl.reference_id, sl.notes, u.username
            FROM stock_logs sl
            JOIN products p       ON sl.product_id  = p.product_id
            JOIN product_batches b ON sl.batch_id    = b.batch_id
            JOIN locations l      ON sl.location_id  = l.location_id
            JOIN warehouses w     ON l.warehouse_id  = w.warehouse_id
            JOIN users u          ON sl.user_id      = u.user_id
            ORDER BY sl.log_id DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [$r['log_id'], $r['timestamp'], $r['transaction_type'], $r['sku'],
                           $r['name'], $r['batch_number'], $r['wh'], $r['zone'], $r['shelf_bin'],
                           $r['quantity_change'], $r['reference_id'] ?? '', $r['notes'] ?? '', $r['username']]);
        }
        fclose($out);
        break;

    default:
        json_response(['error' => 'Unknown export type.'], 400);
}
exit;
