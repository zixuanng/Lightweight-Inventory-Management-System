<?php
// api/categories.php - Categories CRUD API Endpoint

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $catId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($catId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
        $stmt->execute([$catId]);
        $cat = $stmt->fetch();
        if (!$cat) {
            json_response(['error' => 'Category not found.'], 404);
        }
        json_response($cat);
    } else {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
        json_response($stmt->fetchAll());
    }
} elseif ($method === 'POST') {
    require_admin();

    $input = get_json_input();
    $catId = isset($input['category_id']) ? (int)$input['category_id'] : 0;
    $catName = trim($input['category_name'] ?? '');
    $desc = trim($input['description'] ?? '');

    if (empty($catName)) {
        json_response(['error' => 'Category Name is required.'], 400);
    }

    if ($catId > 0) {
        $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
        $stmt->execute([$catName, $desc, $catId]);
        json_response(['message' => 'Category updated successfully.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
        $stmt->execute([$catName, $desc]);
        json_response(['message' => 'Category created successfully.', 'category_id' => $pdo->lastInsertId()], 201);
    }
} elseif ($method === 'DELETE') {
    require_admin();

    $catId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($catId <= 0) {
        json_response(['error' => 'Invalid category ID.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->execute([$catId]);
    json_response(['message' => 'Category deleted successfully.']);
} else {
    json_response(['error' => 'Method not allowed.'], 405);
}
