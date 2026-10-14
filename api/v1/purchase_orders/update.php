<?php
// api/v1/purchase_orders/update.php — Update PO Status

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';
require_manager();
validate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method not allowed.'],405);

$input  = get_json_input();
$poId   = (int)($input['po_id'] ?? 0);
$status = trim($input['status'] ?? '');

if (!$poId) json_response(['error'=>'PO ID required.'],400);
if (!in_array($status, ['Draft','Sent','Partial','Completed','Cancelled'])) json_response(['error'=>'Invalid status.'],400);

$stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE po_id=?");
$stmt->execute([$poId]);
$oldStatus = $stmt->fetchColumn();
if (!$oldStatus) json_response(['error'=>'PO not found.'],404);

$pdo->prepare("UPDATE purchase_orders SET status=? WHERE po_id=?")->execute([$status, $poId]);
write_audit($pdo, 'UPDATE', 'purchase_orders', $poId, ['old_status'=>$oldStatus, 'new_status'=>$status]);
json_response(['message'=>'PO status updated.']);
