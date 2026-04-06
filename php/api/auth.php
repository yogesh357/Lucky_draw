<?php
// php/api/auth.php
// POST /api/auth?action=login|logout

declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

session_start();
$action = $_GET['action'] ?? 'login';

if ($action === 'logout') {
    session_destroy();
    json_ok(['message' => 'Logged out']);
}

// LOGIN
$body = require_json_post();
v_required($body, ['email', 'password']);

$user = DB::fetch(
    "SELECT id, email, 	master_user , password FROM users WHERE email = ?",
    [trim($body['email'])]
);

if (!$user ||   !password_verify($body['password'], $user['password'])) {
    json_err('Invalid credentials', 401);
}

$_SESSION['user_id']   = $user['id'];
$_SESSION['user_role'] = $user['master_user'];
$_SESSION['user_email'] = $user['email'];
// $_SESSION['user_unique_id'] = $user['uuid'];
$_SESSION['master_user'] = ($user['master_user'] === 'admin') ? 1 : 0;

// DB::query(
//     "INSERT INTO audit_log (actor_id, action, ip) VALUES (?, 'login', ?)",
//     [$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]
// );

json_ok([
    'user_id'  => $user['id'],
    'role'     => $user['master_user'],
]);
