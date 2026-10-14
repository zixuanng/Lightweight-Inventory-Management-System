<?php
// api/v1/transfers/update.php — Progress Transfer Status

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_manager();
validate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed.'],405);

$input  = get_json_input();
$trId   = (int)($input['transfer_id'] ?? 0);
$status = trim($input['status'] ?? '');
$user   = get_logged_user();

if (!$trId) json_response(['error'=>'Transfer ID required.'],400);
if (!in_array($status, ['In-Transit','Received','Cancelled'])) json_response(['error'=>'Invalid status.'],400);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT status, from_location_id, to_location_id FROM stock_transfers WHERE transfer_id=? FOR UPDATE");
    $stmt->execute([$trId]);
    $tr = $stmt->fetch();
    if (!$tr) { $pdo->rollBack(); json_response(['error'=>'Transfer not found.'],404); }
    if ($tr['status'] === 'Received' || $tr['status'] === 'Cancelled') {
        $pdo->rollBack(); json_response(['error'=>'Transfer already complete or cancelled.'],400);
    }

    if ($status === 'Received') {
        // Process stock movement
        $stmtItems = $pdo->prepare("SELECT product_id, batch_id, quantity FROM stock_transfer_items WHERE transfer_id=?");
        $stmtItems->execute([$trId]);
        $items = $stmtItems->fetchAll();

        $stmtInvOut = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id=? AND batch_id=? AND location_id=?");
        $stmtInvChk = $pdo->prepare("SELECT inventory_id FROM inventory WHERE product_id=? AND batch_id=? AND location_id=?");
        $stmtInvIn  = $pdo->prepare("INSERT INTO inventory(product_id, batch_id, location_id, quantity) VALUES(?,?,?,?)");
        $stmtInvUpd = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE inventory_id=?");
        $stmtLog    = $pdo->prepare("INSERT INTO stock_logs(product_id, batch_id, location_id, user_id, transaction_type, quantity_change, reference_id) VALUES(?,?,?,?,?,?,?)");

        foreach ($items as $it) {
            // Deduct from origin
            $stmtInvOut->execute([$it['quantity'], $it['product_id'], $it['batch_id'], $tr['from_location_id']]);
            // Add to dest
            $stmtInvChk->execute([$it['product_id'], $it['batch_id'], $tr['to_location_id']]);
            $invDestId = $stmtInvChk->fetchColumn();
            if ($invDestId) {
                $stmtInvUpd->execute([$it['quantity'], $invDestId]);
            } else {
                $stmtInvIn->execute([$it['product_id'], $it['batch_id'], $tr['to_location_id'], $it['quantity']]);
            }
            // Logs
            $ref = "TRF-$trId";
            $stmtLog->execute([$it['product_id'], $it['batch_id'], $tr['from_location_id'], $user['user_id'], 'TRANSFER', -$it['quantity'], $ref]);
            $stmtLog->execute([$it['product_id'], $it['batch_id'], $tr['to_location_id'], $user['user_id'], 'TRANSFER', $it['quantity'], $ref]);
        }
    }

    $pdo->prepare("UPDATE stock_transfers SET status=? WHERE transfer_id=?")->execute([$status, $trId]);
    write_audit($pdo, 'UPDATE', 'stock_transfers', $trId, ['old_status'=>$tr['status'], 'new_status'=>$status]);

    $pdo->commit();
    json_response(['message'=>'Transfer status updated.']);

} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error'=>'Failed to update transfer: '.$e->getMessage()],500);
}
