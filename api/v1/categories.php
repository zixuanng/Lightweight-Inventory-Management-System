<?php
// api/v1/categories.php — Categories CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    json_response($pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll());
} elseif ($method === 'POST') {
    require_manager(); validate_csrf_token();
    $input = get_json_input();
    $id    = (int)($input['category_id'] ?? 0);
    $name  = trim($input['category_name'] ?? '');
    if (!$name) json_response(['error'=>'Category name required.'],400);

    if ($id) {
        $old = $pdo->prepare("SELECT category_name FROM categories WHERE category_id=?");
        $old->execute([$id]);
        $oldData = $old->fetch();
        $pdo->prepare("UPDATE categories SET category_name=? WHERE category_id=?")->execute([$name, $id]);
        write_audit($pdo, 'UPDATE', 'categories', $id, ['old'=>$oldData['category_name'], 'new'=>$name]);
        json_response(['message'=>'Category updated.']);
    } else {
        $pdo->prepare("INSERT INTO categories(category_name) VALUES(?)")->execute([$name]);
        $newId = (int)$pdo->lastInsertId();
        write_audit($pdo, 'CREATE', 'categories', $newId, ['category_name'=>$name]);
        json_response(['message'=>'Category created.', 'category_id'=>$newId], 201);
    }
} elseif ($method === 'DELETE') {
    require_admin(); validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $pdo->prepare("DELETE FROM categories WHERE category_id=?")->execute([$id]);
    write_audit($pdo, 'DELETE', 'categories', $id);
    json_response(['message'=>'Category deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'], 405);
}
