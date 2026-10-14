<?php
// config/auth.php — Session, RBAC, CSRF, Audit helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Session helpers ──────────────────────────────────────────
function get_logged_user()  { return $_SESSION['user'] ?? null; }
function is_logged_in()     { return !empty($_SESSION['user']['user_id']); }
function current_role()     { return $_SESSION['user']['role_name'] ?? ''; }

// ── Role-based access helpers ────────────────────────────────
function is_super_admin()       { return current_role() === 'Super Admin'; }
function is_manager_or_above()  { return in_array(current_role(), ['Super Admin', 'Warehouse Manager']); }
function is_clerk_or_above()    { return in_array(current_role(), ['Super Admin', 'Warehouse Manager', 'Inventory Clerk']); }

// ── Middleware guards ────────────────────────────────────────
function require_login() {
    if (!is_logged_in()) {
        json_response(['error' => 'Authentication required.'], 401);
    }
}

function require_manager() {
    require_login();
    if (!is_manager_or_above()) {
        json_response(['error' => 'Permission denied. Warehouse Manager or higher required.'], 403);
    }
}

function require_admin() {
    require_login();
    if (!is_super_admin()) {
        json_response(['error' => 'Permission denied. Super Admin required.'], 403);
    }
}

function require_clerk() {
    require_login();
    if (!is_clerk_or_above()) {
        json_response(['error' => 'Permission denied. Inventory Clerk or higher required.'], 403);
    }
}

// ── CSRF helpers ─────────────────────────────────────────────
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? (get_json_input()['csrf_token'] ?? '');
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response(['error' => 'Invalid or missing CSRF token.'], 403);
    }
}

// ── Audit log writer ─────────────────────────────────────────
function write_audit(PDO $pdo, string $action, string $table, int $recordId, array $delta = []) {
    $user   = get_logged_user();
    $userId = $user['user_id'] ?? 0;
    $ip     = $_SERVER['HTTP_X_FORWARDED_FOR']
           ?? $_SERVER['REMOTE_ADDR']
           ?? '0.0.0.0';

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, table_name, record_id, delta_json, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $action,
        $table,
        $recordId,
        empty($delta) ? null : json_encode($delta),
        $ip
    ]);
}

// ── Response helpers ─────────────────────────────────────────
function json_response($data, int $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return $_POST;
}
