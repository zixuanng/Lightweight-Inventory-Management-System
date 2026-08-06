<?php
// api/export.php - Clean CSV export generator for stock levels and audit logs

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$type = $_GET['type'] ?? 'stock';
$timestamp = date('Y-m-d_H-i-s');

if ($type === 'audit') {
    $filename = "audit_logs_{$timestamp}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Write CSV Header
    fputcsv($output, ['Transaction ID', 'SKU', 'Product Name', 'Type', 'Quantity', 'User', 'Notes', 'Date & Time']);

    $stmt = $pdo->query("
        SELECT t.transaction_id, p.sku, p.name AS product_name, t.transaction_type, t.quantity, u.username, t.notes, t.created_at
        FROM stock_transactions t
        JOIN products p ON t.product_id = p.product_id
        JOIN users u ON t.user_id = u.user_id
        ORDER BY t.transaction_id DESC
    ");

    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['transaction_id'],
            $row['sku'],
            $row['product_name'],
            $row['transaction_type'],
            $row['quantity'],
            $row['username'],
            $row['notes'],
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;

} else {
    // Current stock state CSV
    $filename = "stock_levels_{$timestamp}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // Write CSV Header
    fputcsv($output, ['Product ID', 'SKU', 'Product Name', 'Category', 'Supplier', 'Quantity', 'Reorder Level', 'Unit Price ($)', 'Total Value ($)', 'Status']);

    $stmt = $pdo->query("
        SELECT p.*, c.category_name, s.supplier_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        ORDER BY p.name ASC
    ");

    while ($row = $stmt->fetch()) {
        $totalVal = $row['quantity'] * $row['unit_price'];
        $status = ($row['quantity'] <= $row['reorder_level']) ? 'LOW STOCK' : 'OK';

        fputcsv($output, [
            $row['product_id'],
            $row['sku'],
            $row['name'],
            $row['category_name'] ?? 'Uncategorized',
            $row['supplier_name'] ?? 'None',
            $row['quantity'],
            $row['reorder_level'],
            number_format($row['unit_price'], 2),
            number_format($totalVal, 2),
            $status
        ]);
    }
    fclose($output);
    exit;
}
