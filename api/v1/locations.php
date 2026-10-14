<?php
// api/v1/locations.php — Locations CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $where = ''; $params = [];
    if (!empty($_GET['warehouse_id'])) {
        $where = 'WHERE l.warehouse_id = ?';
        $params[] = (int)$_GET['warehouse_id'];
    }
    $stmt = $pdo->prepare("
        SELECT l.location_id, l.warehouse_id, l.zone, l.shelf_bin, w.name AS warehouse_name
        FROM locations l
        JOIN warehouses w ON l.warehouse_id = w.warehouse_id
        $where
        ORDER BY w.name ASC, l.zone ASC, l.shelf_bin ASC
    ");
    $stmt->execute($params);
    json_response($stmt->fetchAll());

} elseif ($method === 'POST') {
    require_manager(); validate_csrf_token();
    $input = get_json_input();
    $id    = (int)($input['location_id'] ?? 0);
    $whId  = (int)($input['warehouse_id'] ?? 0);
    $zone  = trim($input['zone'] ?? '');
    $bin   = trim($input['shelf_bin'] ?? '');

    if (!$whId || !$zone || !$bin) json_response(['error'=>'Warehouse, Zone, and Shelf/Bin are required.'],400);

    if ($id) {
        $old = $pdo->prepare("SELECT zone, shelf_bin FROM locations WHERE location_id=?");
        $old->execute([$id]);
        $oldData = $old->fetch();
        $pdo->prepare("UPDATE locations SET warehouse_id=?, zone=?, shelf_bin=? WHERE location_id=?")->execute([$whId, $zone, $bin, $id]);
        write_audit($pdo, 'UPDATE', 'locations', $id, ['old'=>$oldData, 'new'=>['zone'=>$zone,'shelf_bin'=>$bin]]);
        json_response(['message'=>'Location updated.']);
    } else {
        $pdo->prepare("INSERT INTO locations(warehouse_id, zone, shelf_bin) VALUES(?,?,?)")->execute([$whId, $zone, $bin]);
        $newId = (int)$pdo->lastInsertId();
        write_audit($pdo, 'CREATE', 'locations', $newId, ['warehouse_id'=>$whId, 'zone'=>$zone, 'shelf_bin'=>$bin]);
        json_response(['message'=>'Location created.', 'location_id'=>$newId], 201);
    }
} elseif ($method === 'DELETE') {
    require_admin(); validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $pdo->prepare("DELETE FROM locations WHERE location_id=?")->execute([$id]);
    write_audit($pdo, 'DELETE', 'locations', $id);
    json_response(['message'=>'Location deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'], 405);
}
