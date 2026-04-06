<?php
// php/lib/helpers.php — JSON response helpers, auth, validation

declare(strict_types=1);

function json_ok(array $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, int $code = 400, array $extra = []): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function require_json_post(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('POST required', 405);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) json_err('Invalid JSON body', 400);
    return $data;
}

function get_params(): array {
    return $_GET;
}

// ---------- AUTH ----------

function require_auth(): array {
    session_start();
    if (empty($_SESSION['user_id'])) json_err('Unauthenticated', 401);
    return [
        'id'   => (int)$_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
    ];
}

function require_admin(): array {
    $user = require_auth();
    if ($user['role'] !== 'admin') json_err('Forbidden', 403);
    return $user;
}

// ---------- VALIDATION ----------

function v_required(array $data, array $keys): void {
    foreach ($keys as $k) {
        if (!isset($data[$k]) || $data[$k] === '') {
            json_err("Missing required field: $k");
        }
    }
}

function v_positive_decimal($val, string $field): string {
    if (!is_numeric($val) || bccomp((string)$val, '0', 8) <= 0)
        json_err("$field must be a positive number");
    return number_format((float)$val, 4, '.', '');
}

// ---------- IDEMPOTENCY ----------

function idempotency_key(string ...$parts): string {
    return hash('sha256', implode('|', $parts));
}
