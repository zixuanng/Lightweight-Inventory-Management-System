<?php
// api/v1/transfers/list.php — List Transfers

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_login();

$stmt = $pdo->query("
    SELECT t.transfer_id, t.transfer_code, t.status, t.created_at,
           l1.zone AS from_zone, l1.shelf_bin AS from_bin, w1.name AS from_warehouse,
           l2.zone AS to_zone, l2.shelf_bin AS to_bin, w2.name AS to_warehouse,
           u.username AS initiated_by,
           (SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = t.transfer_id) AS item_count
    FROM stock_transfers t
    JOIN locations l1 ON t.from_location_id = l1.location_id
    JOIN warehouses w1 ON l1.warehouse_id = w1.warehouse_id
    JOIN locations l2 ON t.to_location_id = l2.location_id
    JOIN warehouses w2 ON l2.warehouse_id = w2.warehouse_id
    JOIN users u ON t.initiated_by = u.user_id
    ORDER BY t.transfer_id DESC
");
json_response($stmt->fetchAll());
