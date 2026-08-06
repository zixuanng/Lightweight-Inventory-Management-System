<?php
// api/suppliers.php - Suppliers CRUD API Endpoint

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $supId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($supId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supId]);
        $sup = $stmt->fetch();
        if (!$sup) {
            json_response(['error' => 'Supplier not found.'], 404);
        }
        json_response($sup);
    } else {
        $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
        json_response($stmt->fetchAll());
    }
} elseif ($method === 'POST') {
    require_admin();

    $input = get_json_input();
    $supId = isset($input['supplier_id']) ? (int)$input['supplier_id'] : 0;
    $supName = trim($input['supplier_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');

    if (empty($supName)) {
        json_response(['error' => 'Supplier Name is required.'], 400);
    }

    if ($supId > 0) {
        $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name = ?, email = ?, phone = ? WHERE supplier_id = ?");
        $stmt->execute([$supName, $email, $phone, $supId]);
        json_response(['message' => 'Supplier updated successfully.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, email, phone) VALUES (?, ?, ?)");
        $stmt->execute([$supName, $email, $phone]);
        json_response(['message' => 'Supplier created successfully.', 'supplier_id' => $pdo->lastInsertId()], 201);
    }
} elseif ($method === 'DELETE') {
    require_admin();

    $supId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($supId <= 0) {
        json_response(['error' => 'Invalid supplier ID.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supId]);
    json_response(['message' => 'Supplier deleted successfully.']);
} else {
    json_response(['error' => 'Method not allowed.'], 405);
}
