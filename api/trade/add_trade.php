<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/idempotency.php';
require_once __DIR__ . '/../../core/ledger.php';

requirePost();
requireRole('ADMIN');

$data = requestData();
$orderId = trim((string) ($data['order_id'] ?? ''));
$lots = round((float) ($data['lots'] ?? 0), 2);
$tradeDate = trim((string) ($data['trade_date'] ?? ''));
$userIdentifier = trim((string) ($data['user_id'] ?? $data['email'] ?? ''));

$requestPayload = [
    'order_id' => $orderId,
    'lots' => $lots,
    'trade_date' => $tradeDate,
    'user_identifier' => $userIdentifier,
];

$idempotencyKey = requestIdempotencyKey();
if ($idempotencyKey) {
    replayIdempotentResponse('trade.add', $idempotencyKey, $requestPayload);
}

if ($orderId === '' || $tradeDate === '' || $userIdentifier === '' || $lots <= 0) {
    errorResponse('order_id, user_id/email, lots and trade_date are required', 422);
}

$user = ctype_digit($userIdentifier) ? findUserById((int) $userIdentifier) : findUserByEmail($userIdentifier);
if (!$user) {
    errorResponse('User not found', 404);
}

$result = withTransaction(function (PDO $pdo) use ($orderId, $user, $lots, $tradeDate): array {
    $result = processTradeCredit($pdo, $user, $orderId, $lots, $tradeDate, 'MANUAL');

    $logStmt = $pdo->prepare(
        'INSERT INTO sync_logs (method, trades_processed, total_lots, duplicates_count, status, summary, created_at)
         VALUES (\'MANUAL\', :trades_processed, :total_lots, :duplicates_count, \'SUCCESS\', :summary, NOW())'
    );
    $logStmt->execute([
        'trades_processed' => $result['duplicate'] ? 0 : 1,
        'total_lots' => $result['duplicate'] ? toDecimal(0) : toDecimal($lots),
        'duplicates_count' => $result['duplicate'] ? 1 : 0,
        'summary' => sprintf('Manual trade %s handled for user %s', $orderId, $user['email']),
    ]);

    return $result;
});

if ($idempotencyKey) {
    rememberIdempotentResponse('trade.add', $idempotencyKey, $result);
}

jsonResponse($result);
