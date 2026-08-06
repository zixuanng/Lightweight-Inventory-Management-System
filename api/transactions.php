<?php
// api/transactions.php - Stock Movements & Audit Log API

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch transaction logs
    $stmt = $pdo->query("
        SELECT t.*, p.name AS product_name, p.sku AS product_sku, u.username
        FROM stock_transactions t
        JOIN products p ON t.product_id = p.product_id
        JOIN users u ON t.user_id = u.user_id
        ORDER BY t.transaction_id DESC
    ");
    json_response($stmt->fetchAll());
} elseif ($method === 'POST') {
    // Both Admin and Staff can log stock transactions
    $input = get_json_input();
    $productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
    $type = strtoupper(trim($input['transaction_type'] ?? ''));
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;
    $notes = trim($input['notes'] ?? '');
    $user = get_logged_user();

    if ($productId <= 0) {
        json_response(['error' => 'Product selection is required.'], 400);
    }

    if (!in_array($type, ['STOCK_IN', 'STOCK_OUT'], true)) {
        json_response(['error' => 'Invalid transaction type. Must be STOCK_IN or STOCK_OUT.'], 400);
    }

    if ($quantity <= 0) {
        json_response(['error' => 'Transaction quantity must be greater than zero.'], 400);
    }

    try {
        // Begin SQL Transaction for Data Integrity
        $pdo->beginTransaction();

        // Lock product row to inspect current stock level reliably
        $stmtLock = $pdo->prepare("SELECT product_id, quantity, name FROM products WHERE product_id = ? FOR UPDATE");
        $stmtLock->execute([$productId]);
        $product = $stmtLock->fetch();

        if (!$product) {
            $pdo->rollBack();
            json_response(['error' => 'Product not found.'], 404);
        }

        $currentQty = (int)$product['quantity'];

        if ($type === 'STOCK_OUT') {
            if ($currentQty < $quantity) {
                $pdo->rollBack();
                json_response([
                    'error' => "Insufficient stock. Item '{$product['name']}' currently has {$currentQty} unit(s) available."
                ], 400);
            }
            $newQty = $currentQty - $quantity;
        } else {
            // STOCK_IN
            $newQty = $currentQty + $quantity;
        }

        // Update product quantity
        $stmtUpdate = $pdo->prepare("UPDATE products SET quantity = ? WHERE product_id = ?");
        $stmtUpdate->execute([$newQty, $productId]);

        // Record line item in stock_transactions table
        $stmtLog = $pdo->prepare("
            INSERT INTO stock_transactions (product_id, transaction_type, quantity, user_id, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([$productId, $type, $quantity, $user['user_id'], $notes]);

        // Commit SQL Transaction
        $pdo->commit();

        json_response([
            'message' => "Transaction completed successfully ({$type} {$quantity} units).",
            'new_quantity' => $newQty
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        json_response(['error' => 'Transaction failed: ' . $e->getMessage()], 500);
    }
} else {
    json_response(['error' => 'Method not allowed.'], 405);
}
