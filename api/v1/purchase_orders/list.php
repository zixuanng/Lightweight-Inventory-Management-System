<?php
// api/v1/purchase_orders/list.php — List POs

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_login();

$status = $_GET['status'] ?? '';
$where = ''; $params = [];
if (in_array($status, ['Draft','Sent','Partial','Completed','Cancelled'])) {
    $where = 'WHERE po.status = ?'; $params[] = $status;
}

if (!empty($_GET['po_id']) && !empty($_GET['items'])) {
    // Return line items for a specific PO
    $poId = (int)$_GET['po_id'];
    $stmt = $pdo->prepare("
        SELECT pi.po_item_id, pi.product_id, pi.ordered_quantity, pi.received_quantity, pi.unit_cost,
               p.name AS product_name, p.sku
        FROM po_items pi
        JOIN products p ON pi.product_id = p.product_id
        WHERE pi.po_id = ?
    ");
    $stmt->execute([$poId]);
    json_response($stmt->fetchAll());
}

$stmt = $pdo->prepare("
    SELECT po.po_id, po.po_number, po.status, po.created_at,
           s.supplier_name, u.username AS created_by,
           (SELECT COUNT(*) FROM po_items WHERE po_id = po.po_id) AS item_count
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN users u ON po.created_by = u.user_id
    $where
    ORDER BY po.po_id DESC
");
$stmt->execute($params);
json_response($stmt->fetchAll());
