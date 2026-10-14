<?php
// api/v1/transfers/create.php — Create Transfer

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_manager();
validate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed.'],405);

$input = get_json_input();
$fromLocId = (int)($input['from_location_id'] ?? 0);
$toLocId   = (int)($input['to_location_id'] ?? 0);
$notes     = trim($input['notes'] ?? '');
$items     = $input['items'] ?? [];
$user      = get_logged_user();

if (!$fromLocId || !$toLocId) json_response(['error'=>'From and To locations required.'],400);
if ($fromLocId === $toLocId) json_response(['error'=>'Locations must be different.'],400);
if (empty($items) || !is_array($items)) json_response(['error'=>'Items required.'],400);

try {
    $pdo->beginTransaction();

    $code = 'TRF-' . date('Ymd') . '-' . str_pad(mt_rand(1,9999), 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO stock_transfers(transfer_code, from_location_id, to_location_id, notes, initiated_by) VALUES(?,?,?,?,?)")
        ->execute([$code, $fromLocId, $toLocId, $notes, $user['user_id']]);
    $trId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO stock_transfer_items(transfer_id, product_id, batch_id, quantity) VALUES(?,?,?,?)");
    foreach ($items as $item) {
        $stmtItem->execute([$trId, (int)$item['product_id'], (int)$item['batch_id'], (int)$item['quantity']]);
    }

    write_audit($pdo, 'CREATE', 'stock_transfers', $trId, ['transfer_code'=>$code, 'items'=>count($items)]);
    $pdo->commit();
    json_response(['message'=>'Transfer created.', 'transfer_id'=>$trId, 'transfer_code'=>$code], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error'=>'Failed to create transfer: '.$e->getMessage()],500);
}
