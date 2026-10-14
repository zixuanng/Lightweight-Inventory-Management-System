<?php
// api/v1/users.php — Users CRUD

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/auth.php';
require_login();
require_admin(); // Only Super Admins can manage users

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("
        SELECT u.user_id, u.username, u.role_id, r.role_name, u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        ORDER BY u.user_id ASC
    ")->fetchAll();
    json_response($rows);

} elseif ($method === 'POST') {
    validate_csrf_token();
    $input = get_json_input();
    $userId = (int)($input['user_id'] ?? 0);
    $user   = trim($input['username'] ?? '');
    $pass   = trim($input['password'] ?? '');
    $roleId = (int)($input['role_id'] ?? 0);

    if (!$user || !$roleId) json_response(['error'=>'Username and Role are required.'],400);

    if ($userId) {
        // Update user
        if ($pass) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET username=?, password_hash=?, role_id=? WHERE user_id=?")->execute([$user, $hash, $roleId, $userId]);
        } else {
            $pdo->prepare("UPDATE users SET username=?, role_id=? WHERE user_id=?")->execute([$user, $roleId, $userId]);
        }
        write_audit($pdo, 'UPDATE', 'users', $userId, ['username'=>$user, 'role_id'=>$roleId]);
        json_response(['message'=>'User updated.']);
    } else {
        // Create user
        if (!$pass) json_response(['error'=>'Password is required for new users.'],400);
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE username=?");
        $chk->execute([$user]);
        if ($chk->fetch()) json_response(['error'=>'Username already taken.'],400);

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users(username, password_hash, role_id) VALUES(?,?,?)")->execute([$user, $hash, $roleId]);
        $newId = (int)$pdo->lastInsertId();
        write_audit($pdo, 'CREATE', 'users', $newId, ['username'=>$user, 'role_id'=>$roleId]);
        json_response(['message'=>'User created.', 'user_id'=>$newId], 201);
    }
} elseif ($method === 'DELETE') {
    validate_csrf_token();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_response(['error'=>'Invalid ID.'],400);
    $currentUser = get_logged_user();
    if ($currentUser['user_id'] === $id) json_response(['error'=>'You cannot delete your own account.'],400);

    $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$id]);
    write_audit($pdo, 'DELETE', 'users', $id);
    json_response(['message'=>'User deleted.']);
} else {
    json_response(['error'=>'Method not allowed.'], 405);
}
