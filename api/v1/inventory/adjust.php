<?php
// api/v1/inventory/adjust.php — STOCK_IN / STOCK_OUT with FEFO, SQL transaction & audit

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_clerk();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed.'], 405);
}
validate_csrf_token();

$input  = get_json_input();
$type   = strtoupper(trim($input['transaction_type'] ?? ''));
$qty    = (int)($input['quantity'] ?? 0);
$notes  = trim($input['notes'] ?? '');
$refId  = trim($input['reference_id'] ?? '');
$user   = get_logged_user();

if (!in_array($type, ['STOCK_IN', 'STOCK_OUT', 'ADJUSTMENT'])) {
    json_response(['error' => 'Invalid transaction type.'], 400);
}
if ($qty <= 0) {
    json_response(['error' => 'Quantity must be greater than zero.'], 400);
}

try {
    $pdo->beginTransaction();

    if ($type === 'STOCK_IN') {
        // STOCK_IN requires product_id, batch_id, location_id
        $productId  = (int)($input['product_id']  ?? 0);
        $batchId    = (int)($input['batch_id']     ?? 0);
        $locationId = (int)($input['location_id']  ?? 0);

        if (!$productId || !$batchId || !$locationId) {
            $pdo->rollBack();
            json_response(['error' => 'Product, batch, and location are required for STOCK_IN.'], 400);
        }

        // Upsert inventory row
        $stmtCheck = $pdo->prepare("
            SELECT inventory_id, quantity FROM inventory
            WHERE product_id = ? AND batch_id = ? AND location_id = ?
            FOR UPDATE
        ");
        $stmtCheck->execute([$productId, $batchId, $locationId]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $qty;
            $pdo->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?")
                ->execute([$newQty, $existing['inventory_id']]);
        } else {
            $pdo->prepare("
                INSERT INTO inventory (product_id, batch_id, location_id, quantity)
                VALUES (?, ?, ?, ?)
            ")->execute([$productId, $batchId, $locationId, $qty]);
            $newQty = $qty;
        }

        // Log
        $pdo->prepare("
            INSERT INTO stock_logs (product_id, batch_id, location_id, user_id, transaction_type, quantity_change, reference_id, notes)
            VALUES (?, ?, ?, ?, 'STOCK_IN', ?, ?, ?)
        ")->execute([$productId, $batchId, $locationId, $user['user_id'], $qty, $refId, $notes]);

        write_audit($pdo, 'STOCK_IN', 'inventory', $productId, [
            'batch_id' => $batchId, 'location_id' => $locationId,
            'qty_added' => $qty, 'new_qty' => $newQty,
        ]);

        $pdo->commit();
        json_response(['message' => "STOCK_IN of $qty units recorded.", 'new_quantity' => $newQty]);

    } else {
        // STOCK_OUT / ADJUSTMENT — use FEFO order (earliest expiry first)
        $productId  = (int)($input['product_id']  ?? 0);
        $locationId = (int)($input['location_id']  ?? 0);

        if (!$productId) {
            $pdo->rollBack();
            json_response(['error' => 'Product is required for STOCK_OUT.'], 400);
        }

        // Get total available quantity for this product (optionally at one location)
        $locFilter  = $locationId ? 'AND i.location_id = ?' : '';
        $locParams  = $locationId ? [$productId, $locationId] : [$productId];
        $available  = (int)$pdo->prepare("
            SELECT COALESCE(SUM(quantity),0) FROM inventory i
            WHERE product_id = ? $locFilter
        ")->execute($locParams) ? $pdo->query("
            SELECT COALESCE(SUM(quantity),0) FROM inventory i
            WHERE product_id = $productId " . ($locationId ? "AND location_id = $locationId" : "")
        )->fetchColumn() : 0;

        // Simpler direct query for available
        $availStmt = $pdo->prepare("
            SELECT COALESCE(SUM(i.quantity),0) AS total
            FROM inventory i
            WHERE i.product_id = ? " . ($locationId ? "AND i.location_id = ?" : ""));
        $availStmt->execute($locationId ? [$productId, $locationId] : [$productId]);
        $available = (int)$availStmt->fetchColumn();

        if ($available < $qty) {
            $pdo->rollBack();
            json_response([
                'error' => "Insufficient stock. Available: $available, Requested: $qty."
            ], 400);
        }

        // Deduct FEFO — earliest expiry batch first
        $fefoStmt = $pdo->prepare("
            SELECT i.inventory_id, i.quantity, i.batch_id, i.location_id
            FROM inventory i
            JOIN product_batches b ON i.batch_id = b.batch_id
            WHERE i.product_id = ? " . ($locationId ? "AND i.location_id = ?" : "") . "
            AND i.quantity > 0
            ORDER BY b.expiry_date ASC, i.inventory_id ASC
            FOR UPDATE
        ");
        $fefoStmt->execute($locationId ? [$productId, $locationId] : [$productId]);
        $batches = $fefoStmt->fetchAll();

        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $deduct  = min($remaining, (int)$batch['quantity']);
            $newBQty = $batch['quantity'] - $deduct;

            $pdo->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?")
                ->execute([$newBQty, $batch['inventory_id']]);

            $pdo->prepare("
                INSERT INTO stock_logs (product_id, batch_id, location_id, user_id, transaction_type, quantity_change, reference_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $productId, $batch['batch_id'], $batch['location_id'],
                $user['user_id'], $type, -$deduct, $refId, $notes
            ]);

            write_audit($pdo, $type, 'inventory', $productId, [
                'batch_id' => $batch['batch_id'], 'location_id' => $batch['location_id'],
                'old_qty' => $batch['quantity'], 'new_qty' => $newBQty, 'deducted' => $deduct,
            ]);

            $remaining -= $deduct;
        }

        $pdo->commit();
        json_response(['message' => "$type of $qty units completed (FEFO applied).", 'remaining_stock' => $available - $qty]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
}
