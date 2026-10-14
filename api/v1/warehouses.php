<?php
// api/v1/warehouses.php — Warehouses CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("
        SELECT w.*, COUNT(l.location_id) AS location_count
        FROM warehouses w
        LEFT JOIN locations l ON l.warehouse_id = w.warehouse_id
        GROUP BY w.warehouse_id ORDER BY w.name ASC
    ")->fetchAll();
    json_response($rows);

} elseif ($method === 'POST') {
    require_manager(); validate_csrf_token();
    $input = get_json_input();
    $whId  = (int)($input['warehouse_id'] ?? 0);
    $code  = trim($input['code'] ?? '');
    $name  = trim($input['name'] ?? '');
    $addr  = trim($input['address'] ?? '');
    if (!$code || !$name) json_response(['error'=>'Code and Name required.'],400);

    if ($whId) {
        $old = $pdo->prepare("SELECT name FROM warehouses WHERE warehouse_id=?");
        $old->execute([$whId]);
        $oldData = $old->fetch();
        $pdo->prepare("UPDATE warehouses SET code=?,name=?,address=? WHERE warehouse_id=?")->execute([$code,$name,$addr,$whId]);
        write_audit($pdo,'UPDATE','warehouses',$whId,['old_name'=>$oldData['name'],'new_name'=>$name]);
        json_response(['message'=>'Warehouse updated.']);
    } else {
        $pdo->prepare("INSERT INTO warehouses(code,name,address) VALUES(?,?,?)")->execute([$code,$name,$addr]);
        $newId = (int)$pdo->lastInsertId();
        write_audit($pdo,'CREATE','warehouses',$newId,['code'=>$code,'name'=>$name]);
        json_response(['message'=>'Warehouse created.','warehouse_id'=>$newId],201);
    }

} elseif ($method === 'DELETE') {
    require_admin(); validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $pdo->prepare("DELETE FROM warehouses WHERE warehouse_id=?")->execute([$id]);
    write_audit($pdo,'DELETE','warehouses',$id);
    json_response(['message'=>'Warehouse deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'],405);
}
