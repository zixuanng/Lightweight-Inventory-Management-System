<?php
// api/v1/products.php — Products CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search   = trim($_GET['search']    ?? '');
    $abcClass = trim($_GET['abc_class'] ?? '');

    $where = ['1=1']; $params = [];
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
        $params  = array_merge($params, ["%$search%","%$search%","%$search%"]);
    }
    if (in_array(strtoupper($abcClass), ['A','B','C'])) {
        $where[] = 'p.abc_class = ?'; $params[] = strtoupper($abcClass);
    }

    $stmt = $pdo->prepare("
        SELECT p.product_id, p.sku, p.barcode, p.name,
               p.category_id, c.category_name,
               p.default_supplier_id, s.supplier_name,
               p.reorder_level, p.unit_cost, p.unit_price, p.abc_class,
               COALESCE(SUM(i.quantity),0) AS total_quantity
        FROM products p
        LEFT JOIN categories c ON c.category_id = p.category_id
        LEFT JOIN suppliers  s ON s.supplier_id = p.default_supplier_id
        LEFT JOIN inventory  i ON i.product_id  = p.product_id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY p.product_id ORDER BY p.name ASC
    ");
    $stmt->execute($params);
    json_response($stmt->fetchAll());

} elseif ($method === 'POST') {
    require_manager();
    validate_csrf_token();
    $input = get_json_input();

    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $sku       = trim($input['sku']    ?? '');
    $name      = trim($input['name']   ?? '');
    if (!$sku || !$name) json_response(['error' => 'SKU and Name are required.'], 400);

    $barcode   = trim($input['barcode'] ?? '') ?: null;
    $catId     = !empty($input['category_id'])         ? (int)$input['category_id'] : null;
    $supId     = !empty($input['default_supplier_id']) ? (int)$input['default_supplier_id'] : null;
    $reorder   = (int)($input['reorder_level'] ?? 10);
    $cost      = (float)($input['unit_cost']   ?? 0);
    $price     = (float)($input['unit_price']  ?? 0);
    $abc       = in_array(strtoupper($input['abc_class'] ?? 'C'), ['A','B','C']) ? strtoupper($input['abc_class']) : 'C';

    if ($productId) {
        $old = $pdo->prepare("SELECT unit_price, abc_class FROM products WHERE product_id = ?");
        $old->execute([$productId]);
        $oldData = $old->fetch();
        $pdo->prepare("
            UPDATE products SET sku=?,barcode=?,name=?,category_id=?,default_supplier_id=?,
            reorder_level=?,unit_cost=?,unit_price=?,abc_class=? WHERE product_id=?
        ")->execute([$sku,$barcode,$name,$catId,$supId,$reorder,$cost,$price,$abc,$productId]);
        write_audit($pdo,'UPDATE','products',$productId,['old'=>$oldData,'new'=>['unit_price'=>$price,'abc_class'=>$abc]]);
        json_response(['message'=>'Product updated.']);
    } else {
        $chk = $pdo->prepare("SELECT product_id FROM products WHERE sku=?");
        $chk->execute([$sku]);
        if ($chk->fetch()) json_response(['error'=>'SKU already exists.'],400);
        $pdo->prepare("
            INSERT INTO products(sku,barcode,name,category_id,default_supplier_id,reorder_level,unit_cost,unit_price,abc_class)
            VALUES(?,?,?,?,?,?,?,?,?)
        ")->execute([$sku,$barcode,$name,$catId,$supId,$reorder,$cost,$price,$abc]);
        $newId = (int)$pdo->lastInsertId();
        write_audit($pdo,'CREATE','products',$newId,['sku'=>$sku,'name'=>$name]);
        json_response(['message'=>'Product created.','product_id'=>$newId],201);
    }

} elseif ($method === 'DELETE') {
    require_admin();
    validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $pdo->prepare("DELETE FROM products WHERE product_id=?")->execute([$id]);
    write_audit($pdo,'DELETE','products',$id);
    json_response(['message'=>'Product deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'],405);
}
