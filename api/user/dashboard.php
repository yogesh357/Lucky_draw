<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/ledger.php';

requireGet();
$user = authUser();

$monthStart = (new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d');
$monthlyLots = getMonthlyLots((int) $user['id'], $monthStart);
$expiringSpins = getSpinBalanceBefore((int) $user['id'], $monthStart);
$monthlyPoints = getMonthlySureWinPoints((int) $user['id'], $monthStart);

$activityStmt = db()->prepare(
    '(SELECT created_at, \'TRADE\' AS entry_type, CONCAT(order_id, \' synced\') AS detail, lots AS metric_a, NULL AS metric_b
      FROM trades
      WHERE user_id = :trade_user_id)
     UNION ALL
     (SELECT created_at, \'SPIN\' AS entry_type, reward_type AS detail, reward_value AS metric_a, surewin_points AS metric_b
      FROM spin_results
      WHERE user_id = :spin_user_id)
     UNION ALL
     (SELECT created_at, \'LAMBO\' AS entry_type, type AS detail, amount_usd AS metric_a, NULL AS metric_b
      FROM lambo_ledger
      WHERE user_id = :lambo_user_id)
     ORDER BY created_at DESC
     LIMIT 10'
);
$activityStmt->execute([
    'trade_user_id' => (int) $user['id'],
    'spin_user_id' => (int) $user['id'],
    'lambo_user_id' => (int) $user['id'],
]);

success([
    'data' => [
        'lots' => round($monthlyLots, 2),
        'spins' => getSpinBalance((int) $user['id']),
        'free_spins' => 0,
        'points' => getSureWinPoints((int) $user['id']),
        'points_month' => $monthlyPoints,
        'lambo' => round(getLamboBalance((int) $user['id']), 2),
        'expiring_spins' => $expiringSpins,
        'recent_activity' => $activityStmt->fetchAll(),
    ],
]);
