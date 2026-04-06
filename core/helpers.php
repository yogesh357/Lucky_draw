<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureSessionStarted(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name((string) config('app.session_name', 'madocks_session'));
    session_start();
}

function jsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requestData(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        return jsonInput();
    }

    return $_POST;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function success(array $data = [], int $status = 200): never
{
    jsonResponse(['ok' => true] + $data, $status);
}

function errorResponse(string $message, int $status = 400, array $extra = []): never
{
    jsonResponse(['ok' => false, 'message' => $message] + $extra, $status);
}

function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
}

function requireGet(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        errorResponse('Method not allowed', 405);
    }
}

function nowUtc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function todayUtc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
}

function toDecimal(float|int|string $value, int $scale = 2): string
{
    return number_format((float) $value, $scale, '.', '');
}

function withTransaction(callable $callback): mixed
{
    $pdo = db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $result = $callback($pdo);
        if ($started && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return $result;
    } catch (Throwable $throwable) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $throwable;
    }
}

function getConfigValue(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT value FROM config WHERE key_name = :key_name');
    $stmt->execute(['key_name' => $key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string) $value;
}

function setConfigValue(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO config (key_name, value, updated_at) VALUES (:key_name, :value, NOW())
         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
    );
    $stmt->execute([
        'key_name' => $key,
        'value' => $value,
    ]);
}
