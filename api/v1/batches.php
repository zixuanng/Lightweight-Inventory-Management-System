<?php
// api/v1/batches.php — Batches CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $where = ''; $params = [];
    if (!empty($_GET['product_id'])) {
        $where = 'WHERE b.product_id = ?';
        $params[] = (int)$_GET['product_id'];
    }
    $stmt = $pdo->prepare("
        SELECT b.batch_id, b.product_id, b.batch_number, b.manufacturing_date, b.expiry_date,
               p.name AS product_name, p.sku,
               DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry,
               COALESCE(SUM(i.quantity),0) AS total_quantity
        FROM product_batches b
        JOIN products p ON b.product_id = p.product_id
        LEFT JOIN inventory i ON i.batch_id = b.batch_id
        $where
        GROUP BY b.batch_id
        ORDER BY b.expiry_date ASC
    ");
    $stmt->execute($params);
    json_response($stmt->fetchAll());

} elseif ($method === 'POST') {
    require_clerk(); validate_csrf_token();
    $input = get_json_input();
    $productId = (int)($input['product_id'] ?? 0);
    $batchNum  = trim($input['batch_number'] ?? '');
    $mfgDate   = trim($input['manufacturing_date'] ?? '') ?: null;
    $expDate   = trim($input['expiry_date'] ?? '');

    if (!$productId || !$batchNum || !$expDate) json_response(['error'=>'Product, Batch Number, and Expiry Date are required.'],400);

    // Prevent duplicate batch number for same product
    $chk = $pdo->prepare("SELECT batch_id FROM product_batches WHERE product_id=? AND batch_number=?");
    $chk->execute([$productId, $batchNum]);
    if ($chk->fetch()) json_response(['error'=>'Batch number already exists for this product.'],400);

    $pdo->prepare("INSERT INTO product_batches(product_id, batch_number, manufacturing_date, expiry_date) VALUES(?,?,?,?)")
        ->execute([$productId, $batchNum, $mfgDate, $expDate]);
    $newId = (int)$pdo->lastInsertId();
    write_audit($pdo, 'CREATE', 'product_batches', $newId, ['batch_number'=>$batchNum, 'expiry_date'=>$expDate]);
    json_response(['message'=>'Batch created.', 'batch_id'=>$newId], 201);

} elseif ($method === 'DELETE') {
    require_manager(); validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $pdo->prepare("DELETE FROM product_batches WHERE batch_id=?")->execute([$id]);
    write_audit($pdo, 'DELETE', 'product_batches', $id);
    json_response(['message'=>'Batch deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'], 405);
}
