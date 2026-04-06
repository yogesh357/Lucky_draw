<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/auth.php';

requirePost();

$data = requestData();
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    errorResponse('Email and password are required', 422);
}

$user = findUserByEmail($email);
if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    errorResponse('Invalid credentials', 401);
}

if (($user['status'] ?? '') !== 'ACTIVE' && ($user['role'] ?? '') !== 'ADMIN') {
    errorResponse('Account is not active', 403);
}

loginUser($user);

success([
    'message' => 'Login successful',
    'user' => [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
    ],
]);
