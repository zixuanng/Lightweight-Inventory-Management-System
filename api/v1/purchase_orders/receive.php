<?php
// api/v1/purchase_orders/receive.php — Receive goods against PO line

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_clerk();
validate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed.'],405);

$input = get_json_input();
$poItemId = (int)($input['po_item_id'] ?? 0);
$recvQty  = (int)($input['received_qty'] ?? 0);
$locId    = (int)($input['location_id'] ?? 0);
$batchNum = trim($input['batch_number'] ?? '');
$mfgDate  = trim($input['manufacturing_date'] ?? '') ?: null;
$expDate  = trim($input['expiry_date'] ?? '');
$user     = get_logged_user();

if (!$poItemId || $recvQty <= 0 || !$locId || !$batchNum || !$expDate) {
    json_response(['error'=>'Item, Qty, Location, Batch, and Expiry are required.'],400);
}

try {
    $pdo->beginTransaction();

    // Lock PO item
    $stmtItem = $pdo->prepare("SELECT po_id, product_id, ordered_quantity, received_quantity FROM po_items WHERE po_item_id=? FOR UPDATE");
    $stmtItem->execute([$poItemId]);
    $item = $stmtItem->fetch();
    if (!$item) { $pdo->rollBack(); json_response(['error'=>'PO item not found.'],404); }

    if ($item['received_quantity'] + $recvQty > $item['ordered_quantity']) {
        $pdo->rollBack(); json_response(['error'=>'Cannot receive more than ordered quantity.'],400);
    }

    // Upsert batch
    $stmtBatch = $pdo->prepare("SELECT batch_id FROM product_batches WHERE product_id=? AND batch_number=?");
    $stmtBatch->execute([$item['product_id'], $batchNum]);
    $batchId = $stmtBatch->fetchColumn();
    if (!$batchId) {
        $pdo->prepare("INSERT INTO product_batches(product_id, batch_number, manufacturing_date, expiry_date) VALUES(?,?,?,?)")
            ->execute([$item['product_id'], $batchNum, $mfgDate, $expDate]);
        $batchId = (int)$pdo->lastInsertId();
    }

    // Upsert inventory
    $stmtInv = $pdo->prepare("SELECT inventory_id, quantity FROM inventory WHERE product_id=? AND batch_id=? AND location_id=? FOR UPDATE");
    $stmtInv->execute([$item['product_id'], $batchId, $locId]);
    $inv = $stmtInv->fetch();
    if ($inv) {
        $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE inventory_id=?")->execute([$recvQty, $inv['inventory_id']]);
    } else {
        $pdo->prepare("INSERT INTO inventory(product_id, batch_id, location_id, quantity) VALUES(?,?,?,?)")
            ->execute([$item['product_id'], $batchId, $locId, $recvQty]);
    }

    // Update PO item
    $pdo->prepare("UPDATE po_items SET received_quantity = received_quantity + ? WHERE po_item_id=?")->execute([$recvQty, $poItemId]);

    // Stock log
    $pdo->prepare("INSERT INTO stock_logs(product_id, batch_id, location_id, user_id, transaction_type, quantity_change, reference_id, notes) VALUES(?,?,?,?,?,?,?,?)")
        ->execute([$item['product_id'], $batchId, $locId, $user['user_id'], 'STOCK_IN', $recvQty, "PO_ITEM-$poItemId", 'Received from PO']);

    // Check PO completion status
    $stmtPoItems = $pdo->prepare("SELECT SUM(ordered_quantity) AS to_ord, SUM(received_quantity) AS to_recv FROM po_items WHERE po_id=?");
    $stmtPoItems->execute([$item['po_id']]);
    $poStats = $stmtPoItems->fetch();
    $newStatus = ($poStats['to_ord'] <= $poStats['to_recv']) ? 'Completed' : 'Partial';
    $pdo->prepare("UPDATE purchase_orders SET status=? WHERE po_id=?")->execute([$newStatus, $item['po_id']]);

    write_audit($pdo, 'RECEIVE', 'po_items', $poItemId, ['received'=>$recvQty, 'po_status'=>$newStatus]);

    $pdo->commit();
    json_response(['message'=>'Goods received.', 'new_po_status'=>$newStatus]);

} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error'=>'Failed to receive: '.$e->getMessage()],500);
}
