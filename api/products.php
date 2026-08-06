<?php
// api/products.php - Products CRUD API Endpoint

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $lowStockOnly = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

    if ($productId > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, c.category_name, s.supplier_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
            WHERE p.product_id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            json_response(['error' => 'Product not found.'], 404);
        }
        json_response($product);
    } else {
        $sql = "
            SELECT p.*, c.category_name, s.supplier_name 
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        ";
        if ($lowStockOnly) {
            $sql .= " WHERE p.quantity <= p.reorder_level";
        }
        $sql .= " ORDER BY p.product_id DESC";

        $stmt = $pdo->query($sql);
        json_response($stmt->fetchAll());
    }
} elseif ($method === 'POST') {
    require_admin(); // Creation/Editing requires Admin role

    $input = get_json_input();
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $sku = trim($input['sku'] ?? '');
    $name = trim($input['name'] ?? '');
    $categoryId = !empty($input['category_id']) ? (int)$input['category_id'] : null;
    $supplierId = !empty($input['supplier_id']) ? (int)$input['supplier_id'] : null;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
    $reorderLevel = isset($input['reorder_level']) ? (int)$input['reorder_level'] : 10;
    $unitPrice = isset($input['unit_price']) ? (float)$input['unit_price'] : 0.00;

    if (empty($sku) || empty($name)) {
        json_response(['error' => 'SKU and Product Name are required.'], 400);
    }

    if ($quantity < 0 || $reorderLevel < 0 || $unitPrice < 0) {
        json_response(['error' => 'Quantity, reorder level, and unit price must be non-negative.'], 400);
    }

    if ($productId > 0) {
        // Check SKU uniqueness excluding current product
        $chk = $pdo->prepare("SELECT product_id FROM products WHERE sku = ? AND product_id != ?");
        $chk->execute([$sku, $productId]);
        if ($chk->fetch()) {
            json_response(['error' => 'SKU already exists on another product.'], 400);
        }

        $stmt = $pdo->prepare("
            UPDATE products 
            SET sku = ?, name = ?, category_id = ?, supplier_id = ?, quantity = ?, reorder_level = ?, unit_price = ?
            WHERE product_id = ?
        ");
        $stmt->execute([$sku, $name, $categoryId, $supplierId, $quantity, $reorderLevel, $unitPrice, $productId]);
        json_response(['message' => 'Product updated successfully.']);
    } else {
        // Check SKU uniqueness
        $chk = $pdo->prepare("SELECT product_id FROM products WHERE sku = ?");
        $chk->execute([$sku]);
        if ($chk->fetch()) {
            json_response(['error' => 'SKU already exists.'], 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO products (sku, name, category_id, supplier_id, quantity, reorder_level, unit_price)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$sku, $name, $categoryId, $supplierId, $quantity, $reorderLevel, $unitPrice]);
        json_response(['message' => 'Product created successfully.', 'product_id' => $pdo->lastInsertId()], 201);
    }
} elseif ($method === 'DELETE') {
    require_admin();

    $input = get_json_input();
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($input['product_id'] ?? 0);

    if ($productId <= 0) {
        json_response(['error' => 'Invalid product ID.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);
    json_response(['message' => 'Product deleted successfully.']);
} else {
    json_response(['error' => 'Method not allowed.'], 405);
}
