<?php

declare(strict_types=1);


require_once __DIR__ . '/helpers.php';

function findUserByEmail(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => strtolower(trim($email))]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function findUserById(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function loginUser(array $user): void
{
    ensureSessionStarted();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = (string) $user['role'];
}

function logoutUser(): void
{
    ensureSessionStarted();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function authUser(): array
{
    ensureSessionStarted();
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        errorResponse('Unauthenticated', 401);
    }

    $user = findUserById($userId);
    if (!$user) {
        errorResponse('User not found', 401);
    }

    if (($user['status'] ?? '') !== 'ACTIVE' && ($user['role'] ?? '') !== 'ADMIN') {
        errorResponse('Account is not active', 403);
    }

    return $user;
}

function requireRole(string ...$roles): array
{
    $user = authUser();
    if (!in_array($user['role'], $roles, true)) {
        errorResponse('Forbidden', 403);
    }

    return $user;
}
