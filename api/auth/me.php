<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/auth.php';

requireGet();
$user = authUser();

success([
    'data' => [
        'id' => (int) $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status'],
    ],
]);
