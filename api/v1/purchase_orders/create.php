<?php
// api/v1/purchase_orders/create.php — Create PO

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_manager();
validate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed.'],405);

$input = get_json_input();
$supplierId = (int)($input['supplier_id'] ?? 0);
$notes      = trim($input['notes'] ?? '');
$items      = $input['items'] ?? [];
$user       = get_logged_user();

if (!$supplierId) json_response(['error'=>'Supplier is required.'],400);
if (empty($items) || !is_array($items)) json_response(['error'=>'At least one item is required.'],400);

try {
    $pdo->beginTransaction();

    $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(mt_rand(1,9999), 4, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO purchase_orders(po_number, supplier_id, status, notes, created_by) VALUES(?,?,?,?,?)")
        ->execute([$poNumber, $supplierId, 'Draft', $notes, $user['user_id']]);
    $poId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO po_items(po_id, product_id, ordered_quantity, unit_cost) VALUES(?,?,?,?)");
    foreach ($items as $item) {
        $stmtItem->execute([$poId, (int)$item['product_id'], (int)$item['ordered_quantity'], (float)$item['unit_cost']]);
    }

    write_audit($pdo, 'CREATE', 'purchase_orders', $poId, ['po_number'=>$poNumber, 'supplier_id'=>$supplierId, 'items'=>count($items)]);
    $pdo->commit();
    json_response(['message'=>'PO created.', 'po_id'=>$poId, 'po_number'=>$poNumber], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error'=>'Failed to create PO: ' . $e->getMessage()], 500);
}
