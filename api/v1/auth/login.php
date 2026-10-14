<?php
// api/v1/auth/login.php

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed.'], 405);
}

$input    = get_json_input();
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (!$username || !$password) {
    json_response(['error' => 'Username and password are required.'], 400);
}

// Look up user + role
$stmt = $pdo->prepare("
    SELECT u.user_id, u.username, u.password_hash, u.role_id, r.role_name
    FROM users u
    JOIN roles r ON u.role_id = r.role_id
    WHERE u.username = ?
");
$stmt->execute([$username]);
$user = $stmt->fetch();

// Default seed accounts: if hash starts with 'NEEDS_REHASH:', extract plain password
$isValid = false;
if ($user) {
    if (str_starts_with($user['password_hash'], 'NEEDS_REHASH:')) {
        $seedPass = substr($user['password_hash'], strlen('NEEDS_REHASH:'));
        if ($password === $seedPass) {
            $isValid  = true;
            $newHash  = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                ->execute([$newHash, $user['user_id']]);
        }
    } elseif (password_verify($password, $user['password_hash'])) {
        $isValid = true;
    }
}

if (!$user || !$isValid) {
    json_response(['error' => 'Invalid username or password.'], 401);
}

// Start session
$_SESSION['user'] = [
    'user_id'   => $user['user_id'],
    'username'  => $user['username'],
    'role_id'   => $user['role_id'],
    'role_name' => $user['role_name'],
];

// Generate fresh CSRF token
$csrf = generate_csrf_token();

json_response([
    'message'    => 'Login successful.',
    'user'       => $_SESSION['user'],
    'csrf_token' => $csrf,
]);
