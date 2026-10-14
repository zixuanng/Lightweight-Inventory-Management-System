<?php
// api/v1/auth/logout.php
require_once __DIR__ . '/../../../config/auth.php';
unset($_SESSION['user'], $_SESSION['csrf_token']);
session_destroy();
json_response(['message' => 'Logged out successfully.']);
