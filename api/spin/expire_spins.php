<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/idempotency.php';
require_once __DIR__ . '/../../core/ledger.php';

requirePost();
requireRole('ADMIN');

$month = trim((string) (requestData()['month'] ?? (new DateTimeImmutable('first day of last month', new DateTimeZone('UTC')))->format('Y-m')));
if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
    errorResponse('month must be in YYYY-MM format', 422);
}

$requestPayload = ['month' => $month];
$idempotencyKey = requestIdempotencyKey() ?: 'expiry:' . $month;
replayIdempotentResponse('spin.expiry', $idempotencyKey, $requestPayload);

[$startDate, $endDate] = [
    $month . '-01',
    (new DateTimeImmutable($month . '-01 00:00:00', new DateTimeZone('UTC')))->modify('first day of next month')->format('Y-m-d'),
];

$result = withTransaction(function (PDO $pdo) use ($startDate, $endDate, $month): array {
    $stmt = $pdo->prepare(
        'SELECT user_id, GREATEST(COALESCE(SUM(amount), 0), 0) AS remaining_spins
         FROM spin_ledger
         WHERE source IN (\'TRADE\', \'SPIN_USE\', \'EXPIRY_JOB\', \'ADMIN\')
           AND created_at >= :start_date AND created_at < :end_date
         GROUP BY user_id
         HAVING remaining_spins > 0'
    );
    $stmt->execute([
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]);

    $expiredUsers = 0;
    $expiredSpins = 0;

    foreach ($stmt->fetchAll() as $row) {
        $userId = (int) $row['user_id'];
        $remainingSpins = (int) $row['remaining_spins'];
        $referenceId = sprintf('EXPIRY-%s-U%s', $month, $userId);

        $check = $pdo->prepare('SELECT COUNT(*) FROM spin_ledger WHERE reference_id = :reference_id AND type = \'EXPIRY\'');
        $check->execute(['reference_id' => $referenceId]);
        if ((int) $check->fetchColumn() > 0) {
            continue;
        }

        insertSpinLedger($userId, 'EXPIRY', 'EXPIRY_JOB', -$remainingSpins, -1 * $remainingSpins * SPIN_USD_PER_LOT, $referenceId);
        distributeExpiredLambo($userId, $remainingSpins * SPIN_USD_PER_LOT, $referenceId);

        $expiredUsers++;
        $expiredSpins += $remainingSpins;
    }

    return [
        'ok' => true,
        'message' => 'Expiry run completed',
        'data' => [
            'month' => $month,
            'expired_users' => $expiredUsers,
            'expired_spins' => $expiredSpins,
            'reallocated_usd' => round($expiredSpins * SPIN_USD_PER_LOT, 2),
        ],
    ];
});

rememberIdempotentResponse('spin.expiry', $idempotencyKey, $result);
jsonResponse($result);
