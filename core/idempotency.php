<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function requestIdempotencyKey(): ?string
{
    $header = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
    $header = trim($header);

    return $header !== '' ? $header : null;
}

function idempotencyFingerprint(array $payload): string
{
    ksort($payload);
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function rememberIdempotentResponse(string $scope, string $key, array $payload, int $statusCode = 200): void
{
    $stmt = db()->prepare(
        'INSERT INTO idempotency_keys (scope_name, idempotency_key, request_hash, response_code, response_body, created_at, updated_at)
         VALUES (:scope_name, :idempotency_key, :request_hash, :response_code, :response_body, NOW(), NOW())
         ON DUPLICATE KEY UPDATE response_code = VALUES(response_code), response_body = VALUES(response_body), updated_at = NOW()'
    );

    $stmt->execute([
        'scope_name' => $scope,
        'idempotency_key' => $key,
        'request_hash' => idempotencyFingerprint($payload),
        'response_code' => $statusCode,
        'response_body' => json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);
}

function replayIdempotentResponse(string $scope, string $key, array $requestPayload): void
{
    $stmt = db()->prepare(
        'SELECT request_hash, response_code, response_body
         FROM idempotency_keys
         WHERE scope_name = :scope_name AND idempotency_key = :idempotency_key
         LIMIT 1'
    );
    $stmt->execute([
        'scope_name' => $scope,
        'idempotency_key' => $key,
    ]);

    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $requestHash = idempotencyFingerprint($requestPayload);
    if (!hash_equals((string) $row['request_hash'], $requestHash)) {
        errorResponse('Idempotency key reused with different payload', 409);
    }

    $body = json_decode((string) $row['response_body'], true);
    jsonResponse(is_array($body) ? $body : ['ok' => false, 'message' => 'Invalid idempotent response'], (int) $row['response_code']);
}
