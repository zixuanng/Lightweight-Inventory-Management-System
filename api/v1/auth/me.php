<?php
// api/v1/auth/me.php — Return current session user
require_once __DIR__ . '/../../../config/auth.php';
if (is_logged_in()) {
    json_response([
        'user'       => get_logged_user(),
        'csrf_token' => generate_csrf_token(),
    ]);
} else {
    json_response(['user' => null]);
}
