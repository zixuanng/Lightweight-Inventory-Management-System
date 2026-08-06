<?php
// api/users.php - User Management API Endpoint (Admin Only)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

require_admin(); // Restricted to Admin role

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT user_id, username, role, created_at FROM users ORDER BY user_id ASC");
    json_response($stmt->fetchAll());
} elseif ($method === 'POST') {
    $input = get_json_input();
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $role = trim($input['role'] ?? 'Staff');

    if (!in_array($role, ['Admin', 'Staff'], true)) {
        json_response(['error' => 'Invalid role specified.'], 400);
    }

    if ($userId > 0) {
        // Edit existing user
        if (!empty($password)) {
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET role = ?, password_hash = ? WHERE user_id = ?");
            $stmt->execute([$role, $passHash, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->execute([$role, $userId]);
        }
        json_response(['message' => 'User updated successfully.']);
    } else {
        // Create new user
        if (empty($username) || empty($password)) {
            json_response(['error' => 'Username and Password are required for new users.'], 400);
        }

        // Check if username exists
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            json_response(['error' => 'Username is already taken.'], 400);
        }

        $passHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $passHash, $role]);
        json_response(['message' => 'User created successfully.'], 201);
    }
} elseif ($method === 'DELETE') {
    $userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $currentUser = get_logged_user();

    if ($userId <= 0) {
        json_response(['error' => 'Invalid user ID.'], 400);
    }

    if ($userId === (int)$currentUser['user_id']) {
        json_response(['error' => 'You cannot delete your own account.'], 400);
    }

    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    json_response(['message' => 'User deleted successfully.']);
} else {
    json_response(['error' => 'Method not allowed.'], 405);
}
