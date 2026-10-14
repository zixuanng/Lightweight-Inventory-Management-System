<?php
// api/v1/suppliers.php — Suppliers CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    json_response($pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll());
} elseif ($method === 'POST') {
    require_manager(); validate_csrf_token();
    $input = get_json_input();
    $id    = (int)($input['supplier_id'] ?? 0);
    $name  = trim($input['supplier_name'] ?? '');
    $email = trim($input['contact_email'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if (!$name) json_response(['error'=>'Supplier name required.'],400);

    if ($id) {
        $old = $pdo->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id=?");
        $old->execute([$id]);
        $oldData = $old->fetch();
        $pdo->prepare("UPDATE suppliers SET supplier_name=?, contact_email=?, phone=? WHERE supplier_id=?")->execute([$name, $email, $phone, $id]);
        write_audit($pdo, 'UPDATE', 'suppliers', $id, ['old'=>$oldData['supplier_name'], 'new'=>$name]);
        json_response(['message'=>'Supplier updated.']);
    } else {
        $pdo->prepare("INSERT INTO suppliers(supplier_name, contact_email, phone) VALUES(?,?,?)")->execute([$name, $email, $phone]);
        $newId = (int)$pdo->lastInsertId();
        write_audit($pdo, 'CREATE', 'suppliers', $newId, ['supplier_name'=>$name]);
        json_response(['message'=>'Supplier created.', 'supplier_id'=>$newId], 201);
    }
} elseif ($method === 'DELETE') {
    require_admin(); validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $pdo->prepare("DELETE FROM suppliers WHERE supplier_id=?")->execute([$id]);
    write_audit($pdo, 'DELETE', 'suppliers', $id);
    json_response(['message'=>'Supplier deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'], 405);
}
